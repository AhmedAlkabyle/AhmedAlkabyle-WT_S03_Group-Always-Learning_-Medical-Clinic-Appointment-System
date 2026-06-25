<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../../api/axios'
import { useAlertStore } from '../../stores/alertStore'
import StatusBadge from '../../components/StatusBadge.vue'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import Modal from '../../components/Modal.vue'
import Avatar from '../../components/Avatar.vue'
import Icon from '../../components/Icon.vue'

const alerts = useAlertStore()
const items = ref([])
const loading = ref(false)
const statusFilter = ref('all')
const search = ref('')
const dateFilter = ref('')

const editTarget = ref(null)
const editStatus = ref('pending')
const saving = ref(false)

const deleting = ref(null)
const deletingBusy = ref(false)

const filtered = computed(() => {
  let list = items.value
  if (statusFilter.value !== 'all') list = list.filter(a => a.status === statusFilter.value)
  if (dateFilter.value) list = list.filter(a => a.date === dateFilter.value)
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter(a =>
      (a.patient?.name || '').toLowerCase().includes(q) ||
      (a.doctor?.name || '').toLowerCase().includes(q)
    )
  }
  return list
})

const load = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/appointments')
    items.value = data.data || data || []
  } catch (e) {
    alerts.error('Failed to load appointments')
  } finally {
    loading.value = false
  }
}

const openEdit = (a) => {
  editTarget.value = a
  editStatus.value = a.status || 'pending'
}

const saveStatus = async () => {
  if (!editTarget.value) return
  saving.value = true
  try {
    await api.put(`/appointments/${editTarget.value.id}`, { status: editStatus.value })
    alerts.success('Status updated')
    editTarget.value = null
    load()
  } catch (e) {
    alerts.error(e?.response?.data?.message || 'Update failed')
  } finally {
    saving.value = false
  }
}

