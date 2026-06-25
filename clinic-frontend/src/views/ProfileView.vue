<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../api/axios'
import { useAuthStore } from '../stores/authStore'
import { useAlertStore } from '../stores/alertStore'
import Icon from '../components/Icon.vue'
import Avatar from '../components/Avatar.vue'
import LoadingSpinner from '../components/LoadingSpinner.vue'

const auth = useAuthStore()
const alerts = useAlertStore()

const activeTab = ref('info')
const loading = ref(false)
const saving = ref(false)
const savingPassword = ref(false)

const form = ref({ name: '', email: '', phone: '', address: '', position: '', specialization: '', availability: '' })
const pwdForm = ref({ current_password: '', new_password: '', confirm_password: '' })
const pwdErrors = ref({})
const formErrors = ref({})
const showCurrentPwd = ref(false)
const showNewPwd = ref(false)
const showConfirmPwd = ref(false)

const roleGradient = computed(() => {
  if (auth.role === 'admin') return 'linear-gradient(135deg, #881337 0%, #BE123C 60%, #FB7185 100%)'
  if (auth.role === 'doctor') return 'linear-gradient(135deg, #1A365D 0%, #2B6CB0 60%, #4299E1 100%)'
  return 'linear-gradient(135deg, #064E3B 0%, #059669 60%, #34D399 100%)'
})

const roleSaveBtn = computed(() => {
  if (auth.role === 'admin') return 'linear-gradient(135deg, #BE123C 0%, #881337 100%)'
  if (auth.role === 'doctor') return 'linear-gradient(135deg, #2B6CB0 0%, #1A365D 100%)'
  return 'linear-gradient(135deg, #059669 0%, #064E3B 100%)'
})

const roleTabActive = computed(() => {
  if (auth.role === 'admin') return 'bg-rose-600 text-white shadow-sm'
  if (auth.role === 'doctor') return 'bg-blue-600 text-white shadow-sm'
  return 'bg-emerald-600 text-white shadow-sm'
})

const roleAccentBar = computed(() => {
  if (auth.role === 'admin') return 'from-rose-600 to-pink-400'
  if (auth.role === 'doctor') return 'from-blue-600 to-indigo-400'
  return 'from-emerald-600 to-teal-400'
})

const portalLabel = computed(() => {
  if (auth.role === 'admin') return 'Admin Portal'
  if (auth.role === 'doctor') return 'Doctor Portal'
  return 'Patient Portal'
})

const portalIcon = computed(() => {
  if (auth.role === 'admin') return 'shield'
  if (auth.role === 'doctor') return 'stethoscope'
  return 'user'
})

const pwdStrength = computed(() => {
  const p = pwdForm.value.new_password
  if (!p) return { score: 0, label: '', color: '' }
  let score = 0
  if (p.length >= 6) score++
  if (p.length >= 10) score++
  if (/[A-Z]/.test(p)) score++
  if (/[0-9]/.test(p)) score++
  if (/[^A-Za-z0-9]/.test(p)) score++
  if (score <= 1) return { score, label: 'Weak', color: 'bg-rose-500' }
  if (score <= 3) return { score, label: 'Fair', color: 'bg-amber-400' }
  return { score, label: 'Strong', color: 'bg-emerald-500' }
})

const loadProfile = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/me')
    const u = data.data || data
    form.value = {
      name: u.name || '',
      email: u.email || '',
      phone: u.phone || '',
      address: u.address || '',
      position: u.position || '',
      specialization: u.specialization || u.doctor_profile?.specialization || '',
      availability: u.availability || u.doctor_profile?.availability || ''
    }
  } catch {
    form.value = {
      name: auth.user?.name || '',
      email: auth.user?.email || '',
      phone: '', address: '', position: '', specialization: '', availability: ''
    }
  } finally {
    loading.value = false
  }
}

// ── Individual field validators ───────────────────────────────────────────────
const checkEmail = () => {
  if (!form.value.email.trim()) {
    formErrors.value.email = 'Email is required'; return false
  }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)) {
    formErrors.value.email = 'Enter a valid email'; return false
  }
  formErrors.value.email = ''; return true
}

