import { defineStore } from 'pinia'
import { api, web } from '@/lib/axios'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: null, // for future token auth (separate frontend deploy)
    loading: false,
  }),

  getters: {
    isAuthenticated: (state) => !!state.user,
  },

  actions: {
    async fetchUser() {
      try {
        const hasToken = sessionStorage.getItem('api_token') || localStorage.getItem('api_token')
        if (!hasToken) {
          await web.get('/sanctum/csrf-cookie')
        }
        const { data } = await api.get('/me')
        const rolesRaw = data.roles ?? []
        const roles = Array.isArray(rolesRaw)
          ? rolesRaw.map((r) => (typeof r === 'string' ? r : r?.name)).filter(Boolean)
          : []
        this.user = {
          ...data,
          roles,
        }
      } catch {
        this.user = null
      }
    },

    async login(credentials, options = {}) {
      this.loading = true
      try {
        if (!options.token) {
          await web.get('/sanctum/csrf-cookie')
        }
        const headers = options.token ? { 'X-Request-Token': 'true' } : {}
        const { data } = await api.post('/auth/login', credentials, { headers })
        if (data.token) {
          this.token = data.token
          sessionStorage.setItem('api_token', data.token)
          this.user = { ...data.user, roles: data.user?.roles ?? [] }
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
        sessionStorage.removeItem('api_token')
        localStorage.removeItem('api_token')
      }
    },

    async verify2FA(code) {
      const { data } = await api.post('/auth/2fa/verify', { otp: code })
      await this.fetchUser()
      return data
    },
  },
})
