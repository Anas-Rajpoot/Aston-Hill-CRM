import { defineStore } from 'pinia'
import { api, web } from '@/lib/axios'

const BOOTSTRAP_CACHE_KEY = 'auth_bootstrap'
const BOOTSTRAP_CACHE_TTL_MS = 4 * 60 * 1000 // 4 min – under server 5 min

function getBootstrapFromStorage() {
  try {
    const raw = localStorage.getItem(BOOTSTRAP_CACHE_KEY)
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
    localStorage.setItem(BOOTSTRAP_CACHE_KEY, JSON.stringify({ at: Date.now(), data }))
  } catch {
    //
  }
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: null,
    timezone: 'Asia/Dubai',
    session: {
      timeout_minutes: 30,
      warning_enabled: false,
      warning_minutes_before: 5,
    },
    loading: false,
    _fetchPromise: null,
    _lastFetchedAt: 0,
    _meMaxAgeMs: 55 * 1000,
  }),

  getters: {
    isAuthenticated: (state) => !!state.user,
  },

  actions: {
    async fetchUser() {
      if (this._fetchPromise) return this._fetchPromise
      if (this.user && this._lastFetchedAt && Date.now() - this._lastFetchedAt < this._meMaxAgeMs) {
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
        const hasToken = sessionStorage.getItem('api_token') || localStorage.getItem('api_token')
        const hasCsrfFromPage = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        if (!hasToken && !hasCsrfFromPage) {
          await web.get('/sanctum/csrf-cookie')
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
          if (cached.session) this.session = { ...this.session, ...cached.session }
          this._lastFetchedAt = Date.now()
        }

        try {
          const { data } = await api.get('/bootstrap')
          const u = data.user ?? data
          const roles = Array.isArray(u.roles) ? u.roles.map((r) => (typeof r === 'string' ? r : r?.name)).filter(Boolean) : []
          this.user = { id: u.id, name: u.name, email: u.email, roles, permissions: data.permissions ?? [] }
          if (data.timezone) this.timezone = data.timezone
          if (data.session) this.session = { ...this.session, ...data.session }
          this._lastFetchedAt = Date.now()
          setBootstrapInStorage({ user: this.user, permissions: this.user.permissions, timezone: this.timezone, session: this.session })
          return
        } catch {
          if (this.user) return
        }
        const { data } = await api.get('/me')
        const roles = Array.isArray(data.roles) ? data.roles.map((r) => (typeof r === 'string' ? r : r?.name)).filter(Boolean) : []
        this.user = { ...data, roles, permissions: [] }
        if (data.timezone) this.timezone = data.timezone
        if (data.session) this.session = { ...this.session, ...data.session }
        this._lastFetchedAt = Date.now()
      } catch {
        this.user = null
        this._lastFetchedAt = 0
        try {
          localStorage.removeItem(BOOTSTRAP_CACHE_KEY)
        } catch {
          //
        }
      }
    },

    async login(credentials, options = {}) {
      this.loading = true
      try {
        const hasCsrfFromPage = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        if (!options.token && !hasCsrfFromPage) {
          await web.get('/sanctum/csrf-cookie')
        }
        const headers = options.token ? { 'X-Request-Token': 'true' } : {}
        const { data } = await api.post('/auth/login', credentials, { headers })
        if (data.token) {
          this.token = data.token
          sessionStorage.setItem('api_token', data.token)
          this.user = { ...data.user, permissions: data.user?.permissions ?? [] }
          this._lastFetchedAt = Date.now()
          setBootstrapInStorage({ user: this.user, permissions: this.user.permissions })
          return data
        }
        if (data.user && data.permissions) {
          this.user = { ...data.user, permissions: data.permissions }
          this._lastFetchedAt = Date.now()
          setBootstrapInStorage({ user: this.user, permissions: data.permissions })
          return data
        }
        if (data.redirect === '/2fa/verify') {
          this.user = { pending2FA: true }
          try {
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
        this._lastFetchedAt = 0
        sessionStorage.removeItem('api_token')
        localStorage.removeItem('api_token')
        try {
          localStorage.removeItem(BOOTSTRAP_CACHE_KEY)
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