const checkPhone = () => {
  const ph = form.value.phone?.trim() || ''
  if (ph && !/^[+\d][\d\s\-()+]{6,18}$/.test(ph)) {
    formErrors.value.phone = 'Enter a valid phone number (e.g. +60 12-345 6789)'; return false
  }
  formErrors.value.phone = ''; return true
}

// Re-validate on input only if there is already an error shown (clears as user fixes)
const onEmailInput  = () => { if (formErrors.value.email) checkEmail() }
const onPhoneInput  = () => {
  form.value.phone = form.value.phone.replace(/[^\d+\-() ]/g, '')
  if (formErrors.value.phone) checkPhone()
}

const validateInfo = () => {
  if (!form.value.name.trim()) formErrors.value.name = 'Full name is required'
  else formErrors.value.name = ''
  const emailOk = checkEmail()
  const phoneOk = checkPhone()
  return !formErrors.value.name && emailOk && phoneOk
}

const saveProfile = async (silent = false) => {
  if (!validateInfo()) return
  saving.value = true
  try {
    await api.put('/me', {
      name:           form.value.name,
      email:          form.value.email,
      phone:          form.value.phone || null,
      address:        form.value.address || null,
      position:       form.value.position || null,
      specialization: form.value.specialization || null,
      availability:   form.value.availability || null
    })
    auth.updateUser({ name: form.value.name, email: form.value.email })
    if (!silent) alerts.success('Profile updated successfully')
  } catch (e) {
    alerts.error(e?.response?.data?.message || 'Failed to update profile')
  } finally {
    saving.value = false
  }
}

// Validate on blur and silently auto-save if valid
const onEmailBlur = () => { if (checkEmail()) saveProfile(true) }
const onPhoneBlur = () => { if (checkPhone() && form.value.phone) saveProfile(true) }

const validatePwd = () => {
  const e = {}
  if (!pwdForm.value.current_password) e.current = 'Current password is required'
  if (!pwdForm.value.new_password) e.new = 'New password is required'
  else if (pwdForm.value.new_password.length < 6) e.new = 'Must be at least 6 characters'
  else if (pwdForm.value.current_password && pwdForm.value.new_password === pwdForm.value.current_password) {
    e.new = 'New password must be different from your current password'
  }
  if (pwdForm.value.new_password !== pwdForm.value.confirm_password) e.confirm = 'Passwords do not match'
  pwdErrors.value = e
  return Object.keys(e).length === 0
}

const savePassword = async () => {
  if (!validatePwd()) return
  savingPassword.value = true
  try {
    await api.put('/me/password', {
      current_password: pwdForm.value.current_password,
      new_password: pwdForm.value.new_password
    })
    alerts.success('Password updated successfully')
    pwdForm.value = { current_password: '', new_password: '', confirm_password: '' }
    pwdErrors.value = {}
  } catch (e) {
    alerts.error(e?.response?.data?.error || e?.response?.data?.message || 'Failed to update password')
  } finally {
    savingPassword.value = false
  }
}

onMounted(loadProfile)
</script>

