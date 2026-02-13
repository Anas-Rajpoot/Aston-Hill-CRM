import axios from 'axios'

const csrfToken = () => {
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
  const token = sessionStorage.getItem('api_token') || localStorage.getItem('api_token')
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
