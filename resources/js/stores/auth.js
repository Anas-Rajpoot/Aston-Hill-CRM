import { defineStore } from 'pinia'
import axios from '@/lib/axios'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    loading: false,
  }),

  getters: {
    isAuthenticated: (state) => !!state.user,
  },

  actions: {
    async fetchUser() {
      try {
        // Ensure CSRF cookie is set first (required for SPA auth)
        await axios.get('/sanctum/csrf-cookie')
        const { data } = await axios.get('/me') // /api/me due to baseURL in axios.js
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

    async login(credentials) {
      this.loading = true
      try {
        await axios.get('/sanctum/csrf-cookie') // CSRF cookie
        await axios.post('/login', credentials) // login via Breeze
        await this.fetchUser()                  // fetch authenticated user
      } finally {
        this.loading = false
      }
    },

    async logout() {
      try {
        await axios.post('/logout') // logout via Breeze
      } finally {
        this.user = null
      }
    },
  },
})
