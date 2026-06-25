<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../../api/axios'
import { useAlertStore } from '../../stores/alertStore'
import Avatar from '../../components/Avatar.vue'
import Icon from '../../components/Icon.vue'
import Modal from '../../components/Modal.vue'
import LoadingSpinner from '../../components/LoadingSpinner.vue'

const alerts = useAlertStore()
const doctors = ref([])
const users = ref([])
const loading = ref(false)
const search = ref('')

const showAdd = ref(false)
const showEdit = ref(false)
const showDelete = ref(false)
const saving = ref(false)
const deletingDoc = ref(null)
const deleting = ref(false)
const editingDoc = ref(null)

const form = ref({ user_id: '', specialization: '', availability: '' })
const formErrors = ref({})

const filtered = computed(() => {
  if (!search.value.trim()) return doctors.value
  const q = search.value.toLowerCase()
  return doctors.value.filter(d =>
    (d.name || '').toLowerCase().includes(q) ||
    (d.email || '').toLowerCase().includes(q) ||
    (d.specialization || '').toLowerCase().includes(q)
  )
})

const availableDoctorUsers = computed(() =>
  users.value.filter(u => u.role === 'doctor' && !doctors.value.some(d => d.user_id === u.id))
)

const load = async () => {
  loading.value = true
  try {
    const [{ data: d }, { data: u }] = await Promise.all([
      api.get('/doctors'),
      api.get('/users').catch(() => ({ data: [] }))
    ])
    doctors.value = d.data || d || []
    users.value = u.data || u || []
  } catch {
    alerts.error('Failed to load doctors')
  } finally {
    loading.value = false
  }
}

const validateForm = () => {
  const e = {}
  if (!form.value.specialization.trim()) e.specialization = 'Specialization is required'
  formErrors.value = e
  return Object.keys(e).length === 0
}

const openAdd = () => {
  form.value = { user_id: '', specialization: '', availability: '' }
  formErrors.value = {}
  showAdd.value = true
}

const submitAdd = async () => {
  if (!validateForm()) return
  saving.value = true
  try {
    const { data } = await api.post('/doctors', form.value)
    doctors.value.push(data.data || data)
    alerts.success('Doctor added successfully')
    showAdd.value = false
  } catch (e) {
    alerts.error(e?.response?.data?.message || 'Failed to add doctor')
  } finally {
    saving.value = false
  }
}

const openEdit = (doc) => {
  editingDoc.value = doc
  form.value = { specialization: doc.specialization || '', availability: doc.availability || '' }
  formErrors.value = {}
  showEdit.value = true
}

const submitEdit = async () => {
  if (!validateForm()) return
  saving.value = true
  try {
    await api.put(`/doctors/${editingDoc.value.id}`, form.value)
    editingDoc.value.specialization = form.value.specialization
    editingDoc.value.availability = form.value.availability
    alerts.success('Doctor updated')
    showEdit.value = false
  } catch (e) {
    alerts.error(e?.response?.data?.message || 'Failed to update')
  } finally {
    saving.value = false
  }
}

const confirmDelete = (doc) => {
  deletingDoc.value = doc
  showDelete.value = true
}

