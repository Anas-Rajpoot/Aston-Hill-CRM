import axios from 'axios'

const csrfToken = () => {
  // Prefer the XSRF-TOKEN cookie (set/refreshed by /sanctum/csrf-cookie)
  const cookieMatch = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  if (cookieMatch) {
    return decodeURIComponent(cookieMatch[1])
  }
  // Fall back to meta tag (set during initial server-rendered page load)
  const meta = document.querySelector('meta[name="csrf-token"]')
  return meta ? meta.getAttribute('content') : null
}

// API axios – baseURL /api for all API calls (session or token auth)
export const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  headers: {
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

// Add Bearer token when using token auth (e.g. separate frontend deploy)
api.interceptors.request.use((config) => {
  const forceLogout = sessionStorage.getItem('force_logout_on_close') === '1'
  const token = forceLogout
    ? sessionStorage.getItem('api_token')
    : (localStorage.getItem('api_token') || sessionStorage.getItem('api_token'))
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

// Web axios – no prefix, for Sanctum csrf, login, logout (web routes)
export const web = axios.create({
  baseURL: '',
  withCredentials: true,
  headers: {
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

// Add CSRF token to all requests (web and api) for Laravel
const addCsrf = (config) => {
  const token = csrfToken()
  if (token) config.headers['X-XSRF-TOKEN'] = token
  return config
}
api.interceptors.request.use(addCsrf)
web.interceptors.request.use(addCsrf)

// Intercept X-Password-Action header from EnforcePasswordExpiry middleware.
// When the server signals a password change is needed, update the auth store and redirect.
api.interceptors.response.use(
  (res) => {
    const pwAction = res.headers?.['x-password-action']
    if (pwAction && (pwAction === 'must_change_password' || pwAction === 'password_expired')) {
      // Lazy import to avoid circular dependency at module load
      import('@/stores/auth').then(({ useAuthStore }) => {
        const auth = useAuthStore()
        if (auth.passwordAction !== pwAction) {
          auth.passwordAction = pwAction
        }
      })
    }
    return res
  },
  (err) => {
    const data = err?.response?.data
    if (data && typeof data === 'object') {
      const rawMessage = String(data.message || '')
      const looksTechnical =
        /SQLSTATE|QueryException|PDOException|Stack trace| in .*\.php|line \d+/i.test(rawMessage)

      if (looksTechnical) {
        data.status = data.status || 'fail'
        data.message = 'Request failed. Please contact support if the issue persists.'
        if ('exception' in data) delete data.exception
        if ('file' in data) delete data.file
        if ('line' in data) delete data.line
        if ('trace' in data) delete data.trace
      }
    }
    return Promise.reject(err)
  }
)

// Detect session termination (single-session enforcement: another device logged in).
// Force immediate logout and redirect to login with a message.
api.interceptors.response.use(
  (res) => res,
  (err) => {
    if (
      err.response?.status === 401 &&
      err.response?.data?.reason === 'session_terminated'
    ) {
      import('@/stores/auth').then(({ useAuthStore }) => {
        const auth = useAuthStore()
        auth.user = null
        auth.token = null
        auth.passwordAction = null
        auth._lastFetchedAt = 0
        sessionStorage.removeItem('api_token')
        localStorage.removeItem('api_token')
        try {
          sessionStorage.removeItem('auth_bootstrap')
          localStorage.removeItem('auth_bootstrap')
          sessionStorage.removeItem('force_logout_on_close')
        } catch { /* */ }
      })
      // Store message for login page to display
      try {
        sessionStorage.setItem('session_terminated_msg', err.response.data.message || 'Your session was ended because you logged in on another device.')
      } catch { /* */ }
      window.location.href = '/login'
      return new Promise(() => {}) // never resolves – page is navigating away
    }
    return Promise.reject(err)
  }
)

// Retry with exponential backoff for transient errors (429, 5xx). Cancel-safe: respect config.signal.
const RETRY_STATUSES = [429, 500, 502, 503, 504]
const RETRY_MAX = 3
const RETRY_DELAY_MS = 1000

api.interceptors.response.use(
  (res) => res,
  async (err) => {
    const config = err.config
    if (!config || config.__retryCount >= RETRY_MAX) return Promise.reject(err)
    const status = err.response?.status
    if (!status || !RETRY_STATUSES.includes(status)) return Promise.reject(err)
    if (config.signal?.aborted) return Promise.reject(err)
    config.__retryCount = (config.__retryCount || 0) + 1
    const delay = RETRY_DELAY_MS * Math.pow(2, config.__retryCount - 1)
    await new Promise((r) => setTimeout(r, delay))
    if (config.signal?.aborted) return Promise.reject(err)
    return api.request(config)
  }
)

export default api
