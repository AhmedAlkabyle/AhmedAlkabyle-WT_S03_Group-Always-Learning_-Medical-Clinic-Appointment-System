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
const offDays = ref([])
const profile = ref(null)
const loading = ref(false)
const dateFilter = ref('')
const statusFilter = ref('all')
const updatingId = ref(null)
const showComment = ref(false)
const commentAppt = ref(null)
const commentText = ref('')
const savingComment = ref(false)
const completingId = ref(null)

const todayStr = new Date().toISOString().split('T')[0]

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'Good morning'
  if (h < 17) return 'Good afternoon'
  return 'Good evening'
})

const todayPretty = computed(() =>
  new Date().toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' })
)

const sortedAll = computed(() =>
  items.value.slice().sort((a, b) =>
    new Date(`${a.date}T${a.time || '00:00'}`) - new Date(`${b.date}T${b.time || '00:00'}`)
  )
)

const todayList = computed(() => sortedAll.value.filter(a => a.date === todayStr))
const upcomingList = computed(() =>
  sortedAll.value.filter(a => a.date > todayStr && (a.status || '').toLowerCase() !== 'cancelled')
)
const pendingList = computed(() =>
  sortedAll.value.filter(a => (a.status || '').toLowerCase() === 'pending')
)

const stats = computed(() => {
  const uniquePatients = new Set(items.value.map(a => a.patient_id)).size
  return {
    today: todayList.value.length,
    upcoming: upcomingList.value.length,
    pending: pendingList.value.length,
    patients: uniquePatients
  }
})

const filtered = computed(() => {
  let list = sortedAll.value
  if (dateFilter.value) list = list.filter(a => a.date === dateFilter.value)
  if (statusFilter.value !== 'all') list = list.filter(a => (a.status || '').toLowerCase() === statusFilter.value)
  return list
})

const upcomingOffDays = computed(() =>
  offDays.value
    .filter(o => o.date >= todayStr)
    .sort((a, b) => a.date.localeCompare(b.date))
    .slice(0, 3)
)

const fmtDate = (d) => new Date(d).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })

const fetchData = async () => {
  loading.value = true
  try {
    // First get the doctor profile to find the correct doctor table ID
    const { data: docs } = await api.get('/doctors')
    const list = docs.data || docs || []
    profile.value = list.find(d => d.user_id === auth.user.id) || null
    const doctorId = profile.value?.id

    const [{ data: appts }, { data: offs }] = await Promise.all([
      doctorId
        ? api.get(`/appointments/doctor/${doctorId}`)
        : Promise.resolve({ data: [] }),
      doctorId
        ? api.get(`/off-days/doctor/${doctorId}`).catch(() => ({ data: [] }))
        : Promise.resolve({ data: [] })
    ])
    items.value = appts.data || appts || []
    offDays.value = offs || []
  } catch (e) {
    alerts.error('Failed to load schedule')
  } finally {
    loading.value = false
  }
}

const updateStatus = async (a, status) => {
  updatingId.value = a.id
  try {
    await api.put(`/appointments/${a.id}`, { status })
    a.status = status
    alerts.success(`Appointment ${status}`)
  } catch (e) {
    alerts.error(e.response?.data?.error || e.response?.data?.message || 'Update failed')
  } finally {
    updatingId.value = null
  }
}

const openComment = (appt) => {
  commentAppt.value = appt
  commentText.value = appt.doctor_comment || ''
  showComment.value = true
}

const saveComment = async () => {
  if (!commentAppt.value) return
  savingComment.value = true
  try {
    await api.put(`/appointments/${commentAppt.value.id}/comment`, {
      doctor_comment: commentText.value
    })
    commentAppt.value.doctor_comment = commentText.value
    alerts.success('Comment saved')
    showComment.value = false
  } catch (e) {
    alerts.error(e?.response?.data?.message || 'Failed to save comment')
  } finally {
    savingComment.value = false
  }
}

