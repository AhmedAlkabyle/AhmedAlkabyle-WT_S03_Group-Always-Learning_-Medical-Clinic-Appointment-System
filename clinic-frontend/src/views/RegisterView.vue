<script setup>
import { ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAlertStore } from '../stores/alertStore'
import Icon from '../components/Icon.vue'
import api from '../api/axios'

const router = useRouter()
const alerts = useAlertStore()

const form = ref({ name: '', email: '', phone: '', password: '', confirmPassword: '' })
const errors = ref({})
const loading = ref(false)
const showPwd = ref(false)
const showConfirmPwd = ref(false)

const checkRegEmail = () => {
  if (!form.value.email.trim()) { errors.value.email = 'Email is required'; return false }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)) { errors.value.email = 'Enter a valid email'; return false }
  errors.value.email = ''; return true
}
const checkRegPhone = () => {
  const ph = form.value.phone?.trim() || ''
  if (ph && !/^[+\d][\d\s\-()+]{6,18}$/.test(ph)) {
    errors.value.phone = 'Enter a valid phone number (e.g. +60 12-345 6789)'; return false
  }
  errors.value.phone = ''; return true
}
const onEmailInput = () => { if (errors.value.email) checkRegEmail() }
const onPhoneInput = () => {
  form.value.phone = form.value.phone.replace(/[^\d+\-() ]/g, '')
  if (errors.value.phone) checkRegPhone()
}

const validate = () => {
  if (!form.value.name.trim()) errors.value.name = 'Full name is required'
  else errors.value.name = ''
  const emailOk = checkRegEmail()
  const phoneOk = checkRegPhone()
  if (!form.value.password) errors.value.password = 'Password is required'
  else if (form.value.password.length < 6) errors.value.password = 'Must be at least 6 characters'
  else errors.value.password = ''
  if (form.value.password !== form.value.confirmPassword) errors.value.confirmPassword = 'Passwords do not match'
  else errors.value.confirmPassword = ''
  return !errors.value.name && emailOk && phoneOk && !errors.value.password && !errors.value.confirmPassword
}

