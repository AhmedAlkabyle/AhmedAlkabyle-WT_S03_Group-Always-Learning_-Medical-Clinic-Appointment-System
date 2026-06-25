import axios from 'axios'
import { mockHandle } from './mock'

const USE_MOCK = String(import.meta.env.VITE_USE_MOCK).toLowerCase() === 'true'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' }
})

if (USE_MOCK) {
  // Replace axios's HTTP adapter with our in-browser mock router.
  api.defaults.adapter = (config) => mockHandle(config)
  // eslint-disable-next-line no-console
  console.info('[clinic] Mock API enabled. Demo accounts: patient@clinic.test / doctor@clinic.test / admin@clinic.test (password: "password")')
}

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err?.response?.status === 401) {
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      if (typeof window !== 'undefined' && window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
    }
    return Promise.reject(err)
  }
)

export default api
