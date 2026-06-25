<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, RouterLink, useRoute } from 'vue-router'
import { useAlertStore } from '../stores/alertStore'
import Icon from '../components/Icon.vue'
import api from '../api/axios'

const router = useRouter()
const route  = useRoute()
const alerts = useAlertStore()

const token   = ref('')
const form    = ref({ new_password: '', confirm: '' })
const errors  = ref({})
const loading = ref(false)
const showPwd = ref(false)

onMounted(() => {
  token.value = route.query.token || ''
  if (!token.value) {
    alerts.error('Invalid or missing reset link')
    router.push('/forgot-password')
  }
})

const validate = () => {
  const e = {}
  if (!form.value.new_password) e.new_password = 'Password is required'
  else if (form.value.new_password.length < 6) e.new_password = 'Must be at least 6 characters'
  if (form.value.new_password !== form.value.confirm) e.confirm = 'Passwords do not match'
  errors.value = e
  return Object.keys(e).length === 0
}

const handleReset = async () => {
  if (!validate()) return
  loading.value = true
  try {
    await api.post('/auth/reset-password', {
      token: token.value,
      new_password: form.value.new_password
    })
    alerts.success('Password reset successfully! Please sign in.')
    router.push('/login')
  } catch (e) {
    alerts.error(e?.response?.data?.error || 'Failed to reset password')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50/50 px-6">
    <div class="w-full max-w-md">

      <!-- Logo -->
      <div class="flex items-center gap-2 mb-8 justify-center">
        <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white" style="background-image:linear-gradient(135deg,#2B6CB0,#1A365D)">
          <Icon name="heart" :size="18" />
        </div>
        <span class="font-bold text-gray-900 text-lg">MediCare</span>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <div class="mb-5">
          <h2 class="text-2xl font-bold text-gray-900">Set new password</h2>
          <p class="text-gray-500 mt-1 text-sm">Enter your new password below.</p>
        </div>
        <div class="space-y-4">

          <!-- New Password -->
          <div>
            <label class="label">New Password <span class="text-rose-500">*</span></label>
            <div class="relative">
              <input
                v-model="form.new_password"
                :type="showPwd ? 'text' : 'password'"
                :class="['input !pr-9', errors.new_password ? '!border-rose-400 !ring-rose-300/40' : '']"
                placeholder="Min. 6 characters"
                @input="errors.new_password = ''"
              />
              <button type="button" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" @click="showPwd = !showPwd">
                <Icon :name="showPwd ? 'x' : 'search'" :size="14" />
              </button>
            </div>
            <p v-if="errors.new_password" class="field-error flex items-center gap-1 mt-1">
              <Icon name="x" :size="11" /> {{ errors.new_password }}
            </p>
          </div>

          <!-- Confirm Password -->
          <div>
            <label class="label">Confirm Password <span class="text-rose-500">*</span></label>
            <input
              v-model="form.confirm"
              type="password"
              :class="['input', errors.confirm ? '!border-rose-400 !ring-rose-300/40' : (form.confirm && form.confirm === form.new_password ? '!border-emerald-400' : '')]"
              placeholder="Repeat password"
              @input="errors.confirm = ''"
              @keyup.enter="handleReset"
            />
            <p v-if="form.confirm && form.confirm === form.new_password && !errors.confirm" class="text-[11px] text-emerald-600 mt-1 flex items-center gap-1">
              <Icon name="check" :size="11" /> Match
            </p>
            <p v-if="errors.confirm" class="field-error flex items-center gap-1 mt-1">
              <Icon name="x" :size="11" /> {{ errors.confirm }}
            </p>
          </div>

          <button
            class="w-full py-3 rounded-xl text-white font-semibold text-sm transition disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            style="background-image: linear-gradient(135deg, #2B6CB0 0%, #1A365D 100%)"
            :disabled="loading"
            @click="handleReset"
          >
            <svg v-if="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            {{ loading ? 'Resetting...' : 'Reset Password' }}
          </button>

          <p class="text-center text-sm text-gray-500">
            <RouterLink to="/login" class="text-blue-600 hover:underline font-medium">Back to Sign In</RouterLink>
          </p>
        </div>
      </div>

    </div>
  </div>
</template>
