<script setup>
import { ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
import { useAlertStore } from '../stores/alertStore'
import Icon from '../components/Icon.vue'
import api from '../api/axios'

const router = useRouter()
const auth = useAuthStore()
const alerts = useAlertStore()

const form = ref({ email: '', password: '' })
const errors = ref({})
const loading = ref(false)
const showPwd = ref(false)

const validate = () => {
  const e = {}
  if (!form.value.email.trim()) e.email = 'Email is required'
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)) e.email = 'Enter a valid email'
  if (!form.value.password) e.password = 'Password is required'
  errors.value = e
  return Object.keys(e).length === 0
}

const handleLogin = async () => {
  if (!validate()) return
  loading.value = true
  try {
    const { data } = await api.post('/auth/login', form.value)
    const token = data.token || data.access_token || data.data?.token
    const user = data.user || data.data?.user
    if (!token) throw new Error('Invalid response: missing token')
    auth.login(token, user)
    alerts.success(`Welcome back, ${auth.user?.name || ''}!`)
    const role = auth.role
    if (role === 'admin') router.push('/admin')
    else if (role === 'doctor') router.push('/schedule')
    else router.push('/dashboard')
  } catch (e) {
    alerts.error(e?.response?.data?.message || 'Invalid email or password')
  } finally {
    loading.value = false
  }
}

const features = [
  { icon: 'user', text: 'Effortless scheduling for patients' },
  { icon: 'stethoscope', text: 'Live calendar view for doctors' },
  { icon: 'shield', text: 'Powerful admin controls' }
]


</script>

<template>
  <div class="min-h-screen flex">

    <!-- LEFT PANEL -->
    <div
      class="hidden lg:flex lg:w-5/12 xl:w-1/2 flex-col justify-between p-10 relative overflow-hidden"
      style="background-image: linear-gradient(160deg, #1A365D 0%, #2B6CB0 40%, #065F46 100%)"
    >
      <!-- Background decoration -->
      <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-32 -left-32 w-96 h-96 rounded-full bg-white/5 blur-3xl"></div>
        <div class="absolute -bottom-32 -right-32 w-96 h-96 rounded-full bg-white/5 blur-3xl"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] rounded-full bg-white/[0.03] blur-3xl"></div>
      </div>

      <!-- Logo -->
      <div class="relative flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur flex items-center justify-center">
          <Icon name="heart" :size="20" class="text-white" />
        </div>
        <span class="text-white font-bold text-xl tracking-tight">MediCare Clinic</span>
      </div>

      <!-- Center content -->
      <div class="relative">
        <div class="inline-flex items-center gap-2 bg-white/15 backdrop-blur px-3 py-1.5 rounded-full text-white/80 text-xs font-medium mb-6">
          <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
          System Online · Mock Mode
        </div>
        <h1 class="text-4xl xl:text-5xl font-bold text-white leading-tight mb-4">
          Better appointments,<br />better care.
        </h1>
        <p class="text-white/70 text-base leading-relaxed mb-8 max-w-sm">
          Book visits, manage your schedule and stay on top of patient care — all in one clean, modern dashboard built for clinics.
        </p>
        <div class="space-y-3">
          <div v-for="f in features" :key="f.text" class="flex items-center gap-3">
            <div class="w-7 h-7 rounded-lg bg-white/15 backdrop-blur flex items-center justify-center flex-shrink-0">
              <Icon :name="f.icon" :size="14" class="text-white" />
            </div>
            <span class="text-white/80 text-sm">{{ f.text }}</span>
          </div>
        </div>

        <!-- Role pills -->
        <div class="flex flex-wrap gap-2 mt-8">
          <span class="inline-flex items-center gap-1.5 bg-white/10 backdrop-blur text-white text-xs font-medium px-3 py-1.5 rounded-full border border-white/20">
            <span class="w-2 h-2 rounded-full bg-emerald-400"></span> Patient
          </span>
          <span class="inline-flex items-center gap-1.5 bg-white/10 backdrop-blur text-white text-xs font-medium px-3 py-1.5 rounded-full border border-white/20">
            <span class="w-2 h-2 rounded-full bg-blue-300"></span> Doctor
          </span>
          <span class="inline-flex items-center gap-1.5 bg-white/10 backdrop-blur text-white text-xs font-medium px-3 py-1.5 rounded-full border border-white/20">
            <span class="w-2 h-2 rounded-full bg-rose-400"></span> Admin
          </span>
        </div>
      </div>

      <!-- Bottom copyright -->
      <div class="relative text-white/40 text-xs">© {{ new Date().getFullYear() }} MediCare. All rights reserved.</div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="flex-1 flex flex-col justify-center items-center px-6 py-12 bg-gray-50/50">
      <div class="w-full max-w-md">

        <!-- Mobile logo -->
        <div class="lg:hidden flex items-center gap-2 mb-8">
          <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white" style="background-image:linear-gradient(135deg,#2B6CB0,#1A365D)">
            <Icon name="heart" :size="18" />
          </div>
          <span class="font-bold text-gray-900 text-lg">MediCare</span>
        </div>

        <!-- Header -->
        <div class="mb-8">
          <h2 class="text-3xl font-bold text-gray-900">Welcome back</h2>
          <p class="text-gray-500 mt-1">Sign in to continue to your dashboard.</p>
        </div>

        <!-- Form card -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
          <div class="space-y-4">

            <!-- Email -->
            <div>
              <label class="label">Email Address</label>
              <input
                v-model="form.email"
                type="email"
                :class="['input', errors.email ? '!border-rose-400 !ring-rose-300/40' : '']"
                placeholder="you@example.com"
                autocomplete="email"
                @keyup.enter="handleLogin"
                @input="errors.email = ''"
              />
              <p v-if="errors.email" class="field-error flex items-center gap-1 mt-1">
                <Icon name="x" :size="11" /> {{ errors.email }}
              </p>
            </div>

            <!-- Password -->
            <div>
              <label class="label">Password</label>
              <div class="relative">
                <input
                  v-model="form.password"
                  :type="showPwd ? 'text' : 'password'"
                  :class="['input !pr-10', errors.password ? '!border-rose-400 !ring-rose-300/40' : '']"
                  placeholder="••••••••"
                  autocomplete="current-password"
                  @keyup.enter="handleLogin"
                  @input="errors.password = ''"
                />
                <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" @click="showPwd = !showPwd">
                  <Icon :name="showPwd ? 'x' : 'search'" :size="16" />
                </button>
              </div>
              <p v-if="errors.password" class="field-error flex items-center gap-1 mt-1">
                <Icon name="x" :size="11" /> {{ errors.password }}
              </p>
            </div>

            <!-- Forgot password -->
            <div class="flex justify-end -mb-1">
              <RouterLink to="/forgot-password" class="text-xs text-blue-600 hover:underline">Forgot password?</RouterLink>
            </div>

            <!-- Submit -->
            <button
              class="w-full py-3 rounded-xl text-white font-semibold text-sm transition disabled:opacity-60 disabled:cursor-not-allowed mt-2 flex items-center justify-center gap-2"
              style="background-image: linear-gradient(135deg, #2B6CB0 0%, #1A365D 100%)"
              :disabled="loading"
              @click="handleLogin"
            >
              <svg v-if="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
              </svg>
              {{ loading ? 'Signing in...' : 'Sign In' }}
            </button>

            <p class="text-center text-sm text-gray-500">
              Don't have an account?
              <RouterLink to="/register" class="text-blue-600 hover:underline font-medium">Create one</RouterLink>
            </p>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>
