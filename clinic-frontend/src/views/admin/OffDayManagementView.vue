<script setup>
import { onMounted, ref, computed } from 'vue'
import api from '../../api/axios'
import { useAlertStore } from '../../stores/alertStore'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import StatusBadge from '../../components/StatusBadge.vue'
import Avatar from '../../components/Avatar.vue'
import Icon from '../../components/Icon.vue'

const alerts = useAlertStore()
const offDays = ref([])
const loading = ref(true)
const statusFilter = ref('all')
const search = ref('')

const fetchAll = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/off-days')
    offDays.value = (data || []).sort((a, b) => (a.status === 'pending' ? -1 : 1) - (b.status === 'pending' ? -1 : 1) || (b.date || '').localeCompare(a.date || ''))
  } catch (e) {
    alerts.error(e.response?.data?.message || 'Failed to load off days')
  } finally {
    loading.value = false
  }
}

const filtered = computed(() => {
  let list = offDays.value
  if (statusFilter.value !== 'all') list = list.filter(o => o.status === statusFilter.value)
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter(o => (o.doctor?.name || '').toLowerCase().includes(q) || (o.reason || '').toLowerCase().includes(q))
  }
  return list
})

const stats = computed(() => ({
  total: offDays.value.length,
  pending: offDays.value.filter(o => o.status === 'pending').length,
  approved: offDays.value.filter(o => o.status === 'approved').length,
  rejected: offDays.value.filter(o => o.status === 'rejected').length
}))

const setStatus = async (o, status) => {
  try {
    await api.put(`/off-days/${o.id}`, { status })
    alerts.success(`Request ${status}`)
    await fetchAll()
  } catch (e) {
    alerts.error(e.response?.data?.message || 'Update failed')
  }
}

const remove = async (o) => {
  try {
    await api.delete(`/off-days/${o.id}`)
    alerts.success('Removed')
    await fetchAll()
  } catch (e) {
    alerts.error(e.response?.data?.message || 'Delete failed')
  }
}

const fmt = (d) => new Date(d).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })

onMounted(fetchAll)
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="h-1 w-full rounded-full bg-gradient-to-r from-rose-600 to-pink-400 mb-6"></div>
    <div class="flex items-center gap-4 mb-6">
      <div class="w-12 h-12 rounded-xl bg-rose-600 text-white flex items-center justify-center shadow-sm">
        <Icon name="clock" :size="22" />
      </div>
      <div>
        <h1 class="text-2xl font-semibold text-gray-900">Doctor Off-Day Requests</h1>
        <p class="text-sm text-gray-500">Approve or reject off-day requests submitted by doctors.</p>
      </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="stat-card border-l-4 border-rose-400">
        <div class="stat-icon bg-rose-50 text-rose-600"><Icon name="calendar" :size="20" /></div>
        <div><p class="text-xs uppercase tracking-wide text-gray-500">Total</p><p class="text-2xl font-semibold">{{ stats.total }}</p></div>
      </div>
      <div class="stat-card border-l-4 border-amber-400">
        <div class="stat-icon !bg-amber-50 !text-amber-600"><Icon name="clock" :size="20" /></div>
        <div><p class="text-xs uppercase tracking-wide text-gray-500">Pending</p><p class="text-2xl font-semibold">{{ stats.pending }}</p></div>
      </div>
      <div class="stat-card border-l-4 border-emerald-400">
        <div class="stat-icon !bg-emerald-50 !text-emerald-600"><Icon name="check" :size="20" /></div>
        <div><p class="text-xs uppercase tracking-wide text-gray-500">Approved</p><p class="text-2xl font-semibold">{{ stats.approved }}</p></div>
      </div>
      <div class="stat-card border-l-4 border-rose-300">
        <div class="stat-icon bg-rose-50 text-rose-400"><Icon name="x" :size="20" /></div>
        <div><p class="text-xs uppercase tracking-wide text-gray-500">Rejected</p><p class="text-2xl font-semibold">{{ stats.rejected }}</p></div>
      </div>
    </div>

    <div class="flex items-center gap-3 mb-4 bg-rose-50/30 border border-rose-100 rounded-xl p-3 flex-wrap">
      <Icon name="filter" :size="16" class="text-rose-400" />
      <div class="relative flex-1 min-w-[180px] max-w-xs">
        <Icon name="search" :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
        <input v-model="search" type="text" class="input !pl-9 !py-1.5 !text-sm" placeholder="Search doctor or reason..." />
      </div>
      <select v-model="statusFilter" class="input !w-auto !py-1.5 !text-sm !border-rose-200">
        <option value="all">All</option>
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
      </select>
      <button v-if="search || statusFilter !== 'all'" @click="search=''; statusFilter='all'" class="btn-ghost text-xs text-rose-500">Clear</button>
      <span class="text-xs text-gray-500 ml-auto">{{ filtered.length }} request(s)</span>
    </div>
    <div class="card !p-0 overflow-hidden">

      <div v-if="loading" class="py-16 flex justify-center"><LoadingSpinner label="Loading..." /></div>
      <div v-else-if="filtered.length === 0" class="py-16 text-center">
        <div class="inline-flex w-12 h-12 rounded-xl bg-clinic-50 text-clinic-600 items-center justify-center mb-3">
          <Icon name="calendar" :size="22" />
        </div>
        <p class="text-gray-700 font-medium">No off-day requests</p>
      </div>
      <table v-else class="w-full text-sm">
        <thead class="bg-rose-50/60 text-xs uppercase tracking-wide text-rose-900/60">
          <tr>
            <th class="text-left px-4 py-3">Doctor</th>
            <th class="text-left px-4 py-3">Date</th>
            <th class="text-left px-4 py-3">Reason</th>
            <th class="text-left px-4 py-3">Status</th>
            <th class="text-right px-4 py-3">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="o in filtered" :key="o.id" class="hover:bg-gray-50">
            <td class="px-4 py-3">
              <div class="flex items-center gap-3">
                <Avatar :name="o.doctor?.name" size="sm" />
                <div>
                  <p class="font-medium text-gray-900">Dr. {{ o.doctor?.name || '—' }}</p>
                  <p class="text-xs text-clinic-700">{{ o.doctor?.specialization || '—' }}</p>
                </div>
              </div>
            </td>
            <td class="px-4 py-3 text-gray-700">{{ fmt(o.date) }}</td>
            <td class="px-4 py-3 text-gray-600">{{ o.reason || '—' }}</td>
            <td class="px-4 py-3"><StatusBadge :status="o.status" /></td>
            <td class="px-4 py-3 text-right">
              <div class="inline-flex items-center gap-1">
                <button
                  v-if="o.status !== 'approved'"
                  class="text-rose-600 hover:text-rose-800 p-1.5 rounded-lg"
                  title="Approve" @click="setStatus(o, 'approved')"
                ><Icon name="check" :size="16" /></button>
                <button
                  v-if="o.status !== 'rejected'"
                  class="text-amber-600 hover:bg-amber-50 p-1.5 rounded-lg"
                  title="Reject" @click="setStatus(o, 'rejected')"
                ><Icon name="x" :size="16" /></button>
                <button
                  class="text-rose-600 hover:bg-rose-50 p-1.5 rounded-lg"
                  title="Delete" @click="remove(o)"
                ><Icon name="trash" :size="16" /></button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
