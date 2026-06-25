import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/authStore'

const routes = [
  { path: '/', redirect: () => {
      const token = localStorage.getItem('token')
      const user = JSON.parse(localStorage.getItem('user') || 'null')
      if (!token) return '/login'
      if (user?.role === 'doctor') return '/schedule'
      if (user?.role === 'admin') return '/admin'
      return '/dashboard'
    }
  },
  { path: '/login', name: 'login', component: () => import('../views/LoginView.vue'), meta: { public: true } },
  { path: '/register', name: 'register', component: () => import('../views/RegisterView.vue'), meta: { public: true } },
  { path: '/forgot-password', name: 'forgot-password', component: () => import('../views/ForgotPasswordView.vue'), meta: { public: true } },

  // Profile (any authenticated role)
  { path: '/profile', component: () => import('../views/ProfileView.vue') },

  // Patient
  { path: '/dashboard', component: () => import('../views/patient/DashboardView.vue'), meta: { roles: ['patient'] } },
  { path: '/book', component: () => import('../views/patient/BookAppointmentView.vue'), meta: { roles: ['patient'] } },
  { path: '/appointments', component: () => import('../views/patient/MyAppointmentsView.vue'), meta: { roles: ['patient'] } },

  // Doctor
  { path: '/schedule', component: () => import('../views/doctor/ScheduleView.vue'), meta: { roles: ['doctor'] } },
  { path: '/off-days', component: () => import('../views/doctor/OffDaysView.vue'), meta: { roles: ['doctor'] } },

  // Admin
  { path: '/admin', component: () => import('../views/admin/AdminDashboardView.vue'), meta: { roles: ['admin'] } },
  { path: '/admin/doctors', component: () => import('../views/admin/DoctorManagementView.vue'), meta: { roles: ['admin'] } },
  { path: '/admin/appointments', component: () => import('../views/admin/AppointmentManagementView.vue'), meta: { roles: ['admin'] } },
  { path: '/admin/off-days', component: () => import('../views/admin/OffDayManagementView.vue'), meta: { roles: ['admin'] } },
  { path: '/admin/users', component: () => import('../views/admin/UserManagementView.vue'), meta: { roles: ['admin'] } },

  // Catch-all
  { path: '/:pathMatch(.*)*', redirect: '/login' }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (!auth.token) auth.hydrate()

  if (to.meta.public) {
    // logged-in users should not see /login or /register
    if (auth.isAuthenticated && (to.path === '/login' || to.path === '/register')) {
      const role = auth.role
      if (role === 'doctor') return '/schedule'
      if (role === 'admin') return '/admin'
      return '/dashboard'
    }
    return true
  }

  if (!auth.isAuthenticated) return '/login'

  if (to.meta.roles && !to.meta.roles.includes(auth.role)) {
    // wrong role → send to their home
    if (auth.role === 'doctor') return '/schedule'
    if (auth.role === 'admin') return '/admin'
    if (auth.role === 'patient') return '/dashboard'
    return '/login'
  }

  return true
})

export default router