const handleRegister = async () => {
  if (!validate()) return
  loading.value = true
  try {
    await api.post('/auth/register', {
      name: form.value.name,
      email: form.value.email,
      password: form.value.password,
      password_confirmation: form.value.confirmPassword,
      role: 'patient',
      phone: form.value.phone || null
    })
    alerts.success('Account created! Please sign in.')
    router.push('/login')
  } catch (e) {
    alerts.error(e?.response?.data?.message || 'Registration failed')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex">

    <!-- LEFT PANEL -->
    <div
      class="hidden lg:flex lg:w-5/12 xl:w-1/2 flex-col justify-between p-10 relative overflow-hidden"
      style="background-image: linear-gradient(160deg, #064E3B 0%, #059669 45%, #2B6CB0 100%)"
    >
      <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-32 -right-32 w-96 h-96 rounded-full bg-white/5 blur-3xl"></div>
        <div class="absolute -bottom-32 -left-32 w-96 h-96 rounded-full bg-white/5 blur-3xl"></div>
      </div>

      <!-- Logo -->
      <div class="relative flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur flex items-center justify-center">
          <Icon name="heart" :size="20" class="text-white" />
        </div>
        <span class="text-white font-bold text-xl tracking-tight">MediCare Clinic</span>
      </div>

      <!-- Content -->
      <div class="relative">
        <div class="inline-flex items-center gap-2 bg-white/15 backdrop-blur px-3 py-1.5 rounded-full text-white/80 text-xs font-medium mb-6">
          <Icon name="user" :size="12" class="text-white" />
          Patient Registration
        </div>
        <h1 class="text-4xl xl:text-5xl font-bold text-white leading-tight mb-4">
          Join MediCare<br />today.
        </h1>
        <p class="text-white/70 text-base leading-relaxed mb-8 max-w-sm">
          Create your patient account and book appointments with our specialists in just a few clicks.
        </p>

        <!-- Benefits -->
        <div class="space-y-4">
          <div v-for="item in [
            { icon: 'calendar', title: 'Easy Booking',       desc: 'Book appointments anytime, anywhere' },
            { icon: 'clock',    title: 'Real-time Updates',  desc: 'Get instant status updates on your visits' },
            { icon: 'check',    title: 'Manage History',     desc: 'View all your past and upcoming appointments' }
          ]" :key="item.title" class="flex items-start gap-3">
            <div class="w-9 h-9 rounded-xl bg-white/15 backdrop-blur flex items-center justify-center flex-shrink-0 mt-0.5">
              <Icon :name="item.icon" :size="16" class="text-white" />
            </div>
            <div>
              <p class="text-white font-semibold text-sm">{{ item.title }}</p>
              <p class="text-white/60 text-xs">{{ item.desc }}</p>
            </div>
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

      <div class="relative text-white/40 text-xs">© {{ new Date().getFullYear() }} MediCare. All rights reserved.</div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="flex-1 flex flex-col justify-center items-center px-6 py-12 bg-gray-50/50">
      <div class="w-full max-w-md">

        <!-- Mobile logo -->
        <div class="lg:hidden flex items-center gap-2 mb-8">
          <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white" style="background-image:linear-gradient(135deg,#059669,#064E3B)">
            <Icon name="heart" :size="18" />
          </div>
          <span class="font-bold text-gray-900 text-lg">MediCare</span>
        </div>

        <!-- Header -->
        <div class="mb-6">
          <div class="inline-flex items-center gap-2 bg-emerald-50 text-emerald-700 text-xs font-semibold px-3 py-1.5 rounded-full border border-emerald-200 mb-3">
            <Icon name="user" :size="12" /> Patient Account
          </div>
          <h2 class="text-3xl font-bold text-gray-900">Create your account</h2>
          <p class="text-gray-500 mt-1">Register as a patient to book appointments.</p>
        </div>

        <!-- Form card -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
          <div class="space-y-4">

            <!-- Name -->
            <div>
              <label class="label">Full Name <span class="text-rose-500">*</span></label>
              <input v-model="form.name" type="text"
                :class="['input', errors.name ? '!border-rose-400 !ring-rose-300/40' : '']"
                placeholder="Jane Doe"
                @input="errors.name = ''" />
              <p v-if="errors.name" class="field-error flex items-center gap-1 mt-1"><Icon name="x" :size="11" /> {{ errors.name }}</p>
            </div>

            <!-- Email -->
            <div>
              <label class="label">Email Address <span class="text-rose-500">*</span></label>
              <input v-model="form.email" type="text"
                :class="['input', errors.email ? '!border-rose-400 !ring-rose-300/40' : '']"
                placeholder="you@example.com"
                autocomplete="email"
                @input="onEmailInput"
                @blur="checkRegEmail()" />
              <p v-if="errors.email" class="field-error flex items-center gap-1 mt-1"><Icon name="x" :size="11" /> {{ errors.email }}</p>
            </div>

            <!-- Phone -->
            <div>
              <label class="label">Phone Number <span class="text-gray-400 text-xs font-normal">(optional)</span></label>
              <input
                v-model="form.phone"
                type="tel"
                :class="['input', errors.phone ? '!border-rose-400 !ring-rose-300/40' : '']"
                placeholder="e.g. +60 12-345 6789"
                autocomplete="tel"
                @input="onPhoneInput"
                @blur="checkRegPhone()"
              />
              <p v-if="errors.phone" class="field-error flex items-center gap-1 mt-1">
                <Icon name="x" :size="11" /> {{ errors.phone }}
              </p>
            </div>

            <!-- Password row -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="label">Password <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <input v-model="form.password"
                    :type="showPwd ? 'text' : 'password'"
                    :class="['input !pr-9', errors.password ? '!border-rose-400' : '']"
                    placeholder="Min. 6 chars"
                    @input="errors.password = ''" />
                  <button type="button" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" @click="showPwd = !showPwd">
                    <Icon :name="showPwd ? 'x' : 'search'" :size="14" />
                  </button>
                </div>
                <p v-if="errors.password" class="field-error text-[11px] mt-1">{{ errors.password }}</p>
              </div>
              <div>
                <label class="label">Confirm <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <input v-model="form.confirmPassword"
                    :type="showConfirmPwd ? 'text' : 'password'"
                    :class="['input !pr-9', errors.confirmPassword ? '!border-rose-400' : (form.confirmPassword && form.confirmPassword === form.password ? '!border-emerald-400' : '')]"
                    placeholder="Repeat"
                    @input="errors.confirmPassword = ''" />
                  <button type="button" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" @click="showConfirmPwd = !showConfirmPwd">
                    <Icon :name="showConfirmPwd ? 'x' : 'search'" :size="14" />
                  </button>
                </div>
                <p v-if="form.confirmPassword && form.confirmPassword === form.password && !errors.confirmPassword" class="text-[11px] text-emerald-600 mt-1 flex items-center gap-1">
                  <Icon name="check" :size="11" /> Match
                </p>
                <p v-if="errors.confirmPassword" class="field-error text-[11px] mt-1">{{ errors.confirmPassword }}</p>
              </div>
            </div>

            <!-- Info note -->
            <div class="bg-emerald-50 border border-emerald-100 rounded-xl px-3 py-2.5 text-xs text-emerald-700 flex items-center gap-2">
              <Icon name="shield" :size="14" class="flex-shrink-0" />
              <span>Registering as a <strong>Patient</strong>. Doctors &amp; admins are added by the clinic.</span>
            </div>

            <!-- Submit -->
            <button
              class="w-full py-3 rounded-xl text-white font-semibold text-sm transition disabled:opacity-60 flex items-center justify-center gap-2"
              style="background-image: linear-gradient(135deg, #059669 0%, #064E3B 100%)"
              :disabled="loading"
              @click="handleRegister"
            >
              <svg v-if="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
              </svg>
              {{ loading ? 'Creating account...' : 'Create Account' }}
            </button>

            <p class="text-center text-sm text-gray-500">
              Already have an account?
              <RouterLink to="/login" class="text-emerald-600 hover:underline font-medium">Sign in</RouterLink>
            </p>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>
