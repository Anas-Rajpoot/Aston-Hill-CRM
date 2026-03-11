import { defineStore } from 'pinia'
import { api, ensureCsrfCookie } from '@/lib/axios'

const BOOTSTRAP_CACHE_KEY = 'auth_bootstrap'
const BOOTSTRAP_CACHE_TTL_MS = 4 * 60 * 1000 // 4 min – under server 5 min
const FORCE_LOGOUT_KEY = 'force_logout_on_close'

/**
 * Returns the correct storage based on force_logout_on_close setting.
 * - Enabled  → sessionStorage (cleared when browser closes)
 * - Disabled → localStorage   (persists across browser sessions)
 */
function getStorage() {
  try {
    return sessionStorage.getItem(FORCE_LOGOUT_KEY) === '1' ? sessionStorage : localStorage
  } catch {
    return localStorage
  }
}

function setForceLogoutFlag(enabled) {
  try {
    if (enabled) {
      sessionStorage.setItem(FORCE_LOGOUT_KEY, '1')
      // Migrate bootstrap cache from localStorage → sessionStorage
      const existing = localStorage.getItem(BOOTSTRAP_CACHE_KEY)
      if (existing) {
        sessionStorage.setItem(BOOTSTRAP_CACHE_KEY, existing)
        localStorage.removeItem(BOOTSTRAP_CACHE_KEY)
      }
      localStorage.removeItem('api_token')
    } else {
      sessionStorage.removeItem(FORCE_LOGOUT_KEY)
      // Migrate bootstrap cache from sessionStorage → localStorage
      const existing = sessionStorage.getItem(BOOTSTRAP_CACHE_KEY)
      if (existing) {
        localStorage.setItem(BOOTSTRAP_CACHE_KEY, existing)
        sessionStorage.removeItem(BOOTSTRAP_CACHE_KEY)
      }
    }
  } catch {
    //
  }
}

function getBootstrapFromStorage() {
  try {
    const store = getStorage()
    const raw = store.getItem(BOOTSTRAP_CACHE_KEY)
    if (!raw) return null
    const { at, data } = JSON.parse(raw)
    if (Date.now() - at > BOOTSTRAP_CACHE_TTL_MS) return null
    return data
  } catch {
    return null
  }
}

function setBootstrapInStorage(data) {
  try {
    getStorage().setItem(BOOTSTRAP_CACHE_KEY, JSON.stringify({ at: Date.now(), data }))
  } catch {
    //
  }
}

