<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../../api/axios'
import { useAlertStore } from '../../stores/alertStore'
import Avatar from '../../components/Avatar.vue'
import Icon from '../../components/Icon.vue'
import Modal from '../../components/Modal.vue'
import LoadingSpinner from '../../components/LoadingSpinner.vue'

const alerts = useAlertStore()
const users = ref([])
const loading = ref(false)
const filterRole = ref('all')
const search = ref('')

// ── Delete ──────────────────────────
const showDelete = ref(false)
const deletingUser = ref(null)
const deleting = ref(false)

// ── Add User ─────────────────────────
const showAddUser = ref(false)
const saving = ref(false)
const addStep = ref(1) // 2-step: 1=form, 2=confirm
const addForm = ref({
  name: '',
  email: '',
  password: '',
  confirmPassword: '',
  role: 'doctor',
  phone: '',
  address: '',
  specialization: '',
  availability: '',
  licenseNumber: '',
  position: ''
})
const addErrors = ref({})
const showAddPwd = ref(false)
const showAddConfirmPwd = ref(false)

// ── Edit User ─────────────────────────
const showEdit = ref(false)
const editingUser = ref(null)
const editSaving = ref(false)
const editForm = ref({ name: '', email: '' })
const editErrors = ref({})

// ── Reset Password ────────────────────
const showResetPwd = ref(false)
const resetPwdUser = ref(null)
const resetPwdLoading = ref(false)

// ── Password strength ─────────────────
const pwdStrength = computed(() => {
  const p = addForm.value.password
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

// ── Filters & counts ──────────────────
const filtered = computed(() => {
  let list = filterRole.value === 'all' ? users.value : users.value.filter(u => u.role === filterRole.value)
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter(u => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q))
  }
  return list
})

const counts = computed(() => ({
  all: users.value.length,
  patient: users.value.filter(u => u.role === 'patient').length,
  doctor: users.value.filter(u => u.role === 'doctor').length,
  admin: users.value.filter(u => u.role === 'admin').length
}))

// ── Load ──────────────────────────────
const load = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/users')
    users.value = data.data || data || []
  } catch {
    alerts.error('Failed to load users')
  } finally {
    loading.value = false
  }
}

// ── Add User validation ───────────────
const validateAdd = () => {
  const e = {}
  if (!addForm.value.name.trim()) e.name = 'Full name is required'
  if (!addForm.value.email.trim()) e.email = 'Email is required'
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(addForm.value.email)) e.email = 'Enter a valid email address (any domain allowed)'
  if (!addForm.value.password) e.password = 'Password is required'
  else if (addForm.value.password.length < 6) e.password = 'Password must be at least 6 characters'
  if (addForm.value.password !== addForm.value.confirmPassword) e.confirmPassword = 'Passwords do not match'
  if (addForm.value.phone && !/^[+\d\s\-()]{7,15}$/.test(addForm.value.phone)) e.phone = 'Enter a valid phone number'
  if (addForm.value.role === 'doctor' && !addForm.value.specialization.trim()) e.specialization = 'Specialization is required for doctors'
  addErrors.value = e
  return Object.keys(e).length === 0
}

const openAddUser = () => {
  addForm.value = {
    name: '',
    email: '',
    password: '',
    confirmPassword: '',
    role: 'doctor',
    phone: '',
    address: '',
    specialization: '',
    availability: '',
    licenseNumber: '',
    position: ''
  }
  addErrors.value = {}
  addStep.value = 1
  showAddPwd.value = false
  showAddConfirmPwd.value = false
  showAddUser.value = true
}

const goToConfirm = () => {
  if (validateAdd()) addStep.value = 2
}

const submitAddUser = async () => {
  saving.value = true
  try {
    await api.post('/auth/register', {
      name: addForm.value.name,
      email: addForm.value.email,
      password: addForm.value.password,
      role: addForm.value.role,
      phone: addForm.value.phone || null,
      address: addForm.value.address || null,
      specialization: addForm.value.specialization || null,
      availability: addForm.value.availability || null,
      license_number: addForm.value.licenseNumber || null,
      position: addForm.value.position || null
    })
    alerts.success(`${addForm.value.role === 'doctor' ? 'Doctor' : 'Admin'} account created successfully`)
    showAddUser.value = false
    await load()
  } catch (e) {
    alerts.error(e?.response?.data?.message || 'Failed to create account')
    addStep.value = 1
  } finally {
    saving.value = false
  }
}

