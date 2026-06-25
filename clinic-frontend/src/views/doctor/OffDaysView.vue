<script setup>
import { onMounted, reactive, ref, computed, watch } from 'vue'
import api from '../../api/axios'
import { useAuthStore } from '../../stores/authStore'
import { useAlertStore } from '../../stores/alertStore'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import StatusBadge from '../../components/StatusBadge.vue'
import Modal from '../../components/Modal.vue'
import Icon from '../../components/Icon.vue'

const auth = useAuthStore()
const alerts = useAlertStore()

const offDays = ref([])
const appointments = ref([])
const loading = ref(true)
const submitting = ref(false)
const showAdd = ref(false)
const showDelete = ref(false)
const targetId = ref(null)
const tab = ref('upcoming') // 'upcoming' | 'past' | 'all'

const today = computed(() => new Date().toISOString().split('T')[0])

const REASONS = [
  { value: 'sick',       label: 'Sick Leave',  icon: 'heart' },
  { value: 'personal',   label: 'Personal',    icon: 'user' },
  { value: 'conference', label: 'Conference',  icon: 'stethoscope' },
  { value: 'vacation',   label: 'Vacation',    icon: 'calendar' },
  { value: 'other',      label: 'Other',       icon: 'edit' }
]

const form = reactive({
  mode: 'single',     // 'single' | 'range'
  date: '',           // single mode
  startDate: '',      // range mode
  endDate: '',        // range mode
  reasonType: '',
  reason: ''
})
const errors = reactive({ date: '', range: '', reasonType: '' })

const fetchOffDays = async () => {
  loading.value = true
  try {
    // Resolve the doctor table ID from the doctors list first
    const { data: doctorList } = await api.get('/doctors')
    const list = doctorList.data || doctorList || []
    const doctorProfile = list.find(d => d.user_id === auth.user.id)
    const doctorId = doctorProfile?.id

    const [{ data: offs }, apptsRes] = await Promise.all([
      doctorId
        ? api.get(`/off-days/doctor/${doctorId}`)
        : Promise.resolve({ data: [] }),
      doctorId
        ? api.get(`/appointments/doctor/${doctorId}`).catch(() => ({ data: [] }))
        : Promise.resolve({ data: [] })
    ])
    offDays.value = (offs || []).sort((a, b) => (b.date || '').localeCompare(a.date || ''))
    appointments.value = apptsRes.data?.data || apptsRes.data || []
  } catch (e) {
    alerts.error(e.response?.data?.message || 'Failed to load off days')
  } finally {
    loading.value = false
  }
}

const stats = computed(() => {
  const total = offDays.value.length
  const pending = offDays.value.filter(o => o.status === 'pending').length
  const approved = offDays.value.filter(o => o.status === 'approved').length
  const rejected = offDays.value.filter(o => o.status === 'rejected').length
  return { total, pending, approved, rejected }
})

const upcomingItems = computed(() =>
  offDays.value.filter(o => o.date >= today.value).sort((a, b) => a.date.localeCompare(b.date))
)
const pastItems = computed(() =>
  offDays.value.filter(o => o.date < today.value).sort((a, b) => b.date.localeCompare(a.date))
)
const visibleItems = computed(() => {
  if (tab.value === 'upcoming') return upcomingItems.value
  if (tab.value === 'past') return pastItems.value
  return offDays.value.slice().sort((a, b) => b.date.localeCompare(a.date))
})

const nextOffDay = computed(() =>
  upcomingItems.value.find(o => o.status === 'approved') || upcomingItems.value[0] || null
)

const daysUntil = (dateStr) => {
  const a = new Date(dateStr + 'T00:00:00')
  const b = new Date(today.value + 'T00:00:00')
  return Math.round((a - b) / 86400000)
}

const monthAbbr = (d) => new Date(d + 'T00:00:00').toLocaleDateString(undefined, { month: 'short' }).toUpperCase()
const dayNum   = (d) => new Date(d + 'T00:00:00').getDate()
const weekday  = (d) => new Date(d + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'long' })