function clearStoredApiToken() {
  try {
    sessionStorage.removeItem('api_token')
    localStorage.removeItem('api_token')
  } catch {
    //
  }
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: null,
    timezone: 'Asia/Dubai',
    defaultTablePageSize: 25,
    session: {
      timeout_minutes: 120,
      warning_enabled: false,
      warning_minutes_before: 5,
    },
    passwordAction: null, // 'must_change_password' | null
    loading: false,
    _fetchPromise: null,
    _lastFetchedAt: 0,
    _meMaxAgeMs: 55 * 1000,
  }),

  getters: {
    isAuthenticated: (state) => !!state.user,
  },

  actions: {
    async fetchUser(force = false) {
      if (this._fetchPromise) return this._fetchPromise
      if (!force && this.user && this._lastFetchedAt && Date.now() - this._lastFetchedAt < this._meMaxAgeMs) {
        return
      }
      const promise = this._doFetchUser()
      this._fetchPromise = promise
      try {
        await promise
      } finally {
        this._fetchPromise = null
      }
    },

    async _doFetchUser() {
      if (this.user?.pending2FA) return
      try {
        const hasToken = getStorage().getItem('api_token') || sessionStorage.getItem('api_token') || localStorage.getItem('api_token')
        const hasCsrfFromPage = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        if (!hasToken && !hasCsrfFromPage) {
          await ensureCsrfCookie()
        }

        const cached = getBootstrapFromStorage()
        if (cached?.user) {
          this.user = {
            id: cached.user.id,
            name: cached.user.name,
            email: cached.user.email,
            roles: cached.user.roles ?? [],
            permissions: cached.permissions ?? [],
          }
          if (cached.timezone) this.timezone = cached.timezone
          if (cached.defaultTablePageSize) this.defaultTablePageSize = Number(cached.defaultTablePageSize) || 25
          if (cached.session) this.session = { ...this.session, ...cached.session }
          this.passwordAction = cached.password_action ?? null
          this._lastFetchedAt = Date.now()
        }

        try {
          const { data } = await api.get('/bootstrap', {
            skipAuthRedirect: true,
            showToast: false,
          })
          const u = data.user ?? data
          const roles = Array.isArray(u.roles) ? u.roles.map((r) => (typeof r === 'string' ? r : r?.name)).filter(Boolean) : []
          this.user = { id: u.id, name: u.name, email: u.email, roles, permissions: data.permissions ?? [] }
          if (data.timezone) this.timezone = data.timezone
          if (data.default_table_page_size) this.defaultTablePageSize = Number(data.default_table_page_size) || 25
          if (data.session) {
            this.session = { ...this.session, ...data.session }
            setForceLogoutFlag(!!data.session.force_logout_on_close)
          }
          this.passwordAction = data.password_action ?? null
          this._lastFetchedAt = Date.now()
          setBootstrapInStorage({ user: this.user, permissions: this.user.permissions, timezone: this.timezone, defaultTablePageSize: this.defaultTablePageSize, session: this.session, password_action: this.passwordAction })
          return
        } catch (e) {
          const status = e?.response?.status
          const isAuthError = status === 401 || status === 419
          // Only trust cached user on transient failures (network/5xx), never on auth failures.
          if (this.user && !isAuthError) return
        }
        const { data } = await api.get('/me', {
          skipAuthRedirect: true,
          showToast: false,
        })
        const roles = Array.isArray(data.roles) ? data.roles.map((r) => (typeof r === 'string' ? r : r?.name)).filter(Boolean) : []
        this.user = { ...data, roles, permissions: [] }
        if (data.timezone) this.timezone = data.timezone
        if (data.default_table_page_size) this.defaultTablePageSize = Number(data.default_table_page_size) || 25
        if (data.session) this.session = { ...this.session, ...data.session }
        this.passwordAction = data.password_action ?? null
        this._lastFetchedAt = Date.now()
      } catch (e) {
        const status = e?.response?.status
        const isAuthError = status === 401 || status === 419
        // Clear auth state for real auth errors, and also when we had no user to begin with.
        if (isAuthError || !this.user) {
          this.user = null
          this._lastFetchedAt = 0
          try {
            sessionStorage.removeItem(BOOTSTRAP_CACHE_KEY)
            localStorage.removeItem(BOOTSTRAP_CACHE_KEY)
          } catch {
            //
          }
        }
      }
    },

    async login(credentials, options = {}) {
      this.loading = true
      try {
        if (!options.token) {
          await ensureCsrfCookie(true)
        }
        const headers = options.token ? { 'X-Request-Token': 'true' } : {}
        const requestConfig = { headers, showToast: false }
        let response
        try {
          response = await api.post('/auth/login', credentials, requestConfig)
        } catch (error) {
          if (!options.token && error?.response?.status === 419) {
            await ensureCsrfCookie(true)
            response = await api.post('/auth/login', credentials, requestConfig)
          } else {
            throw error
          }
        }
        const { data } = response
        // Capture password_action from login response
        this.passwordAction = data.password_action ?? null
        if (data.token) {
          this.token = data.token
          getStorage().setItem('api_token', data.token)
          this.user = { ...data.user, permissions: data.user?.permissions ?? [] }
          this._lastFetchedAt = Date.now()
          setBootstrapInStorage({ user: this.user, permissions: this.user.permissions, password_action: this.passwordAction })
          return data
        }
        if (data.user && data.permissions) {
          // Session-based login should not carry stale bearer tokens.
          clearStoredApiToken()
          this.token = null
          this.user = { ...data.user, permissions: data.permissions }

          // Verify that server session is actually established before routing to protected pages.
          // This prevents "login succeeded, then immediate 401/logout" when cookies are blocked/missing.
          try {
            const { data: bootstrap } = await api.get('/bootstrap', {
              skipAuthRedirect: true,
              showToast: false,
            })
            const u = bootstrap.user ?? bootstrap
            const roles = Array.isArray(u.roles) ? u.roles.map((r) => (typeof r === 'string' ? r : r?.name)).filter(Boolean) : []
            this.user = { id: u.id, name: u.name, email: u.email, roles, permissions: bootstrap.permissions ?? [] }
            if (bootstrap.timezone) this.timezone = bootstrap.timezone
            if (bootstrap.default_table_page_size) this.defaultTablePageSize = Number(bootstrap.default_table_page_size) || 25
            if (bootstrap.session) {
              this.session = { ...this.session, ...bootstrap.session }
              setForceLogoutFlag(!!bootstrap.session.force_logout_on_close)
            }
            this.passwordAction = bootstrap.password_action ?? this.passwordAction
            this._lastFetchedAt = Date.now()
            setBootstrapInStorage({
              user: this.user,
              permissions: this.user.permissions,
              timezone: this.timezone,
              defaultTablePageSize: this.defaultTablePageSize,
              session: this.session,
              password_action: this.passwordAction,
            })
            return data
          } catch (e) {
            this.user = null
            this._lastFetchedAt = 0
            try {
              sessionStorage.removeItem(BOOTSTRAP_CACHE_KEY)
              localStorage.removeItem(BOOTSTRAP_CACHE_KEY)
            } catch {
              //
            }
            const status = e?.response?.status
            if (status === 401 || status === 419) {
              const authError = new Error('Unable to establish a login session. Please try signing in again.')
              authError.response = { data: { message: 'Unable to establish a login session. Please try signing in again.' } }
              throw authError
            }
            throw e
          }
        }
        if (data.redirect === '/2fa/verify') {
          // 2FA flow is also session-based; clear stale bearer tokens.
          clearStoredApiToken()
          this.token = null
          this.user = { pending2FA: true }
          try {
            sessionStorage.removeItem(BOOTSTRAP_CACHE_KEY)
            localStorage.removeItem(BOOTSTRAP_CACHE_KEY)
          } catch {
            //
          }
          return data
        }
        await this.fetchUser()
        return data
      } finally {
        this.loading = false
      }
    },

    async logout() {
      try {
        await api.post('/auth/logout')
      } finally {
        this.user = null
        this.token = null
        this.passwordAction = null
        this._lastFetchedAt = 0
        sessionStorage.removeItem('api_token')
        localStorage.removeItem('api_token')
        try {
          sessionStorage.removeItem(BOOTSTRAP_CACHE_KEY)
          localStorage.removeItem(BOOTSTRAP_CACHE_KEY)
          sessionStorage.removeItem(FORCE_LOGOUT_KEY)
        } catch {
          //
        }
      }
    },

    async verify2FA(code) {
      const { data } = await api.post('/auth/2fa/verify', { otp: code })
      this.user = null
      await this.fetchUser()
      return data
    },
  },
})