<template>
  <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="h-1 w-full rounded-full mb-6 bg-gradient-to-r" :class="roleAccentBar"></div>

    <div v-if="loading" class="py-12 flex justify-center"><LoadingSpinner size="md" label="Loading profile..." /></div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- LEFT: Profile Card -->
      <div class="lg:col-span-1">
        <div class="rounded-2xl text-white overflow-hidden shadow-lg" :style="{ backgroundImage: roleGradient }">
          <!-- Top section -->
          <div class="p-6 text-center relative">
            <div class="absolute inset-0 opacity-10 flex items-center justify-center">
              <Icon :name="portalIcon" :size="140" :stroke="1" />
            </div>
            <!-- Portal badge -->
            <div class="relative inline-flex items-center gap-1.5 bg-white/20 backdrop-blur text-white text-xs font-semibold px-3 py-1 rounded-full mb-4">
              <Icon :name="portalIcon" :size="12" />
              {{ portalLabel }}
            </div>
            <!-- Avatar initials -->
            <div class="relative inline-block mb-3">
              <div class="w-20 h-20 rounded-full bg-white/20 backdrop-blur flex items-center justify-center text-2xl font-bold ring-4 ring-white/30 mx-auto">
                {{ form.name ? form.name.split(' ').map(p=>p[0]).slice(0,2).join('').toUpperCase() : '?' }}
              </div>
            </div>
            <h2 class="text-xl font-bold">{{ form.name || auth.user?.name }}</h2>
            <p class="text-white/75 text-sm mt-0.5">{{ form.email || auth.user?.email }}</p>
            <!-- Role badge -->
            <div class="inline-flex items-center gap-1.5 bg-white/20 backdrop-blur px-3 py-1 rounded-full text-xs font-semibold capitalize mt-2">
              {{ auth.role }}
            </div>
          </div>

          <!-- Info rows -->
          <div class="bg-black/20 backdrop-blur divide-y divide-white/10">
            <div class="flex items-center justify-between px-4 py-2.5 text-sm">
              <span class="text-white/60 flex items-center gap-2"><Icon name="user" :size="14" /> User ID</span>
              <span class="font-semibold">#{{ auth.user?.id }}</span>
            </div>
            <div v-if="form.phone" class="flex items-center justify-between px-4 py-2.5 text-sm">
              <span class="text-white/60 flex items-center gap-2"><Icon name="clock" :size="14" /> Phone</span>
              <span class="font-semibold">{{ form.phone }}</span>
            </div>
            <div v-if="form.position && auth.role === 'admin'" class="flex items-center justify-between px-4 py-2.5 text-sm">
              <span class="text-white/60 flex items-center gap-2"><Icon name="shield" :size="14" /> Position</span>
              <span class="font-semibold">{{ form.position }}</span>
            </div>
            <div v-if="form.specialization && auth.role === 'doctor'" class="flex items-center justify-between px-4 py-2.5 text-sm">
              <span class="text-white/60 flex items-center gap-2"><Icon name="stethoscope" :size="14" /> Specialty</span>
              <span class="font-semibold">{{ form.specialization }}</span>
            </div>
            <div v-if="form.availability && auth.role === 'doctor'" class="flex items-center justify-between px-4 py-2.5 text-sm">
              <span class="text-white/60 flex items-center gap-2"><Icon name="calendar" :size="14" /> Available</span>
              <span class="font-semibold text-xs text-right">{{ form.availability }}</span>
            </div>
            <div v-if="form.address" class="flex items-center justify-between px-4 py-2.5 text-sm">
              <span class="text-white/60 flex items-center gap-2"><Icon name="dashboard" :size="14" /> Address</span>
              <span class="font-semibold text-xs text-right max-w-[140px] truncate">{{ form.address }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- RIGHT: Form tabs -->
      <div class="lg:col-span-2 space-y-4">
        <!-- Tab switcher -->
        <div class="flex items-center gap-2 bg-gray-100 p-1 rounded-xl w-fit">
          <button
            @click="activeTab = 'info'"
            :class="['px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2', activeTab === 'info' ? roleTabActive : 'text-gray-600 hover:bg-white']"
          >
            <Icon name="user" :size="15" /> Personal Info
          </button>
          <button
            @click="activeTab = 'security'"
            :class="['px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2', activeTab === 'security' ? roleTabActive : 'text-gray-600 hover:bg-white']"
          >
            <Icon name="shield" :size="15" /> Security
          </button>
        </div>

        <!-- Personal Info Tab -->
        <div v-if="activeTab === 'info'" class="card space-y-5">
          <div>
            <h3 class="font-semibold text-gray-900">Personal Information</h3>
            <p class="text-sm text-gray-500">Update your name and contact details.</p>
          </div>

          <!-- Basic info -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="label">Full Name <span class="text-rose-500">*</span></label>
              <input v-model="form.name" type="text" :class="['input', formErrors.name ? '!border-rose-400' : '']" placeholder="Your full name" @input="formErrors.name=''" />
              <p v-if="formErrors.name" class="field-error">{{ formErrors.name }}</p>
            </div>
            <div>
              <label class="label">Email Address <span class="text-rose-500">*</span></label>
              <input
                v-model="form.email"
                type="text"
                :class="['input', formErrors.email ? '!border-rose-400 !ring-rose-300/40' : (form.email && !formErrors.email ? '!border-emerald-400' : '')]"
                placeholder="your@email.com"
                autocomplete="email"
                @input="onEmailInput"
                @blur="onEmailBlur"
              />
              <p v-if="formErrors.email" class="field-error flex items-center gap-1">
                <Icon name="x" :size="11" /> {{ formErrors.email }}
              </p>
              <p v-else-if="form.email && !formErrors.email" class="text-xs text-emerald-600 mt-1 flex items-center gap-1">
                <Icon name="check" :size="11" /> Valid email
              </p>
            </div>
            <div>
              <label class="label">Phone Number <span class="text-gray-400 text-xs">(optional)</span></label>
              <input
                v-model="form.phone"
                type="tel"
                :class="['input', formErrors.phone ? '!border-rose-400 !ring-rose-300/40' : (form.phone && !formErrors.phone ? '!border-emerald-400' : '')]"
                placeholder="e.g. +60 12-345 6789"
                autocomplete="tel"
                @input="onPhoneInput"
                @blur="onPhoneBlur"
              />
              <p v-if="formErrors.phone" class="field-error flex items-center gap-1">
                <Icon name="x" :size="11" /> {{ formErrors.phone }}
              </p>
              <p v-else-if="form.phone && !formErrors.phone" class="text-xs text-emerald-600 mt-1 flex items-center gap-1">
                <Icon name="check" :size="11" /> Looks good
              </p>
            </div>
            <div>
              <label class="label">Address <span class="text-gray-400 text-xs">(optional)</span></label>
              <input v-model="form.address" type="text" class="input" placeholder="Your address" />
            </div>
          </div>

          <!-- Admin-specific fields -->
          <div v-if="auth.role === 'admin'" class="border-t border-rose-100 pt-4">
            <p class="text-xs font-semibold text-rose-600 uppercase tracking-wider mb-3 flex items-center gap-2">
              <Icon name="shield" :size="13" /> Admin Details
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="label">Job Position / Title</label>
                <select v-model="form.position" class="input">
                  <option value="">Select position...</option>
                  <option>Clinic Manager</option>
                  <option>Head Receptionist</option>
                  <option>Receptionist</option>
                  <option>System Administrator</option>
                  <option>Operations Supervisor</option>
                  <option>Medical Records Officer</option>
                  <option>Front Desk Officer</option>
                  <option>Other</option>
                </select>
              </div>
              <div>
                <label class="label">Department</label>
                <input v-model="form.address" type="text" class="input" placeholder="e.g. Front Office, Administration" />
              </div>
            </div>
          </div>

          <!-- Doctor-specific fields -->
          <div v-if="auth.role === 'doctor'" class="border-t border-blue-100 pt-4">
            <p class="text-xs font-semibold text-blue-600 uppercase tracking-wider mb-3 flex items-center gap-2">
              <Icon name="stethoscope" :size="13" /> Doctor Details
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="label">Specialization</label>
                <select v-model="form.specialization" class="input">
                  <option value="">Select specialization...</option>
                  <option>General Practice</option>
                  <option>Cardiology</option>
                  <option>Dermatology</option>
                  <option>Paediatrics</option>
                  <option>Orthopaedics</option>
                  <option>Neurology</option>
                  <option>Gynaecology</option>
                  <option>Ophthalmology</option>
                  <option>ENT (Ear, Nose &amp; Throat)</option>
                  <option>Psychiatry</option>
                  <option>Dental</option>
                  <option>Other</option>
                </select>
              </div>
              <div>
                <label class="label">Availability</label>
                <input v-model="form.availability" type="text" class="input" placeholder="e.g. Mon–Fri, 9am–5pm" />
              </div>
            </div>
          </div>

          <div class="flex justify-end pt-2 border-t border-gray-100">
            <button
              class="btn text-white shadow-sm"
              :style="{ backgroundImage: roleSaveBtn }"
              :disabled="saving"
              @click="saveProfile"
            >
              <Icon name="check" :size="16" />
              {{ saving ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </div>

        <!-- Security Tab -->
        <div v-if="activeTab === 'security'" class="card space-y-5">
          <div>
            <h3 class="font-semibold text-gray-900">Change Password</h3>
            <p class="text-sm text-gray-500">Update your account password. Use a strong password.</p>
          </div>

          <div class="bg-amber-50 border border-amber-100 rounded-xl px-4 py-3 text-sm text-amber-700 flex items-start gap-2">
            <Icon name="shield" :size="16" class="mt-0.5 flex-shrink-0" />
            <span>You will stay logged in after changing your password.</span>
          </div>

          <!-- Current password -->
          <div>
            <label class="label">Current Password <span class="text-rose-500">*</span></label>
            <div class="relative">
              <input v-model="pwdForm.current_password" :type="showCurrentPwd ? 'text' : 'password'"
                :class="['input !pr-10', pwdErrors.current ? '!border-rose-400' : '']"
                placeholder="Enter current password" @input="pwdErrors.current=''" />
              <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" @click="showCurrentPwd = !showCurrentPwd">
                <Icon :name="showCurrentPwd ? 'x' : 'search'" :size="16" />
              </button>
            </div>
            <p v-if="pwdErrors.current" class="field-error">{{ pwdErrors.current }}</p>
          </div>

          <!-- New password -->
          <div>
            <label class="label">New Password <span class="text-rose-500">*</span></label>
            <div class="relative">
              <input v-model="pwdForm.new_password" :type="showNewPwd ? 'text' : 'password'"
                :class="['input !pr-10', pwdErrors.new ? '!border-rose-400' : '']"
                placeholder="Min. 6 characters" @input="pwdErrors.new=''" />
              <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" @click="showNewPwd = !showNewPwd">
                <Icon :name="showNewPwd ? 'x' : 'search'" :size="16" />
              </button>
            </div>
            <!-- Strength bar -->
            <div v-if="pwdForm.new_password" class="mt-2">
              <div class="flex gap-1 mb-1">
                <div v-for="i in 5" :key="i" :class="['h-1.5 flex-1 rounded-full transition', i <= pwdStrength.score ? pwdStrength.color : 'bg-gray-200']"></div>
              </div>
              <p class="text-xs" :class="pwdStrength.score <= 1 ? 'text-rose-500' : pwdStrength.score <= 3 ? 'text-amber-500' : 'text-emerald-600'">
                {{ pwdStrength.label }} password
              </p>
            </div>
            <p v-if="pwdErrors.new" class="field-error">{{ pwdErrors.new }}</p>
          </div>

          <!-- Confirm password -->
          <div>
            <label class="label">Confirm New Password <span class="text-rose-500">*</span></label>
            <div class="relative">
              <input v-model="pwdForm.confirm_password" :type="showConfirmPwd ? 'text' : 'password'"
                :class="['input !pr-10', pwdErrors.confirm ? '!border-rose-400' : (pwdForm.confirm_password && pwdForm.confirm_password === pwdForm.new_password ? '!border-emerald-400' : '')]"
                placeholder="Repeat new password" @input="pwdErrors.confirm=''" />
              <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" @click="showConfirmPwd = !showConfirmPwd">
                <Icon :name="showConfirmPwd ? 'x' : 'search'" :size="16" />
              </button>
            </div>
            <p v-if="pwdForm.confirm_password && pwdForm.confirm_password === pwdForm.new_password && !pwdErrors.confirm" class="text-xs text-emerald-600 mt-1 flex items-center gap-1">
              <Icon name="check" :size="12" /> Passwords match
            </p>
            <p v-if="pwdErrors.confirm" class="field-error">{{ pwdErrors.confirm }}</p>
          </div>

          <div class="flex justify-end pt-2 border-t border-gray-100">
            <button
              class="btn text-white shadow-sm"
              :style="{ backgroundImage: roleSaveBtn }"
              :disabled="savingPassword"
              @click="savePassword"
            >
              <Icon name="shield" :size="16" />
              {{ savingPassword ? 'Updating...' : 'Update Password' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