// ── Edit User ─────────────────────────
const openEdit = (user) => {
  editingUser.value = user
  editForm.value = { name: user.name, email: user.email }
  editErrors.value = {}
  showEdit.value = true
}

const validateEdit = () => {
  const e = {}
  if (!editForm.value.name.trim()) e.name = 'Full name is required'
  if (!editForm.value.email.trim()) e.email = 'Email is required'
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(editForm.value.email)) e.email = 'Enter a valid email address'
  editErrors.value = e
  return Object.keys(e).length === 0
}

const submitEdit = async () => {
  if (!validateEdit()) return
  editSaving.value = true
  try {
    await api.put(`/users/${editingUser.value.id}`, {
      name: editForm.value.name,
      email: editForm.value.email
    })
    editingUser.value.name = editForm.value.name
    editingUser.value.email = editForm.value.email
    alerts.success('User updated successfully')
    showEdit.value = false
  } catch (e) {
    alerts.error(e?.response?.data?.message || 'Failed to update user')
  } finally {
    editSaving.value = false
  }
}

// ── Reset Password ────────────────────
const openResetPwd = (user) => {
  resetPwdUser.value = user
  showResetPwd.value = true
}

const submitResetPwd = async () => {
  resetPwdLoading.value = true
  try {
    const { data } = await api.post(`/users/${resetPwdUser.value.id}/reset-password`)
    alerts.success(`Password reset. Default password: ${data.default_password}`)
    showResetPwd.value = false
  } catch (err) {
    alerts.error(err?.response?.data?.error || 'Failed to reset password')
  } finally {
    resetPwdLoading.value = false
  }
}

// ── Toggle status ─────────────────────
const toggleStatus = async (user) => {
  try {
    const newStatus = user.is_active === 1 ? 0 : 1
    await api.put(`/users/${user.id}/status`, { is_active: newStatus })
    user.is_active = newStatus
    alerts.success(`User ${newStatus === 1 ? 'activated' : 'deactivated'}`)
  } catch {
    alerts.error('Failed to update status')
  }
}

// ── Delete ────────────────────────────
const confirmDelete = (user) => {
  deletingUser.value = user
  showDelete.value = true
}

const deleteUser = async () => {
  if (!deletingUser.value) return
  deleting.value = true
  try {
    await api.delete(`/users/${deletingUser.value.id}`)
    users.value = users.value.filter(u => u.id !== deletingUser.value.id)
    alerts.success('User deleted')
    showDelete.value = false
  } catch {
    alerts.error('Failed to delete user')
  } finally {
    deleting.value = false
  }
}

// ── Role helpers ──────────────────────
const roleColor = (role) => {
  if (role === 'admin') return 'bg-rose-50 text-rose-700 ring-rose-200'
  if (role === 'doctor') return 'bg-blue-50 text-blue-700 ring-blue-200'
  return 'bg-emerald-50 text-emerald-700 ring-emerald-200'
}

