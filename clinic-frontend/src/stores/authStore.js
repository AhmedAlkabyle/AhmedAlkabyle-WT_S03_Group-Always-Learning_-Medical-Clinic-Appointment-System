import { defineStore } from 'pinia'
import { jwtDecode } from 'jwt-decode'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: null,
    user: null
  }),
  getters: {
    isAuthenticated: (s) => !!s.token,
    role: (s) => s.user?.role || null
  },
  actions: {
    hydrate() {
      const token = localStorage.getItem('token')
      const userJson = localStorage.getItem('user')
      if (token) this.token = token
      if (userJson) {
        try { this.user = JSON.parse(userJson) } catch { this.user = null }
      }
      // Fallback: derive from JWT payload if user missing
      if (this.token && !this.user) {
        try {
          const payload = jwtDecode(this.token)
          this.user = {
            id: payload.id || payload.sub,
            name: payload.name || payload.email || 'User',
            email: payload.email,
            role: payload.role
          }
          localStorage.setItem('user', JSON.stringify(this.user))
        } catch { /* ignore */ }
      }
    },
    login(token, user) {
      this.token = token
      // If user not provided, decode from token
      if (!user && token) {
        try {
          const payload = jwtDecode(token)
          user = {
            id: payload.id || payload.sub,
            name: payload.name || payload.email,
            email: payload.email,
            role: payload.role
          }
        } catch { /* noop */ }
      }
      this.user = user || null
      localStorage.setItem('token', token)
      if (this.user) localStorage.setItem('user', JSON.stringify(this.user))
    },
    logout() {
      this.token = null
      this.user = null
      localStorage.removeItem('token')
      localStorage.removeItem('user')
    },
    updateUser(patch) {
      if (!this.user) return
      this.user = { ...this.user, ...patch }
      localStorage.setItem('user', JSON.stringify(this.user))
    },
    getRole() { return this.user?.role || null }
  }
})
