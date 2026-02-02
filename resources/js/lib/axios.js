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

export default api