const markCompleted = async (appt) => {
  completingId.value = appt.id
  try {
    await api.put(`/appointments/${appt.id}/complete`, { status: 'completed' })
    appt.status = 'completed'
    alerts.success('Appointment marked as completed')
  } catch (e) {
    alerts.error(e?.response?.data?.message || 'Failed to update')
  } finally {
    completingId.value = null
  }
}

onMounted(fetchData)
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Hero -->
    <div
      class="rounded-2xl text-white p-6 sm:p-8 mb-6 relative overflow-hidden"
      style="background-image: linear-gradient(135deg, #1A365D 0%, #2B6CB0 60%, #4299E1 100%)"
    >
      <div class="absolute -right-16 -top-16 w-72 h-72 rounded-full bg-white/10 blur-2xl"></div>
      <div class="absolute -left-20 -bottom-24 w-80 h-80 rounded-full bg-white/10 blur-3xl"></div>

      <div class="relative inline-flex items-center gap-1.5 bg-white/20 backdrop-blur text-white text-xs font-semibold px-3 py-1 rounded-full mb-3">
        <Icon name="stethoscope" :size="12" /> Doctor Portal
      </div>
      <div class="relative flex items-start gap-4 flex-wrap">
        <Avatar :name="auth.user?.name" size="lg" class="!w-16 !h-16 !text-xl ring-4 ring-white/20" />
        <div class="flex-1 min-w-0">
          <p class="text-clinic-100/90 text-sm">{{ greeting }},</p>
          <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight">Dr. {{ auth.user?.name }}</h1>
          <p class="text-clinic-100/90 mt-1 text-sm">
            <span>{{ profile?.specialization || 'General Practitioner' }}</span>
            <span v-if="profile?.availability" class="opacity-80"> · <Icon name="clock" :size="12" class="inline -mt-0.5" /> {{ profile.availability }}</span>
          </p>
        </div>
        <div class="text-right">
          <p class="text-xs text-white/70 uppercase tracking-wider">{{ todayPretty }}</p>
          <p class="text-3xl font-semibold leading-tight mt-1">{{ stats.today }}<span class="text-base font-medium text-white/80"> appt today</span></p>
        </div>
      </div>

      <div class="relative mt-5 flex flex-wrap gap-2">
        <RouterLink to="/off-days" class="bg-white/15 hover:bg-white/25 backdrop-blur text-white text-sm font-medium px-3.5 py-2 rounded-lg inline-flex items-center gap-2 transition">
          <Icon name="clock" :size="16" /> Request Off Day
        </RouterLink>
        <RouterLink to="/profile" class="bg-white/15 hover:bg-white/25 backdrop-blur text-white text-sm font-medium px-3.5 py-2 rounded-lg inline-flex items-center gap-2 transition">
          <Icon name="user" :size="16" /> Edit Profile
        </RouterLink>
      </div>
    </div>

    <!-- Stat cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="stat-card border-t-4 border-t-blue-400">
        <div class="stat-icon bg-blue-50 text-blue-600"><Icon name="calendar" :size="20" /></div>
        <div>
          <p class="text-xs uppercase tracking-wide text-gray-500">Today</p>
          <p class="text-2xl font-semibold text-gray-900">{{ stats.today }}</p>
        </div>
      </div>
      <div class="stat-card border-t-4 border-t-indigo-400">
        <div class="stat-icon bg-indigo-50 text-indigo-600"><Icon name="clock" :size="20" /></div>
        <div>
          <p class="text-xs uppercase tracking-wide text-gray-500">Upcoming</p>
          <p class="text-2xl font-semibold text-gray-900">{{ stats.upcoming }}</p>
        </div>
      </div>
      <div class="stat-card border-t-4 border-t-amber-400">
        <div class="stat-icon !bg-amber-50 !text-amber-600"><Icon name="check" :size="20" /></div>
        <div>
          <p class="text-xs uppercase tracking-wide text-gray-500">Pending</p>
          <p class="text-2xl font-semibold text-gray-900">{{ stats.pending }}</p>
        </div>
      </div>
      <div class="stat-card border-t-4 border-t-sky-400">
        <div class="stat-icon bg-sky-50 text-sky-600"><Icon name="users" :size="20" /></div>
        <div>
          <p class="text-xs uppercase tracking-wide text-gray-500">Patients</p>
          <p class="text-2xl font-semibold text-gray-900">{{ stats.patients }}</p>
        </div>
      </div>
    </div>

    <div v-if="loading" class="py-12 flex justify-center"><LoadingSpinner size="md" label="Loading..." /></div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- LEFT: Today's schedule + Full schedule -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Today's schedule -->
        <div class="card">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h2 class="text-base font-semibold text-gray-900">Today's Schedule</h2>
              <p class="text-xs text-gray-500">{{ todayList.length }} appointment(s)</p>
            </div>
            <span class="chip !bg-blue-50 !text-blue-700 !ring-blue-200">{{ new Date().toLocaleDateString(undefined,{month:'short',day:'numeric'}) }}</span>
          </div>

          <div v-if="todayList.length === 0" class="text-center py-8">
            <div class="inline-flex w-12 h-12 rounded-xl bg-blue-50 text-blue-600 items-center justify-center mb-2">
              <Icon name="check" :size="22" />
            </div>
            <p class="text-gray-700 font-medium">No appointments today</p>
            <p class="text-sm text-gray-500">Enjoy your day!</p>
          </div>

          <ul v-else class="space-y-2">
            <li
              v-for="a in todayList" :key="a.id"
              class="flex items-center gap-3 p-3 rounded-xl border border-gray-100 hover:bg-blue-50/30 transition border-l-4 border-l-blue-400 pl-4"
            >
              <div class="flex-shrink-0 w-14 text-center">
                <div class="text-lg font-semibold text-blue-700 leading-tight">{{ a.time || '—' }}</div>
                <div class="text-[10px] uppercase tracking-wider text-gray-400">Today</div>
              </div>
              <div class="w-px h-10 bg-gray-200"></div>
              <Avatar :name="a.patient_name" size="sm" />
              <div class="flex-1 min-w-0">
                <p class="font-medium text-gray-900 truncate">{{ a.patient_name || '—' }}</p>
                <p v-if="a.notes" class="text-xs text-gray-500 truncate">{{ a.notes }}</p>
              </div>
              <StatusBadge :status="a.status" />
              <div class="flex items-center gap-1">
                <button
                  v-if="(a.status || '').toLowerCase() !== 'confirmed' && (a.status || '').toLowerCase() !== 'cancelled'"
                  class="text-emerald-600 hover:bg-emerald-50 p-1.5 rounded-lg disabled:opacity-50"
                  :disabled="updatingId === a.id"
                  title="Confirm" @click="updateStatus(a, 'confirmed')"
                ><Icon name="check" :size="16" /></button>
                <button
                  v-if="(a.status || '').toLowerCase() !== 'cancelled'"
                  class="text-rose-600 hover:bg-rose-50 p-1.5 rounded-lg disabled:opacity-50"
                  :disabled="updatingId === a.id"
                  title="Cancel" @click="updateStatus(a, 'cancelled')"
                ><Icon name="x" :size="16" /></button>
              </div>
            </li>
          </ul>
        </div>

        <!-- Full schedule with filters -->
        <div class="card !p-0 overflow-hidden">
          <div class="px-4 py-3 flex items-center gap-2 border-b border-gray-100 bg-blue-50/30 flex-wrap">
            <Icon name="filter" :size="16" class="text-blue-500" />
            <input v-model="dateFilter" type="date" class="input !w-auto !py-1.5 !text-sm" />
            <select v-model="statusFilter" class="input !w-auto !py-1.5 !text-sm">
              <option value="all">All status</option>
              <option value="pending">Pending</option>
              <option value="confirmed">Confirmed</option>
              <option value="cancelled">Cancelled</option>
              <option value="completed">Completed</option>
            </select>
            <button v-if="dateFilter || statusFilter !== 'all'" class="btn-ghost text-xs" @click="dateFilter=''; statusFilter='all'">Clear</button>
            <span class="text-xs text-gray-500 ml-auto">{{ filtered.length }} item(s)</span>
          </div>

          <div class="px-4 py-3 border-b border-blue-100 bg-blue-50/40 flex items-center gap-6 flex-wrap">
            <span class="text-sm font-medium text-blue-800">{{ items.length }} total appointment(s)</span>
            <span class="text-sm text-gray-500">Today: <strong class="text-blue-600">{{ todayList.length }}</strong></span>
            <span class="text-sm text-gray-500">Pending: <strong class="text-amber-600">{{ pendingList.length }}</strong></span>
          </div>
          <div v-if="filtered.length === 0" class="py-12 text-center">
            <div class="inline-flex w-12 h-12 rounded-xl bg-blue-50 text-blue-600 items-center justify-center mb-2">
              <Icon name="calendar" :size="22" />
            </div>
            <p class="text-gray-700 font-medium">No appointments found</p>
          </div>
          <table v-else class="w-full text-sm">
            <thead class="bg-blue-50/40 text-xs uppercase tracking-wide text-gray-500">
              <tr>
                <th class="text-left px-4 py-3">Patient</th>
                <th class="text-left px-4 py-3">Date</th>
                <th class="text-left px-4 py-3">Time</th>
                <th class="text-left px-4 py-3">Status</th>
                <th class="text-left px-4 py-3">Notes</th>
                <th class="text-left px-4 py-3">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="a in filtered" :key="a.id" class="hover:bg-blue-50/20">
                <td class="px-4 py-3">
                  <div class="flex items-center gap-3">
                    <Avatar :name="a.patient_name" size="sm" />
                    <span class="font-medium text-gray-900">{{ a.patient_name || '—' }}</span>
                  </div>
                </td>
                <td class="px-4 py-3 text-gray-700">{{ fmtDate(a.date) }}</td>
                <td class="px-4 py-3 text-gray-700">{{ a.time }}</td>
                <td class="px-4 py-3"><StatusBadge :status="a.status" /></td>
                <td class="px-4 py-3 text-gray-600 max-w-xs truncate" :title="a.notes">{{ a.notes || '—' }}</td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-1 flex-wrap">
                    <button
                      v-if="a.status === 'pending'"
                      @click="updateStatus(a, 'confirmed')"
                      :disabled="updatingId === a.id"
                      class="text-xs px-2 py-1 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 font-medium disabled:opacity-50 transition inline-flex items-center gap-1"
                      title="Confirm appointment"
                    ><Icon name="check" :size="13" /> Confirm</button>
                    <button
                      v-if="a.status === 'confirmed'"
                      @click="markCompleted(a)"
                      :disabled="completingId === a.id"
                      class="text-xs px-2 py-1 rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100 font-medium disabled:opacity-50 transition"
                      title="Mark as completed"
                    >✓ Done</button>
                    <button
                      v-if="a.status === 'pending' || a.status === 'confirmed'"
                      @click="updateStatus(a, 'cancelled')"
                      :disabled="updatingId === a.id"
                      class="text-xs px-2 py-1 rounded-lg bg-rose-50 text-rose-700 hover:bg-rose-100 font-medium disabled:opacity-50 transition inline-flex items-center gap-1"
                      title="Cancel appointment"
                    ><Icon name="x" :size="13" /> Cancel</button>
                    <button
                      @click="openComment(a)"
                      class="text-xs px-2 py-1 rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 font-medium transition"
                      title="Add clinical note"
                    >💬</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- RIGHT: Pending + Off-days -->
      <div class="space-y-6">
        <div class="card border-t-4 border-t-amber-400">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-base font-semibold text-gray-900">Pending Approvals</h2>
            <span class="chip !bg-amber-50 !text-amber-700">{{ pendingList.length }}</span>
          </div>
          <div v-if="pendingList.length === 0" class="text-sm text-gray-500 py-4 text-center">No pending requests.</div>
          <ul v-else class="space-y-2">
            <li
              v-for="a in pendingList.slice(0, 5)" :key="a.id"
              class="p-3 rounded-xl border border-gray-100 hover:bg-blue-50/20"
            >
              <div class="flex items-center gap-2.5">
                <Avatar :name="a.patient_name" size="sm" />
                <div class="flex-1 min-w-0">
                  <p class="font-medium text-gray-900 truncate text-sm">{{ a.patient_name || '—' }}</p>
                  <p class="text-xs text-gray-500">{{ fmtDate(a.date) }} · {{ a.time }}</p>
                </div>
              </div>
              <div class="flex items-center gap-2 mt-2">
                <button
                  class="flex-1 text-xs font-medium px-2 py-1.5 rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100 inline-flex items-center justify-center gap-1 disabled:opacity-50"
                  :disabled="updatingId === a.id"
                  @click="updateStatus(a, 'confirmed')"
                ><Icon name="check" :size="14" /> Confirm</button>
                <button
                  class="flex-1 text-xs font-medium px-2 py-1.5 rounded-lg bg-rose-50 text-rose-700 hover:bg-rose-100 inline-flex items-center justify-center gap-1 disabled:opacity-50"
                  :disabled="updatingId === a.id"
                  @click="updateStatus(a, 'cancelled')"
                ><Icon name="x" :size="14" /> Decline</button>
              </div>
            </li>
          </ul>
        </div>

        <div class="card border-t-4 border-t-blue-300">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-base font-semibold text-gray-900">My Off Days</h2>
            <RouterLink to="/off-days" class="text-xs text-blue-600 hover:underline">Manage</RouterLink>
          </div>
          <div v-if="upcomingOffDays.length === 0" class="text-sm text-gray-500 py-4 text-center">No upcoming off-days.</div>
          <ul v-else class="space-y-2">
            <li v-for="o in upcomingOffDays" :key="o.id"
              class="flex items-center gap-3 p-2.5 rounded-xl border border-gray-100">
              <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-700 flex items-center justify-center">
                <Icon name="calendar" :size="16" />
              </div>
              <div class="flex-1 min-w-0">
                <p class="font-medium text-gray-900 text-sm">{{ fmtDate(o.date) }}</p>
                <p class="text-xs text-gray-500 truncate">{{ o.reason || 'No reason given' }}</p>
              </div>
              <StatusBadge :status="o.status" />
            </li>
          </ul>
        </div>
      </div>
    </div>

    <Modal :show="showComment" title="Add Clinical Note" size="md" @close="showComment = false">
      <div class="space-y-3">
        <div class="bg-blue-50 border border-blue-100 rounded-lg px-4 py-3 text-sm text-blue-700">
          <strong>Patient:</strong> {{ commentAppt?.patient_name }} ·
          <strong>Date:</strong> {{ commentAppt?.date }} {{ commentAppt?.time }}
        </div>
        <div>
          <label class="label">Clinical Notes / Comment</label>
          <textarea
            v-model="commentText"
            class="input !h-32 resize-none"
            placeholder="Add clinical notes, observations, or follow-up instructions..."
          ></textarea>
        </div>
      </div>
      <template #footer>
        <button class="btn-secondary" @click="showComment = false">Cancel</button>
        <button
          class="btn text-white shadow-sm"
          style="background-image: linear-gradient(135deg, #2B6CB0 0%, #1A365D 100%)"
          :disabled="savingComment"
          @click="saveComment"
        >
          {{ savingComment ? 'Saving...' : 'Save Note' }}
        </button>
      </template>
    </Modal>
  </div>
</template>