const datesInRange = (start, end) => {
  if (!start || !end || start > end) return []
  const out = []
  const d = new Date(start + 'T00:00:00')
  const last = new Date(end + 'T00:00:00')
  while (d <= last) {
    out.push(d.toISOString().split('T')[0])
    d.setDate(d.getDate() + 1)
  }
  return out
}

const selectedDates = computed(() => {
  if (form.mode === 'single') return form.date ? [form.date] : []
  return datesInRange(form.startDate, form.endDate)
})

const conflicts = computed(() => {
  if (!selectedDates.value.length) return []
  const set = new Set(selectedDates.value)
  return appointments.value.filter(a =>
    set.has(a.date) && (a.status || '').toLowerCase() !== 'cancelled'
  )
})

const alreadyRequested = computed(() => {
  if (!selectedDates.value.length) return []
  const set = new Set(selectedDates.value)
  return offDays.value.filter(o => set.has(o.date) && o.status !== 'rejected')
})

const openAdd = () => {
  form.mode = 'single'
  form.date = ''
  form.startDate = ''
  form.endDate = ''
  form.reasonType = ''
  form.reason = ''
  errors.date = ''
  errors.range = ''
  errors.reasonType = ''
  showAdd.value = true
  // Smooth scroll to the form on next tick
  setTimeout(() => {
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }, 50)
}

const applyPreset = (key) => {
  const d = new Date()
  const iso = (x) => x.toISOString().split('T')[0]
  if (key === 'tomorrow') {
    d.setDate(d.getDate() + 1)
    form.mode = 'single'; form.date = iso(d)
  } else if (key === 'nextMon') {
    const day = d.getDay()
    const add = ((8 - day) % 7) || 7
    d.setDate(d.getDate() + add)
    form.mode = 'single'; form.date = iso(d)
  } else if (key === 'weekend') {
    const day = d.getDay()
    const sat = new Date(d); sat.setDate(d.getDate() + ((6 - day + 7) % 7 || 7))
    const sun = new Date(sat); sun.setDate(sat.getDate() + 1)
    form.mode = 'range'; form.startDate = iso(sat); form.endDate = iso(sun)
  } else if (key === 'week') {
    const start = new Date(d); start.setDate(d.getDate() + 1)
    const end = new Date(start); end.setDate(start.getDate() + 4)
    form.mode = 'range'; form.startDate = iso(start); form.endDate = iso(end)
  }
}

const pickReason = (r) => {
  form.reasonType = r.value
  if (!form.reason) form.reason = r.label
  errors.reasonType = ''
}

const validate = () => {
  errors.date = ''
  errors.range = ''
  errors.reasonType = ''
  if (form.mode === 'single') {
    if (!form.date) { errors.date = 'Pick a date'; return false }
    if (form.date < today.value) { errors.date = 'Date must be in the future'; return false }
  } else {
    if (!form.startDate || !form.endDate) { errors.range = 'Pick start and end dates'; return false }
    if (form.startDate < today.value) { errors.range = 'Start date must be in the future'; return false }
    if (form.endDate < form.startDate) { errors.range = 'End date must be after start date'; return false }
    if (selectedDates.value.length > 30) { errors.range = 'Maximum range is 30 days'; return false }
  }
  if (!form.reasonType) { errors.reasonType = 'Choose a reason'; return false }
  return true
}

const submit = async () => {
  if (!validate()) return
  submitting.value = true
  try {
    const reasonText = form.reason?.trim() || REASONS.find(r => r.value === form.reasonType)?.label || ''
    const dates = selectedDates.value
    const results = await Promise.allSettled(dates.map(date =>
      api.post('/off-days', { user_id: auth.user.id, date, reason: reasonText })
    ))
    const ok = results.filter(r => r.status === 'fulfilled').length
    const failed = results.length - ok
    if (ok > 0) alerts.success(`${ok} off-day request${ok > 1 ? 's' : ''} submitted`)
    if (failed > 0) alerts.error(`${failed} request${failed > 1 ? 's' : ''} skipped (already exists)`)
    if (ok > 0) {
      showAdd.value = false
      await fetchOffDays()
    }
  } catch (e) {
    alerts.error(e.response?.data?.message || 'Failed to submit request')
  } finally {
    submitting.value = false
  }
}