const doDelete = async () => {
  if (!deleting.value) return
  deletingBusy.value = true
  try {
    await api.delete(`/appointments/${deleting.value.id}`)
    alerts.success('Appointment deleted')
    deleting.value = null
    load()
  } catch (e) {
    alerts.error(e?.response?.data?.message || 'Delete failed')
  } finally {
    deletingBusy.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="h-1 w-full rounded-full bg-gradient-to-r from-rose-600 to-pink-400 mb-6"></div>
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-rose-600 text-white flex items-center justify-center shadow-sm">
          <Icon name="calendar" :size="22" />
        </div>
        <div>
          <h1 class="text-2xl font-semibold text-gray-900">Appointments</h1>
          <p class="text-sm text-gray-500">Review and manage all clinic appointments.</p>
        </div>
      </div>
    </div>

    <div class="flex items-center gap-3 mb-4 flex-wrap">
      <div class="relative flex-1 min-w-[180px] max-w-xs">
        <Icon name="search" :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
        <input v-model="search" type="text" class="input !pl-9" placeholder="Search patient or doctor..." />
      </div>
      <input v-model="dateFilter" type="date" class="input !w-auto !py-2 !text-sm" />
      <select v-model="statusFilter" class="input !w-auto !py-2 !text-sm">
        <option value="all">All statuses</option>
        <option value="pending">Pending</option>
        <option value="confirmed">Confirmed</option>
        <option value="completed">Completed</option>
        <option value="cancelled">Cancelled</option>
      </select>
      <button v-if="search || dateFilter || statusFilter !== 'all'" @click="search=''; dateFilter=''; statusFilter='all'" class="btn-ghost text-xs text-rose-500">
        Clear filters
      </button>
      <span class="text-xs text-gray-500 ml-auto">{{ filtered.length }} result(s)</span>
    </div>

    <div v-if="loading" class="py-12 flex justify-center"><LoadingSpinner size="md" label="Loading..." /></div>
    <div v-else-if="filtered.length === 0" class="card text-center py-14">
      <div class="w-14 h-14 mx-auto rounded-full bg-clinic-50 text-clinic-600 flex items-center justify-center mb-3">
        <Icon name="calendar" :size="24" />
      </div>
      <p class="text-gray-700 font-medium">No appointments found</p>
      <p class="text-sm text-gray-500 mt-1">Try changing the filter.</p>
    </div>
    <div v-else class="table-wrap">
      <div class="px-4 py-3 border-b border-rose-100 bg-rose-50/40 flex items-center gap-6 flex-wrap">
        <span class="text-sm font-medium text-rose-800">{{ items.length }} total</span>
        <span class="text-sm text-gray-500">Pending: <strong class="text-amber-600">{{ items.filter(a=>a.status==='pending').length }}</strong></span>
        <span class="text-sm text-gray-500">Confirmed: <strong class="text-emerald-600">{{ items.filter(a=>a.status==='confirmed').length }}</strong></span>
        <span class="text-sm text-gray-500">Completed: <strong class="text-blue-600">{{ items.filter(a=>a.status==='completed').length }}</strong></span>
        <span class="text-sm text-gray-500">Cancelled: <strong class="text-rose-600">{{ items.filter(a=>a.status==='cancelled').length }}</strong></span>
      </div>
      <table class="tbl">
        <thead class="[&_th]:!bg-rose-50/60 [&_th]:!text-rose-900/60">
          <tr>
            <th>Patient</th>
            <th>Doctor</th>
            <th>Date</th>
            <th>Time</th>
            <th>Status</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="a in filtered" :key="a.id">
            <td>
              <div class="flex items-center gap-3">
                <Avatar :name="a.patient?.name || a.patient_name" size="sm" />
                <span class="font-medium text-gray-900">{{ a.patient?.name || a.patient_name || '—' }}</span>
              </div>
            </td>
            <td>
              <div class="flex items-center gap-3">
                <Avatar :name="a.doctor?.name || a.doctor_name" size="sm" />
                <span>Dr. {{ a.doctor?.name || a.doctor_name || '—' }}</span>
              </div>
            </td>
            <td>{{ a.date }}</td>
            <td>{{ a.time }}</td>
            <td><StatusBadge :status="a.status" /></td>
            <td class="text-right">
              <div class="inline-flex gap-2">
                <button class="text-rose-600 hover:bg-rose-50 p-1.5 rounded-lg" @click="openEdit(a)" title="Update status">
                  <Icon name="edit" :size="14" />
                </button>
                <button class="btn-danger !px-2.5 !py-1.5" @click="deleting = a" title="Delete">
                  <Icon name="trash" :size="14" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Modal :show="!!editTarget" title="Update Appointment Status" size="sm" @close="editTarget=null">
      <div class="space-y-3">
        <p class="text-sm text-gray-600">
          Patient: <strong>{{ editTarget?.patient?.name || editTarget?.patient_name }}</strong><br />
          Doctor: <strong>Dr. {{ editTarget?.doctor?.name || editTarget?.doctor_name }}</strong><br />
          Date: <strong>{{ editTarget?.date }} {{ editTarget?.time }}</strong>
        </p>
        <div>
          <label class="label">Status</label>
          <select v-model="editStatus" class="input">
            <option value="pending">Pending</option>
            <option value="confirmed">Confirmed</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
      </div>
      <template #footer>
        <button class="btn-secondary" @click="editTarget=null">Cancel</button>
        <button class="btn-primary" :disabled="saving" @click="saveStatus">
          {{ saving ? 'Saving...' : 'Save' }}
        </button>
      </template>
    </Modal>

    <Modal :show="!!deleting" title="Delete Appointment" size="sm" @close="deleting=null">
      <p class="text-sm text-gray-600">
        Are you sure you want to permanently delete this appointment? This cannot be undone.
      </p>
      <template #footer>
        <button class="btn-secondary" @click="deleting=null">Cancel</button>
        <button class="btn-danger" :disabled="deletingBusy" @click="doDelete">
          {{ deletingBusy ? 'Deleting...' : 'Delete' }}
        </button>
      </template>
    </Modal>
  </div>
</template>