onMounted(load)
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="h-1 w-full rounded-full bg-gradient-to-r from-rose-600 to-pink-400 mb-6"></div>

    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-rose-600 text-white flex items-center justify-center shadow-sm">
          <Icon name="users" :size="22" />
        </div>
        <div>
          <h1 class="text-2xl font-semibold text-gray-900">User Accounts</h1>
          <p class="text-sm text-gray-500">View, activate, deactivate, and delete user accounts.</p>
        </div>
      </div>
      <button
        @click="openAddUser"
        class="btn text-white shadow-sm"
        style="background-image: linear-gradient(135deg, #BE123C 0%, #881337 100%)"
      >
        <Icon name="plus" :size="16" /> Add User
      </button>
    </div>

    <!-- Role filter tabs -->
    <div class="flex items-center gap-2 mb-5 flex-wrap">
      <button
        v-for="tab in ['all','patient','doctor','admin']" :key="tab"
        @click="filterRole = tab"
        :class="[
          'px-4 py-1.5 rounded-full text-sm font-medium transition capitalize',
          filterRole === tab
            ? 'bg-rose-600 text-white shadow-sm'
            : 'bg-white text-gray-600 border border-gray-200 hover:bg-rose-50 hover:text-rose-600'
        ]"
      >
        {{ tab === 'all' ? 'All' : tab.charAt(0).toUpperCase() + tab.slice(1) + 's' }}
        <span class="ml-1 text-xs opacity-70">({{ counts[tab] }})</span>
      </button>
    </div>

    <!-- Search -->
    <div class="relative max-w-sm mb-4">
      <Icon name="search" :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
      <input v-model="search" type="text" class="input !pl-9" placeholder="Search by name or email..." />
    </div>

    <!-- Loading -->
    <div v-if="loading" class="py-12 flex justify-center">
      <LoadingSpinner size="md" label="Loading users..." />
    </div>

    <!-- Table -->
    <div v-else class="table-wrap">
      <div class="px-4 py-3 border-b border-rose-100 bg-rose-50/40 flex items-center justify-between">
        <span class="text-sm font-medium text-rose-800">{{ filtered.length }} user(s)</span>
      </div>
      <table class="tbl">
        <thead>
          <tr>
            <th>User</th>
            <th>Email</th>
            <th>Role</th>
            <th>Position</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="filtered.length === 0">
            <td colspan="5" class="text-center py-10 text-gray-400">No users found.</td>
          </tr>
          <tr v-for="u in filtered" :key="u.id">
            <td>
              <div class="flex items-center gap-3">
                <Avatar :name="u.name" size="sm" />
                <span class="font-medium text-gray-900">{{ u.name }}</span>
              </div>
            </td>
            <td class="text-gray-600">{{ u.email }}</td>
            <td>
              <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ring-1 capitalize', roleColor(u.role)]">
                {{ u.role }}
              </span>
            </td>
            <td class="text-gray-500 text-sm">
              {{ u.position || u.specialization || '—' }}
            </td>
            <td>
              <span :class="[
                'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium',
                u.is_active !== 0 ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-gray-100 text-gray-500 ring-1 ring-gray-200'
              ]">
                <span :class="['w-1.5 h-1.5 rounded-full', u.is_active !== 0 ? 'bg-emerald-500' : 'bg-gray-400']"></span>
                {{ u.is_active !== 0 ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td>
              <div class="flex items-center gap-2">
                <button
                  @click="openEdit(u)"
                  class="p-1.5 rounded-lg text-rose-600 hover:bg-rose-50 transition"
                  title="Edit user"
                >
                  <Icon name="edit" :size="16" />
                </button>
                <button
                  @click="openResetPwd(u)"
                  class="p-1.5 rounded-lg text-amber-600 hover:bg-amber-50 transition"
                  title="Reset password"
                >
                  <Icon name="lock" :size="16" />
                </button>
                <button
                  @click="toggleStatus(u)"
                  :class="[
                    'text-xs px-2.5 py-1 rounded-lg font-medium transition',
                    u.is_active !== 0 ? 'bg-gray-100 text-gray-600 hover:bg-gray-200' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                  ]"
                >
                  {{ u.is_active !== 0 ? 'Deactivate' : 'Activate' }}
                </button>
                <button
                  @click="confirmDelete(u)"
                  class="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 transition"
                  title="Delete user"
                >
                  <Icon name="trash" :size="16" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ═══════════════════════════════════════
         ADD USER MODAL (2-step)
    ════════════════════════════════════════ -->
    <Modal :show="showAddUser" title="Add New User Account" size="md" @close="showAddUser = false">

      <!-- Step indicator -->
      <div class="flex items-center gap-3 mb-5">
        <div class="flex items-center gap-2">
          <div :class="['w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition', addStep === 1 ? 'bg-rose-600 text-white' : 'bg-emerald-500 text-white']">
            <Icon v-if="addStep === 2" name="check" :size="14" />
            <span v-else>1</span>
          </div>
          <span class="text-sm font-medium text-gray-700">Fill Details</span>
        </div>
        <div class="flex-1 h-px bg-gray-200"></div>
        <div class="flex items-center gap-2">
          <div :class="['w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition', addStep === 2 ? 'bg-rose-600 text-white' : 'bg-gray-200 text-gray-500']">2</div>
          <span class="text-sm font-medium" :class="addStep === 2 ? 'text-gray-700' : 'text-gray-400'">Confirm</span>
        </div>
      </div>

      <!-- STEP 1: Form -->
      <div v-if="addStep === 1" class="space-y-4">

        <!-- Info note -->
        <div class="bg-rose-50 border border-rose-100 rounded-xl px-4 py-3 text-sm text-rose-700 flex items-start gap-2">
          <Icon name="shield" :size="16" class="mt-0.5 flex-shrink-0" />
          <span>Only <strong>Doctor</strong> or <strong>Admin</strong> accounts can be created here. Patients register themselves.</span>
        </div>

        <!-- Role selector -->
        <div>
          <label class="label">Select Role <span class="text-rose-500">*</span></label>
          <div class="grid grid-cols-2 gap-3">
            <button type="button" @click="addForm.role = 'doctor'"
              :class="['flex items-center gap-3 p-3 rounded-xl border-2 transition text-left', addForm.role === 'doctor' ? 'border-blue-400 bg-blue-50' : 'border-gray-200 bg-white hover:border-blue-200']">
              <div :class="['w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0', addForm.role === 'doctor' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-500']">
                <Icon name="stethoscope" :size="18" />
              </div>
              <div><p class="font-semibold text-sm text-gray-900">Doctor</p><p class="text-xs text-gray-500">Medical staff</p></div>
              <div v-if="addForm.role === 'doctor'" class="ml-auto w-4 h-4 rounded-full bg-blue-600 flex items-center justify-center">
                <Icon name="check" :size="10" class="text-white" />
              </div>
            </button>
            <button type="button" @click="addForm.role = 'admin'"
              :class="['flex items-center gap-3 p-3 rounded-xl border-2 transition text-left', addForm.role === 'admin' ? 'border-rose-400 bg-rose-50' : 'border-gray-200 bg-white hover:border-rose-200']">
              <div :class="['w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0', addForm.role === 'admin' ? 'bg-rose-600 text-white' : 'bg-gray-100 text-gray-500']">
                <Icon name="shield" :size="18" />
              </div>
              <div><p class="font-semibold text-sm text-gray-900">Admin</p><p class="text-xs text-gray-500">Clinic staff</p></div>
              <div v-if="addForm.role === 'admin'" class="ml-auto w-4 h-4 rounded-full bg-rose-600 flex items-center justify-center">
                <Icon name="check" :size="10" class="text-white" />
              </div>
            </button>
          </div>
        </div>

        <!-- Section: Basic Info -->
        <div class="border-t border-gray-100 pt-4">
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Basic Information</p>
          <div class="space-y-3">

            <!-- Full Name -->
            <div>
              <label class="label">Full Name <span class="text-rose-500">*</span></label>
              <input v-model="addForm.name" type="text"
                :class="['input', addErrors.name ? '!border-rose-400 !ring-rose-300/40' : '']"
                placeholder="e.g. Dr. John Smith"
                @input="addErrors.name = ''" />
              <p v-if="addErrors.name" class="field-error flex items-center gap-1"><Icon name="x" :size="12" /> {{ addErrors.name }}</p>
            </div>

            <!-- Email — any domain -->
            <div>
              <label class="label">Email Address <span class="text-rose-500">*</span></label>
              <input v-model="addForm.email" type="email"
                :class="['input', addErrors.email ? '!border-rose-400 !ring-rose-300/40' : '']"
                placeholder="e.g. john@gmail.com or john@hospital.com"
                @input="addErrors.email = ''" />
              <p class="text-xs text-gray-400 mt-1">Any email domain is accepted.</p>
              <p v-if="addErrors.email" class="field-error flex items-center gap-1"><Icon name="x" :size="12" /> {{ addErrors.email }}</p>
            </div>

            <!-- Phone -->
            <div>
              <label class="label">Phone Number <span class="text-gray-400 text-xs font-normal">(optional)</span></label>
              <input v-model="addForm.phone" type="tel"
                :class="['input', addErrors.phone ? '!border-rose-400 !ring-rose-300/40' : '']"
                placeholder="e.g. +60 12-345 6789"
                @input="addErrors.phone = ''" />
              <p v-if="addErrors.phone" class="field-error flex items-center gap-1"><Icon name="x" :size="12" /> {{ addErrors.phone }}</p>
            </div>

            <!-- Address -->
            <div>
              <label class="label">Address <span class="text-gray-400 text-xs font-normal">(optional)</span></label>
              <input v-model="addForm.address" type="text" class="input"
                placeholder="e.g. 123 Jalan Utama, Johor Bahru" />
            </div>

          </div>
        </div>

        <!-- Section: Medical Details (doctor only) -->
        <div v-if="addForm.role === 'doctor'" class="border-t border-blue-100 pt-4 bg-blue-50/30 rounded-xl p-4 -mx-1">
          <p class="text-xs font-semibold text-blue-600 uppercase tracking-wider mb-3 flex items-center gap-2">
            <Icon name="stethoscope" :size="14" /> Doctor Details
          </p>
          <div class="space-y-3">

            <!-- Specialization -->
            <div>
              <label class="label">Specialization <span class="text-rose-500">*</span></label>
              <select v-model="addForm.specialization"
                :class="['input', addErrors.specialization ? '!border-rose-400 !ring-rose-300/40' : '']"
                @change="addErrors.specialization = ''">
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
              <p v-if="addErrors.specialization" class="field-error flex items-center gap-1"><Icon name="x" :size="12" /> {{ addErrors.specialization }}</p>
            </div>

            <!-- Availability -->
            <div>
              <label class="label">Availability <span class="text-gray-400 text-xs font-normal">(optional)</span></label>
              <input v-model="addForm.availability" type="text" class="input"
                placeholder="e.g. Mon–Fri, 9am–5pm" />
            </div>

            <!-- License Number -->
            <div>
              <label class="label">Medical License Number <span class="text-gray-400 text-xs font-normal">(optional)</span></label>
              <input v-model="addForm.licenseNumber" type="text" class="input"
                placeholder="e.g. MMC 12345" />
            </div>

          </div>
        </div>

        <!-- Section: Admin Details (admin only) -->
        <div v-if="addForm.role === 'admin'" class="border-t border-rose-100 pt-4 bg-rose-50/30 rounded-xl p-4 -mx-1">
          <p class="text-xs font-semibold text-rose-600 uppercase tracking-wider mb-3 flex items-center gap-2">
            <Icon name="shield" :size="14" /> Admin Details
          </p>
          <div class="space-y-3">
            <div>
              <label class="label">Job Position / Title <span class="text-gray-400 text-xs font-normal">(optional)</span></label>
              <select v-model="addForm.position" class="input">
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
              <label class="label">Department <span class="text-gray-400 text-xs font-normal">(optional)</span></label>
              <input v-model="addForm.address" type="text" class="input"
                placeholder="e.g. Front Office, Administration" />
            </div>
          </div>
        </div>

        <!-- Password section -->
        <div class="border-t border-gray-100 pt-4">
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Account Security</p>
          <div class="space-y-3">

            <!-- Password -->
            <div>
              <label class="label">Password <span class="text-rose-500">*</span></label>
              <div class="relative">
                <input v-model="addForm.password"
                  :type="showAddPwd ? 'text' : 'password'"
                  :class="['input !pr-10', addErrors.password ? '!border-rose-400 !ring-rose-300/40' : '']"
                  placeholder="Min. 6 characters"
                  @input="addErrors.password = ''" />
                <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" @click="showAddPwd = !showAddPwd">
                  <Icon :name="showAddPwd ? 'x' : 'search'" :size="16" />
                </button>
              </div>
              <div v-if="addForm.password" class="mt-2">
                <div class="flex gap-1 mb-1">
                  <div v-for="i in 5" :key="i" :class="['h-1 flex-1 rounded-full transition', i <= pwdStrength.score ? pwdStrength.color : 'bg-gray-200']"></div>
                </div>
                <p class="text-xs" :class="pwdStrength.score <= 1 ? 'text-rose-500' : pwdStrength.score <= 3 ? 'text-amber-500' : 'text-emerald-600'">
                  {{ pwdStrength.label }} password
                </p>
              </div>
              <p v-if="addErrors.password" class="field-error flex items-center gap-1"><Icon name="x" :size="12" /> {{ addErrors.password }}</p>
            </div>

            <!-- Confirm Password -->
            <div>
              <label class="label">Confirm Password <span class="text-rose-500">*</span></label>
              <div class="relative">
                <input v-model="addForm.confirmPassword"
                  :type="showAddConfirmPwd ? 'text' : 'password'"
                  :class="['input !pr-10', addErrors.confirmPassword ? '!border-rose-400 !ring-rose-300/40' : (addForm.confirmPassword && addForm.confirmPassword === addForm.password ? '!border-emerald-400' : '')]"
                  placeholder="Repeat password"
                  @input="addErrors.confirmPassword = ''" />
                <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" @click="showAddConfirmPwd = !showAddConfirmPwd">
                  <Icon :name="showAddConfirmPwd ? 'x' : 'search'" :size="16" />
                </button>
              </div>
              <p v-if="addForm.confirmPassword && addForm.confirmPassword === addForm.password && !addErrors.confirmPassword" class="text-xs text-emerald-600 mt-1 flex items-center gap-1">
                <Icon name="check" :size="12" /> Passwords match
              </p>
              <p v-if="addErrors.confirmPassword" class="field-error flex items-center gap-1"><Icon name="x" :size="12" /> {{ addErrors.confirmPassword }}</p>
            </div>

          </div>
        </div>
      </div>

      <!-- STEP 2: Confirmation -->
      <div v-if="addStep === 2" class="space-y-4">
        <div class="bg-gray-50 border border-gray-100 rounded-xl p-4 space-y-3">
          <p class="text-sm font-semibold text-gray-700 mb-2">Please confirm the account details:</p>
          <div class="flex items-center gap-3">
            <Avatar :name="addForm.name" size="md" />
            <div>
              <p class="font-semibold text-gray-900">{{ addForm.name }}</p>
              <p class="text-sm text-gray-500">{{ addForm.email }}</p>
              <p v-if="addForm.phone" class="text-xs text-gray-400">{{ addForm.phone }}</p>
            </div>
          </div>
          <div class="flex flex-wrap items-center gap-2 pt-1">
            <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ring-1 capitalize', roleColor(addForm.role)]">
              {{ addForm.role }}
            </span>
            <span v-if="addForm.specialization" class="chip">{{ addForm.specialization }}</span>
            <span v-if="addForm.availability" class="chip">{{ addForm.availability }}</span>
            <span v-if="addForm.licenseNumber" class="chip">{{ addForm.licenseNumber }}</span>
            <span v-if="addForm.position" class="chip">{{ addForm.position }}</span>
          </div>
          <p v-if="addForm.address" class="text-xs text-gray-500 flex items-center gap-1">
            <Icon name="user" :size="12" /> {{ addForm.address }}
          </p>
        </div>
        <div class="bg-amber-50 border border-amber-100 rounded-xl px-4 py-3 text-sm text-amber-700 flex items-start gap-2">
          <Icon name="clock" :size="16" class="mt-0.5 flex-shrink-0" />
          <span>Make sure all details are correct before creating the account.</span>
        </div>
      </div>

      <template #footer>
        <button class="btn-secondary" @click="addStep === 2 ? addStep = 1 : showAddUser = false">
          {{ addStep === 2 ? '← Back' : 'Cancel' }}
        </button>
        <button
          v-if="addStep === 1"
          class="btn text-white shadow-sm"
          style="background-image: linear-gradient(135deg, #BE123C 0%, #881337 100%)"
          @click="goToConfirm"
        >
          Next: Review →
        </button>
        <button
          v-if="addStep === 2"
          class="btn text-white shadow-sm"
          style="background-image: linear-gradient(135deg, #BE123C 0%, #881337 100%)"
          :disabled="saving"
          @click="submitAddUser"
        >
          {{ saving ? 'Creating...' : '✓ Create Account' }}
        </button>
      </template>
    </Modal>

    <!-- ═══════════════════════════════════════
         EDIT USER MODAL
    ════════════════════════════════════════ -->
    <Modal :show="showEdit" title="Edit User Account" size="sm" @close="showEdit = false">
      <div class="space-y-4">

        <!-- User preview -->
        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl border border-gray-100">
          <Avatar :name="editingUser?.name" size="md" />
          <div>
            <p class="font-semibold text-gray-900 text-sm">{{ editingUser?.name }}</p>
            <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ring-1 capitalize mt-0.5', roleColor(editingUser?.role)]">
              {{ editingUser?.role }}
            </span>
          </div>
        </div>

        <!-- Name -->
        <div>
          <label class="label">Full Name <span class="text-rose-500">*</span></label>
          <input
            v-model="editForm.name"
            type="text"
            :class="['input', editErrors.name ? '!border-rose-400 !ring-rose-300/40' : '']"
            placeholder="Full name"
            @input="editErrors.name = ''"
          />
          <p v-if="editErrors.name" class="field-error flex items-center gap-1">
            <Icon name="x" :size="12" /> {{ editErrors.name }}
          </p>
        </div>

        <!-- Email -->
        <div>
          <label class="label">Email Address <span class="text-rose-500">*</span></label>
          <input
            v-model="editForm.email"
            type="email"
            :class="['input', editErrors.email ? '!border-rose-400 !ring-rose-300/40' : '']"
            placeholder="Email address"
            @input="editErrors.email = ''"
          />
          <p v-if="editErrors.email" class="field-error flex items-center gap-1">
            <Icon name="x" :size="12" /> {{ editErrors.email }}
          </p>
        </div>

        <div class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-3 text-sm text-blue-700 flex items-start gap-2">
          <Icon name="shield" :size="16" class="mt-0.5 flex-shrink-0" />
          <span>Role cannot be changed after account creation.</span>
        </div>
      </div>

      <template #footer>
        <button class="btn-secondary" @click="showEdit = false">Cancel</button>
        <button
          class="btn text-white shadow-sm"
          style="background-image: linear-gradient(135deg, #BE123C 0%, #881337 100%)"
          :disabled="editSaving"
          @click="submitEdit"
        >
          {{ editSaving ? 'Saving...' : '✓ Save Changes' }}
        </button>
      </template>
    </Modal>

    <!-- ═══════════════════════════════════════
         RESET PASSWORD MODAL
    ════════════════════════════════════════ -->
    <Modal :show="showResetPwd" title="Reset User Password" size="sm" @close="showResetPwd = false">
      <div class="space-y-4">

        <!-- User preview -->
        <div class="flex items-center gap-3 p-3 bg-amber-50 rounded-xl border border-amber-100">
          <Avatar :name="resetPwdUser?.name" size="md" />
          <div>
            <p class="font-semibold text-gray-900 text-sm">{{ resetPwdUser?.name }}</p>
            <p class="text-xs text-gray-500">{{ resetPwdUser?.email }}</p>
            <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ring-1 capitalize mt-1', roleColor(resetPwdUser?.role)]">
              {{ resetPwdUser?.role }}
            </span>
          </div>
        </div>

        <div class="bg-rose-50 border border-rose-100 rounded-xl px-4 py-3 text-sm text-rose-700 flex items-start gap-2">
          <Icon name="key" :size="16" class="mt-0.5 flex-shrink-0" />
          <div>
            <p class="font-semibold mb-1">Reset to default password?</p>
            <p>This user's password will be set to the clinic default:</p>
            <p class="mt-1.5 font-mono font-bold tracking-wide bg-rose-100 rounded-lg px-3 py-1.5 text-rose-800 text-center text-base">ILoveMediCareClinic</p>
            <p class="mt-2 text-rose-600/80">They can change it from their profile after logging in.</p>
          </div>
        </div>

      </div>
      <template #footer>
        <button class="btn-secondary" @click="showResetPwd = false">Cancel</button>
        <button
          class="btn text-white shadow-sm"
          style="background-image: linear-gradient(135deg, #BE123C 0%, #881337 100%)"
          :disabled="resetPwdLoading"
          @click="submitResetPwd"
        >
          <Icon name="key" :size="15" />
          {{ resetPwdLoading ? 'Resetting...' : 'Reset to Default' }}
        </button>
      </template>
    </Modal>

    <!-- ═══════════════════════════════════════
         DELETE CONFIRMATION MODAL
    ════════════════════════════════════════ -->
    <Modal :show="showDelete" title="Delete User" size="sm" @close="showDelete = false">
      <div class="space-y-3">
        <div class="flex items-center gap-3 p-3 bg-rose-50 rounded-xl border border-rose-100">
          <Avatar :name="deletingUser?.name" size="md" />
          <div>
            <p class="font-semibold text-gray-900">{{ deletingUser?.name }}</p>
            <p class="text-sm text-gray-500">{{ deletingUser?.email }}</p>
          </div>
        </div>
        <p class="text-sm text-gray-600">
          Are you sure you want to permanently delete this account? This action <strong class="text-rose-600">cannot be undone</strong>.
        </p>
      </div>
      <template #footer>
        <button class="btn-secondary" @click="showDelete = false">Cancel</button>
        <button class="btn-danger" :disabled="deleting" @click="deleteUser">
          {{ deleting ? 'Deleting...' : 'Delete Account' }}
        </button>
      </template>
    </Modal>

  </div>
</template>