const deleteDoc = async () => {
  deleting.value = true
  try {
    await api.delete(`/doctors/${deletingDoc.value.id}`)
    doctors.value = doctors.value.filter(d => d.id !== deletingDoc.value.id)
    alerts.success('Doctor removed')
    showDelete.value = false
  } catch (e) {
    alerts.error(e?.response?.data?.message || 'Failed to delete')
  } finally {
    deleting.value = false
  }
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
          <Icon name="stethoscope" :size="22" />
        </div>
        <div>
          <h1 class="text-2xl font-semibold text-gray-900">Doctors</h1>
          <p class="text-sm text-gray-500">Manage all doctors registered in the clinic.</p>
        </div>
      </div>
      <button @click="openAdd" class="btn text-white shadow-sm" style="background-image: linear-gradient(135deg, #BE123C 0%, #881337 100%)">
        <Icon name="plus" :size="16" /> Add Doctor
      </button>
    </div>

    <!-- Search + stats bar -->
    <div class="flex items-center gap-3 mb-4 flex-wrap">
      <div class="relative flex-1 min-w-[200px] max-w-sm">
        <Icon name="search" :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
        <input v-model="search" type="text" class="input !pl-9" placeholder="Search by name, email or specialization..." />
      </div>
      <div class="flex items-center gap-2 ml-auto">
        <span class="text-sm text-gray-500">{{ filtered.length }} of {{ doctors.length }} doctor(s)</span>
      </div>
    </div>

    <div v-if="loading" class="py-12 flex justify-center"><LoadingSpinner size="md" label="Loading doctors..." /></div>

    <div v-else class="table-wrap">
      <div class="px-4 py-3 border-b border-rose-100 bg-rose-50/40 flex items-center gap-4">
        <span class="text-sm font-medium text-rose-800">{{ doctors.length }} doctor(s) registered</span>
        <div class="flex items-center gap-2 ml-auto flex-wrap">
          <span v-for="spec in [...new Set(doctors.map(d => d.specialization).filter(Boolean))].slice(0,4)" :key="spec"
            class="chip !bg-rose-50 !text-rose-600 !ring-rose-200 text-xs">{{ spec }}</span>
        </div>
      </div>

      <div v-if="filtered.length === 0" class="py-16 text-center">
        <div class="w-14 h-14 mx-auto rounded-xl bg-rose-50 text-rose-400 flex items-center justify-center mb-3">
          <Icon name="stethoscope" :size="26" />
        </div>
        <p class="font-medium text-gray-700">No doctors found</p>
        <p class="text-sm text-gray-400 mt-1">{{ search ? 'Try a different search term' : 'Add your first doctor to get started' }}</p>
      </div>

      <table v-else class="tbl">
        <thead>
          <tr>
            <th>Doctor</th>
            <th>Email</th>
            <th>Specialization</th>
            <th>Availability</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="d in filtered" :key="d.id" class="group">
            <td>
              <div class="flex items-center gap-3">
                <Avatar :name="d.name" size="sm" />
                <div>
                  <p class="font-medium text-gray-900">Dr. {{ d.name }}</p>
                  <p class="text-xs text-gray-400">ID #{{ d.id }}</p>
                </div>
              </div>
            </td>
            <td class="text-gray-600">{{ d.email }}</td>
            <td>
              <span class="chip !bg-rose-50 !text-rose-700 !ring-rose-200">{{ d.specialization || '—' }}</span>
            </td>
            <td>
              <span v-if="d.availability" class="text-sm text-gray-600 flex items-center gap-1">
                <Icon name="clock" :size="13" class="text-gray-400" /> {{ d.availability }}
              </span>
              <span v-else class="text-gray-400 text-sm">—</span>
            </td>
            <td>
              <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition">
                <button @click="openEdit(d)" class="p-2 rounded-lg text-rose-600 hover:bg-rose-50 transition" title="Edit">
                  <Icon name="edit" :size="16" />
                </button>
                <button @click="confirmDelete(d)" class="p-2 rounded-lg text-rose-400 hover:bg-rose-50 transition" title="Delete">
                  <Icon name="trash" :size="16" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add Modal -->
    <Modal :show="showAdd" title="Add New Doctor" size="md" @close="showAdd = false">
      <div class="space-y-4">
        <div class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-3 text-sm text-blue-700 flex items-start gap-2">
          <Icon name="stethoscope" :size="16" class="mt-0.5 flex-shrink-0" />
          <span>Select an existing doctor user account and fill in their medical details.</span>
        </div>
        <div>
          <label class="label">Doctor User Account</label>
          <select v-model="form.user_id" class="input">
            <option value="">Select a doctor account...</option>
            <option v-for="u in availableDoctorUsers" :key="u.id" :value="u.id">{{ u.name }} — {{ u.email }}</option>
          </select>
          <p v-if="availableDoctorUsers.length === 0" class="text-xs text-amber-600 mt-1">No available doctor accounts. Create one in User Management first.</p>
        </div>
        <div>
          <label class="label">Specialization <span class="text-rose-500">*</span></label>
          <select v-model="form.specialization" :class="['input', formErrors.specialization ? '!border-rose-400' : '']" @change="formErrors.specialization = ''">
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
          <p v-if="formErrors.specialization" class="field-error">{{ formErrors.specialization }}</p>
        </div>
        <div>
          <label class="label">Availability <span class="text-gray-400 text-xs font-normal">(optional)</span></label>
          <input v-model="form.availability" type="text" class="input" placeholder="e.g. Mon–Fri, 9am–5pm" />
        </div>
      </div>
      <template #footer>
        <button class="btn-secondary" @click="showAdd = false">Cancel</button>
        <button class="btn text-white shadow-sm" style="background-image: linear-gradient(135deg, #BE123C 0%, #881337 100%)" :disabled="saving" @click="submitAdd">
          {{ saving ? 'Adding...' : '+ Add Doctor' }}
        </button>
      </template>
    </Modal>

    <!-- Edit Modal -->
    <Modal :show="showEdit" title="Edit Doctor Profile" size="md" @close="showEdit = false">
      <div class="space-y-4">
        <div v-if="editingDoc" class="flex items-center gap-3 p-3 bg-rose-50 rounded-xl border border-rose-100">
          <Avatar :name="editingDoc.name" size="md" />
          <div>
            <p class="font-semibold text-gray-900">Dr. {{ editingDoc.name }}</p>
            <p class="text-sm text-gray-500">{{ editingDoc.email }}</p>
          </div>
        </div>
        <div>
          <label class="label">Specialization <span class="text-rose-500">*</span></label>
          <select v-model="form.specialization" :class="['input', formErrors.specialization ? '!border-rose-400' : '']" @change="formErrors.specialization = ''">
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
          <p v-if="formErrors.specialization" class="field-error">{{ formErrors.specialization }}</p>
        </div>
        <div>
          <label class="label">Availability</label>
          <input v-model="form.availability" type="text" class="input" placeholder="e.g. Mon–Fri, 9am–5pm" />
        </div>
      </div>
      <template #footer>
        <button class="btn-secondary" @click="showEdit = false">Cancel</button>
        <button class="btn text-white shadow-sm" style="background-image: linear-gradient(135deg, #BE123C 0%, #881337 100%)" :disabled="saving" @click="submitEdit">
          {{ saving ? 'Saving...' : '✓ Save Changes' }}
        </button>
      </template>
    </Modal>

    <!-- Delete Modal -->
    <Modal :show="showDelete" title="Remove Doctor" size="sm" @close="showDelete = false">
      <div class="space-y-3">
        <div v-if="deletingDoc" class="flex items-center gap-3 p-3 bg-rose-50 rounded-xl border border-rose-100">
          <Avatar :name="deletingDoc.name" size="md" />
          <div>
            <p class="font-semibold text-gray-900">Dr. {{ deletingDoc.name }}</p>
            <p class="text-sm text-gray-500">{{ deletingDoc.specialization }}</p>
          </div>
        </div>
        <p class="text-sm text-gray-600">Are you sure you want to remove this doctor? Their appointments will remain but they will no longer appear as an option for booking.</p>
      </div>
      <template #footer>
        <button class="btn-secondary" @click="showDelete = false">Cancel</button>
        <button class="btn-danger" :disabled="deleting" @click="deleteDoc">{{ deleting ? 'Removing...' : 'Remove Doctor' }}</button>
      </template>
    </Modal>
  </div>
</template>
