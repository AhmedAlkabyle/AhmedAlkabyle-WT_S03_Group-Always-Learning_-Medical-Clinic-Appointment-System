<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import api from '../../api/axios'
import { useAuthStore } from '../../stores/authStore'
import { useAlertStore } from '../../stores/alertStore'
import StatusBadge from '../../components/StatusBadge.vue'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import Modal from '../../components/Modal.vue'
import Avatar from '../../components/Avatar.vue'
import Icon from '../../components/Icon.vue'

const auth = useAuthStore()
const alerts = useAlertStore()

const items = ref([])
const loading = ref(false)
const statusFilter = ref('all')
const search = ref('')

const cancelTarget = ref(null)
const cancelling = ref(false)

const filtered = computed(() => {
  let list = items.value
  if (statusFilter.value !== 'all') list = list.filter(a => a.status === statusFilter.value)
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter(a => (a.doctor?.name || '').toLowerCase().includes(q))
  }
  return list
})

const fetch = async () => {
  loading.value = true
  try {
    const { data } = await api.get(`/appointments/patient/${auth.user.id}`)
    items.value = data.data || data || []
  } catch (e) {
    alerts.error('Failed to load appointments')
  } finally {
    loading.value = false
  }
}

const confirmCancel = (appt) => { cancelTarget.value = appt }

const doCancel = async () => {
  if (!cancelTarget.value) return
  cancelling.value = true
  try {
    await api.delete(`/appointments/${cancelTarget.value.id}`)
    alerts.success('Appointment cancelled')
    cancelTarget.value = null
    fetch()
  } catch (e) {
    alerts.error(e?.response?.data?.message || 'Failed to cancel appointment')
  } finally {
    cancelling.value = false
  }
}

onMounted(fetch)
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="h-1 w-full rounded-full bg-gradient-to-r from-emerald-600 to-teal-400 mb-6"></div>

    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-emerald-600 text-white flex items-center justify-center shadow-sm">
          <Icon name="calendar" :size="22" />
        </div>
        <div>
          <h1 class="text-2xl font-semibold text-gray-900">My Appointments</h1>
          <p class="text-sm text-gray-500">View and manage all your appointments.</p>
        </div>
      </div>
      <RouterLink to="/book" class="btn text-white shadow-sm" style="background-image: linear-gradient(135deg, #059669 0%, #064E3B 100%)">
        <Icon name="plus" :size="16" /> Book New
      </RouterLink>
    </div>

    <div class="flex items-center gap-3 mb-4 flex-wrap">
      <div class="relative flex-1 min-w-[180px] max-w-xs">
        <Icon name="search" :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
        <input v-model="search" type="text" class="input !pl-9" placeholder="Search by doctor..." />
      </div>
      <select v-model="statusFilter" class="input !w-auto !py-2 !text-sm !border-emerald-200 focus:!ring-emerald-300">
        <option value="all">All statuses</option>
        <option value="pending">Pending</option>
        <option value="confirmed">Confirmed</option>
        <option value="completed">Completed</option>
        <option value="cancelled">Cancelled</option>
      </select>
      <button v-if="search || statusFilter !== 'all'" @click="search=''; statusFilter='all'" class="btn-ghost text-xs text-emerald-600">Clear</button>
      <span class="text-xs text-gray-500 ml-auto">{{ filtered.length }} appointment(s)</span>
    </div>

    <div v-if="loading" class="py-12 flex justify-center"><LoadingSpinner size="md" label="Loading..." /></div>
    <div v-else-if="filtered.length === 0" class="card py-16 text-center">
      <div class="w-14 h-14 mx-auto rounded-xl bg-emerald-50 text-emerald-400 flex items-center justify-center mb-3">
        <Icon name="calendar" :size="26" />
      </div>
      <p class="font-medium text-gray-700">No appointments found</p>
      <p class="text-sm text-gray-400 mt-1">{{ search || statusFilter !== 'all' ? 'Try clearing your filters' : 'Book your first appointment to get started' }}</p>
      <RouterLink v-if="!search && statusFilter === 'all'" to="/book" class="inline-flex mt-4 items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
        <Icon name="plus" :size="15" /> Book Now
      </RouterLink>
    </div>
    <div v-else class="card !p-0 overflow-hidden">
      <div class="px-4 py-3 border-b border-emerald-100 bg-emerald-50/40 flex items-center gap-6 flex-wrap">
        <span class="text-sm font-medium text-emerald-800">{{ items.length }} total</span>
        <span class="text-sm text-gray-500">Upcoming: <strong class="text-amber-600">{{ items.filter(a=>a.status==='pending'||a.status==='confirmed').length }}</strong></span>
        <span class="text-sm text-gray-500">Completed: <strong class="text-blue-600">{{ items.filter(a=>a.status==='completed').length }}</strong></span>
      </div>
      <table class="tbl">
        <thead class="[&_th]:!bg-emerald-50/60 [&_th]:!text-emerald-900/60">
          <tr>
            <th>Doctor</th>
            <th>Specialization</th>
            <th>Date</th>
            <th>Time</th>
            <th>Status</th>
            <th class="text-right">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="a in filtered" :key="a.id">
            <td>
              <div class="flex items-center gap-3">
                <Avatar :name="a.doctor?.name || a.doctor_name" size="sm" />
                <span class="font-medium text-gray-900">Dr. {{ a.doctor?.name || a.doctor_name || '—' }}</span>
              </div>
            </td>
            <td>
              <span v-if="a.doctor?.specialization" class="chip bg-emerald-50 text-emerald-700 ring-emerald-200">
                {{ a.doctor.specialization }}
              </span>
              <span v-else class="text-gray-400">—</span>
            </td>
            <td>{{ a.date }}</td>
            <td>{{ a.time }}</td>
            <td><StatusBadge :status="a.status" /></td>
            <td class="text-right">
              <button
                v-if="a.status === 'pending'"
                class="text-xs px-2.5 py-1 rounded-lg font-medium bg-rose-50 text-rose-600 hover:bg-rose-100 transition"
                @click="confirmCancel(a)"
                title="Cancel"
              >Cancel</button>
              <span v-else class="text-xs text-gray-400">—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Modal :show="!!cancelTarget" title="Cancel Appointment" size="sm" @close="cancelTarget=null">
      <p class="text-sm text-gray-600">
        Are you sure you want to cancel your appointment with
        <strong>Dr. {{ cancelTarget?.doctor?.name || cancelTarget?.doctor_name }}</strong>
        on <strong>{{ cancelTarget?.date }} {{ cancelTarget?.time }}</strong>?
      </p>
      <template #footer>
        <button class="btn-secondary" @click="cancelTarget=null">Keep</button>
        <button class="btn-danger" :disabled="cancelling" @click="doCancel">
          {{ cancelling ? 'Cancelling...' : 'Yes, Cancel' }}
        </button>
      </template>
    </Modal>
  </div>
</template>