const askDelete = (id) => { targetId.value = id; showDelete.value = true }
const confirmDelete = async () => {
  try {
    await api.delete(`/off-days/${targetId.value}`)
    alerts.success('Request cancelled')
    showDelete.value = false
    await fetchOffDays()
  } catch (e) {
    alerts.error(e.response?.data?.message || 'Failed to cancel')
  }
}

const fmt = (d) => new Date(d).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })
const fmtShort = (d) => new Date(d).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })

watch(() => form.mode, () => {
  errors.date = ''; errors.range = ''
})

onMounted(fetchOffDays)
</script>

<template>
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="h-1 w-full rounded-full bg-gradient-to-r from-blue-600 to-indigo-400 mb-6"></div>
    <!-- Hero header -->
    <div
      class="rounded-2xl text-white p-6 sm:p-8 mb-6 relative overflow-hidden"
      style="background-image: linear-gradient(135deg, #1A365D 0%, #2B6CB0 60%, #4299E1 100%)"
    >
      <div class="absolute -right-16 -top-16 w-72 h-72 rounded-full bg-white/10 blur-2xl"></div>
      <div class="absolute -left-20 -bottom-24 w-80 h-80 rounded-full bg-white/10 blur-3xl"></div>

      <div class="relative flex items-start justify-between gap-4 flex-wrap">
        <div class="flex-1 min-w-0">
          <div class="inline-flex items-center gap-2 bg-white/15 backdrop-blur rounded-full px-3 py-1 text-xs font-medium mb-3">
            <Icon name="clock" :size="12" /> Time Off Management
          </div>
          <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight">Off Days</h1>
          <p class="text-clinic-100/90 mt-1 text-sm">Submit dates you'll be unavailable. Admin approval is required.</p>
          <button class="mt-4 bg-white text-clinic-700 hover:bg-clinic-50 font-semibold text-sm px-4 py-2 rounded-lg inline-flex items-center gap-2 shadow-sm transition" @click="showAdd ? showAdd = false : openAdd()">
            <Icon :name="showAdd ? 'x' : 'plus'" :size="16" />
            {{ showAdd ? 'Close Form' : 'Request Off Day' }}
          </button>
        </div>

        <div v-if="nextOffDay" class="relative bg-white/15 backdrop-blur rounded-xl p-4 min-w-[220px]">
          <p class="text-xs uppercase tracking-wider text-white/70 mb-2">Next Off Day</p>
          <div class="flex items-center gap-3">
            <div class="bg-white text-clinic-700 rounded-lg overflow-hidden text-center w-14 flex-shrink-0">
              <div class="bg-clinic-600 text-white text-[10px] font-bold py-0.5">{{ monthAbbr(nextOffDay.date) }}</div>
              <div class="text-2xl font-bold leading-tight py-1">{{ dayNum(nextOffDay.date) }}</div>
            </div>
            <div class="min-w-0">
              <p class="font-semibold text-sm truncate">{{ weekday(nextOffDay.date) }}</p>
              <p class="text-xs text-white/80">
                <template v-if="daysUntil(nextOffDay.date) === 0">Today</template>
                <template v-else-if="daysUntil(nextOffDay.date) === 1">Tomorrow</template>
                <template v-else>in {{ daysUntil(nextOffDay.date) }} days</template>
              </p>
              <span class="mt-1 inline-flex items-center gap-1 text-[10px] font-semibold px-1.5 py-0.5 rounded-full"
                :class="nextOffDay.status === 'approved' ? 'bg-emerald-400/30' : 'bg-amber-400/30'">
                {{ nextOffDay.status }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Inline request form -->
    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-2"
    >
      <div v-if="showAdd" class="card mb-6 ring-1 ring-clinic-100">
        <div class="flex items-start justify-between gap-3 mb-4">
          <div>
            <h2 class="text-base font-semibold text-gray-900 inline-flex items-center gap-2">
              <span class="w-7 h-7 rounded-lg bg-clinic-50 text-clinic-600 inline-flex items-center justify-center"><Icon name="plus" :size="14" /></span>
              New Off-Day Request
            </h2>
            <p class="text-xs text-gray-500 mt-1">Pick one or more dates, choose a reason, and submit for admin approval.</p>
          </div>
          <button class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg hover:bg-gray-100" title="Close" @click="showAdd=false">
            <Icon name="x" :size="16" />
          </button>
        </div>

        <div class="space-y-5">
          <!-- Mode toggle -->
          <div class="inline-flex p-1 bg-gray-100 rounded-lg">
            <button type="button"
              class="px-3 py-1.5 rounded-md text-sm font-medium inline-flex items-center gap-1.5 transition"
              :class="form.mode === 'single' ? 'bg-blue-600 text-white shadow-sm' : 'bg-white text-gray-600 border border-gray-200'"
              @click="form.mode = 'single'"
            ><Icon name="calendar" :size="14" /> Single Day</button>
            <button type="button"
              class="px-3 py-1.5 rounded-md text-sm font-medium inline-flex items-center gap-1.5 transition"
              :class="form.mode === 'range' ? 'bg-blue-600 text-white shadow-sm' : 'bg-white text-gray-600 border border-gray-200'"
              @click="form.mode = 'range'"
            ><Icon name="clock" :size="14" /> Date Range</button>
          </div>

          <!-- Quick presets -->
          <div>
            <p class="text-xs uppercase tracking-wide text-gray-500 mb-2">Quick Pick</p>
            <div class="flex flex-wrap gap-2">
              <button type="button" class="chip hover:bg-clinic-50 hover:text-clinic-700 cursor-pointer" @click="applyPreset('tomorrow')">Tomorrow</button>
              <button type="button" class="chip hover:bg-clinic-50 hover:text-clinic-700 cursor-pointer" @click="applyPreset('nextMon')">Next Monday</button>
              <button type="button" class="chip hover:bg-clinic-50 hover:text-clinic-700 cursor-pointer" @click="applyPreset('weekend')">This Weekend</button>
              <button type="button" class="chip hover:bg-clinic-50 hover:text-clinic-700 cursor-pointer" @click="applyPreset('week')">Next Week (Mon–Fri)</button>
            </div>
          </div>

          <!-- Date picker(s) -->
          <div v-if="form.mode === 'single'">
            <label class="label">Date</label>
            <input v-model="form.date" type="date" class="input max-w-xs" :min="today" />
            <p v-if="errors.date" class="field-error">{{ errors.date }}</p>
          </div>
          <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-xl">
            <div>
              <label class="label">From</label>
              <input v-model="form.startDate" type="date" class="input" :min="today" />
            </div>
            <div>
              <label class="label">To</label>
              <input v-model="form.endDate" type="date" class="input" :min="form.startDate || today" />
            </div>
            <p v-if="errors.range" class="field-error sm:col-span-2">{{ errors.range }}</p>
          </div>

          <!-- Reason categories -->
          <div>
            <label class="label">Reason</label>
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
              <button
                v-for="r in REASONS" :key="r.value" type="button"
                class="flex flex-col items-center gap-1 p-2.5 rounded-xl border text-xs font-medium transition"
                :class="form.reasonType === r.value ? 'border-blue-400 bg-blue-50 text-blue-700 ring-2 ring-blue-200' : 'border-gray-200 bg-white text-gray-600 hover:border-blue-200'"
                @click="pickReason(r)"
              >
                <Icon :name="r.icon" :size="18" />
                <span>{{ r.label }}</span>
              </button>
            </div>
            <p v-if="errors.reasonType" class="field-error">{{ errors.reasonType }}</p>
          </div>

          <!-- Notes -->
          <div>
            <label class="label">Notes <span class="text-gray-400 font-normal">(optional)</span></label>
            <textarea v-model="form.reason" rows="2" class="input" placeholder="Add details for the admin (e.g. conference name, doctor's note)..."></textarea>
          </div>

          <!-- Summary -->
          <div v-if="selectedDates.length" class="bg-clinic-50 ring-1 ring-clinic-100 rounded-xl p-3">
            <div class="flex items-center justify-between mb-1">
              <span class="text-xs uppercase tracking-wide text-clinic-700 font-semibold">Summary</span>
              <span class="chip !bg-white !text-clinic-700">{{ selectedDates.length }} day{{ selectedDates.length > 1 ? 's' : '' }}</span>
            </div>
            <p class="text-sm text-gray-700">
              <template v-if="form.mode === 'single'">{{ fmt(form.date) }}</template>
              <template v-else>{{ fmtShort(form.startDate) }} → {{ fmtShort(form.endDate) }}</template>
            </p>
          </div>

          <!-- Conflict warnings -->
          <div v-if="conflicts.length" class="bg-amber-50 ring-1 ring-amber-200 rounded-lg px-3 py-2 text-xs text-amber-800 flex items-start gap-2">
            <Icon name="clock" :size="14" class="mt-0.5 flex-shrink-0" />
            <div>
              <strong>{{ conflicts.length }} appointment{{ conflicts.length > 1 ? 's' : '' }}</strong> on selected date{{ selectedDates.length > 1 ? 's' : '' }}.
              Approval will require rescheduling these patients.
            </div>
          </div>

          <div v-if="alreadyRequested.length" class="bg-rose-50 ring-1 ring-rose-200 rounded-lg px-3 py-2 text-xs text-rose-700 flex items-start gap-2">
            <Icon name="x" :size="14" class="mt-0.5 flex-shrink-0" />
            <div>
              You already have request{{ alreadyRequested.length > 1 ? 's' : '' }} for: 
              <strong>{{ alreadyRequested.map(o => fmtShort(o.date)).join(', ') }}</strong>. These will be skipped.
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
            <button class="btn-secondary" @click="showAdd=false">Cancel</button>
            <button class="btn text-white shadow-sm" style="background-image: linear-gradient(135deg, #2B6CB0 0%, #1A365D 100%)" :disabled="submitting || !selectedDates.length" @click="submit">
              <LoadingSpinner v-if="submitting" size="sm" />
              <Icon v-else name="check" :size="16" />
              <span>{{ submitting ? 'Submitting...' : `Submit ${selectedDates.length || ''} Request${selectedDates.length > 1 ? 's' : ''}` }}</span>
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- Stat cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="stat-card border-l-4 border-l-blue-400">
        <div class="stat-icon bg-blue-50 text-blue-600"><Icon name="calendar" :size="20" /></div>
        <div>
          <p class="text-xs uppercase tracking-wide text-gray-500">Total</p>
          <p class="text-2xl font-semibold text-gray-900">{{ stats.total }}</p>
        </div>
      </div>
      <div class="stat-card border-l-4 border-l-amber-400">
        <div class="stat-icon !bg-amber-50 !text-amber-600"><Icon name="clock" :size="20" /></div>
        <div>
          <p class="text-xs uppercase tracking-wide text-gray-500">Pending</p>
          <p class="text-2xl font-semibold text-gray-900">{{ stats.pending }}</p>
        </div>
      </div>
      <div class="stat-card border-l-4 border-l-emerald-400">
        <div class="stat-icon !bg-emerald-50 !text-emerald-600"><Icon name="check" :size="20" /></div>
        <div>
          <p class="text-xs uppercase tracking-wide text-gray-500">Approved</p>
          <p class="text-2xl font-semibold text-gray-900">{{ stats.approved }}</p>
        </div>
      </div>
      <div class="stat-card border-l-4 border-l-rose-300">
        <div class="stat-icon !bg-rose-50 !text-rose-400"><Icon name="x" :size="20" /></div>
        <div>
          <p class="text-xs uppercase tracking-wide text-gray-500">Rejected</p>
          <p class="text-2xl font-semibold text-gray-900">{{ stats.rejected }}</p>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
      <div class="inline-flex p-1 bg-gray-100 rounded-lg">
        <button
          class="px-4 py-1.5 rounded-md text-sm font-medium inline-flex items-center gap-1.5 transition"
          :class="tab === 'upcoming' ? 'bg-white text-clinic-700 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
          @click="tab = 'upcoming'"
        >Upcoming <span class="text-xs opacity-70">{{ upcomingItems.length }}</span></button>
        <button
          class="px-4 py-1.5 rounded-md text-sm font-medium inline-flex items-center gap-1.5 transition"
          :class="tab === 'past' ? 'bg-white text-clinic-700 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
          @click="tab = 'past'"
        >Past <span class="text-xs opacity-70">{{ pastItems.length }}</span></button>
        <button
          class="px-4 py-1.5 rounded-md text-sm font-medium inline-flex items-center gap-1.5 transition"
          :class="tab === 'all' ? 'bg-white text-clinic-700 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
          @click="tab = 'all'"
        >All <span class="text-xs opacity-70">{{ stats.total }}</span></button>
      </div>
    </div>

    <!-- List -->
    <div v-if="loading" class="card py-16 flex justify-center">
      <LoadingSpinner label="Loading..." />
    </div>
    <div v-else-if="visibleItems.length === 0" class="card py-16 text-center">
      <div class="inline-flex w-14 h-14 rounded-2xl bg-clinic-50 text-clinic-600 items-center justify-center mb-3">
        <Icon name="calendar" :size="24" />
      </div>
      <p class="text-gray-700 font-medium">
        <template v-if="tab === 'upcoming'">No upcoming off-days</template>
        <template v-else-if="tab === 'past'">No past off-days</template>
        <template v-else>No off-day requests yet</template>
      </p>
      <p class="text-sm text-gray-500 mt-1">Click "Request Off Day" to submit one.</p>
    </div>
    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div
        v-for="o in visibleItems" :key="o.id"
        class="card card-hover relative"
        :class="{
          '!border-l-4 !border-l-emerald-500': o.status === 'approved',
          '!border-l-4 !border-l-amber-500':   o.status === 'pending',
          '!border-l-4 !border-l-rose-500':    o.status === 'rejected'
        }"
      >
        <div class="flex items-start gap-4">
          <!-- Calendar block -->
          <div class="flex-shrink-0 rounded-xl overflow-hidden text-center w-16 ring-1 ring-gray-200">
            <div
              class="text-white text-[10px] font-bold py-1"
              :class="o.status === 'approved' ? 'bg-emerald-500' : o.status === 'rejected' ? 'bg-rose-500' : 'bg-amber-500'"
            >{{ monthAbbr(o.date) }}</div>
            <div class="bg-white text-gray-900 py-1.5">
              <div class="text-2xl font-bold leading-none">{{ dayNum(o.date) }}</div>
              <div class="text-[10px] uppercase text-gray-500 mt-0.5">{{ weekday(o.date).slice(0,3) }}</div>
            </div>
          </div>

          <!-- Body -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between gap-2 mb-1">
              <p class="font-semibold text-gray-900 truncate">{{ weekday(o.date) }}</p>
              <StatusBadge :status="o.status" />
            </div>
            <p class="text-xs text-gray-500">{{ fmt(o.date) }}</p>

            <p class="mt-2 text-sm text-gray-700">
              <Icon name="edit" :size="12" class="inline -mt-0.5 text-gray-400" />
              {{ o.reason || 'No reason provided' }}
            </p>

            <div class="mt-3 flex items-center justify-between">
              <span class="text-xs text-gray-500">
                <template v-if="daysUntil(o.date) > 0">
                  <Icon name="clock" :size="12" class="inline -mt-0.5" />
                  in {{ daysUntil(o.date) }} day{{ daysUntil(o.date) > 1 ? 's' : '' }}
                </template>
                <template v-else-if="daysUntil(o.date) === 0">
                  <span class="text-clinic-600 font-medium">Today</span>
                </template>
                <template v-else>
                  {{ Math.abs(daysUntil(o.date)) }} day{{ Math.abs(daysUntil(o.date)) > 1 ? 's' : '' }} ago
                </template>
              </span>

              <button
                v-if="o.status === 'pending'"
                class="text-xs font-medium text-rose-600 hover:bg-rose-50 px-2 py-1 rounded-md inline-flex items-center gap-1"
                @click="askDelete(o.id)"
              >
                <Icon name="trash" :size="12" /> Cancel
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <Modal v-if="showDelete" title="Cancel Request" size="sm" @close="showDelete=false">
      <p class="text-sm text-gray-600">Are you sure you want to cancel this off-day request?</p>
      <template #footer>
        <button class="btn-secondary" @click="showDelete=false">Keep</button>
        <button class="btn-danger" @click="confirmDelete">Cancel Request</button>
      </template>
    </Modal>
  </div>
</template>
