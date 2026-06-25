<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../../api/axios'
import { useAuthStore } from '../../stores/authStore'
import { useAlertStore } from '../../stores/alertStore'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import Avatar from '../../components/Avatar.vue'
import Icon from '../../components/Icon.vue'

const router = useRouter()
const auth = useAuthStore()
const alerts = useAlertStore()

const doctors = ref([])
const loadingDoctors = ref(false)
const loadingSlots = ref(false)
const submitting = ref(false)

const form = reactive({ doctor_id: '', appointment_type: '', date: '', time: '', notes: '' })
const errors = reactive({ doctor_id: '', appointment_type: '', date: '', time: '' })

const today = computed(() => new Date().toISOString().split('T')[0])
const selectedDoctor = computed(() => doctors.value.find(d => d.id === form.doctor_id) || null)

// ── Appointment types ──────────────────────────────────────────────────────────
const TYPES = [
  { key: 'Quick Consultation / Follow-up', duration: 15, icon: 'clock',       desc: '15 min · Short follow-up or quick query' },
  { key: 'General Checkup',               duration: 30, icon: 'stethoscope', desc: '30 min · Routine health checkup' },
  { key: 'Detailed Examination',          duration: 45, icon: 'search',      desc: '45 min · In-depth examination' },
  { key: 'New Patient / Full Assessment', duration: 60, icon: 'user',        desc: '60 min · Comprehensive new-patient assessment' },
]
const selectedType = computed(() => TYPES.find(t => t.key === form.appointment_type) || null)
const duration = computed(() => selectedType.value?.duration ?? 30)

// ── Availability data from backend ─────────────────────────────────────────────
const availData  = ref(null)
const availError = ref(false)

// ── Time helpers ──────────────────────────────────────────────────────────────
const timeToMin = (t) => { const [h, m] = t.split(':').map(Number); return h * 60 + m }
const minToTime = (min) => `${String(Math.floor(min / 60)).padStart(2, '0')}:${String(min % 60).padStart(2, '0')}`
const formatTime = (t) => {
  const [h, m] = t.split(':').map(Number)
  return `${h % 12 || 12}:${String(m).padStart(2, '0')} ${h >= 12 ? 'PM' : 'AM'}`
}

// ── Generate selectable time slots ────────────────────────────────────────────
const slots = computed(() => {
  if (!availData.value || availData.value.is_off_day || !availData.value.works_this_day) return []
  if (!selectedType.value) return []
  const { work_start, work_end, booked } = availData.value
  const d       = duration.value
  const buffer  = 5
  const startMin = timeToMin(work_start)
  const endMin   = timeToMin(work_end)
  const result   = []
  for (let t = startMin; t + d <= endMin; t += 15) {
    const slotEnd  = t + d
    const isTaken  = booked.some(b => {
      const bStart = timeToMin(b.start)
      const bEnd   = timeToMin(b.end)
      return t < bEnd + buffer && slotEnd > bStart - buffer
    })
    result.push({ time: minToTime(t), taken: isTaken })
  }
  return result
})

const noSlotsLeft = computed(() => availData.value && !availData.value.is_off_day && availData.value.works_this_day && selectedType.value && slots.value.length > 0 && slots.value.every(s => s.taken))

// ── Fetch availability when doctor + date change ───────────────────────────────
const fetchAvailability = async () => {
  form.time = ''
  availError.value = false
  if (!form.doctor_id || !form.date) { availData.value = null; return }
  loadingSlots.value = true
  try {
    const { data } = await api.get(`/doctors/${form.doctor_id}/availability`, { params: { date: form.date } })
    availData.value = data
  } catch {
    availData.value = null
    availError.value = true
    alerts.error('Failed to load doctor availability. Make sure the database is up to date.')
  } finally {
    loadingSlots.value = false
  }
}

watch([() => form.doctor_id, () => form.date], fetchAvailability)
// Reset selected slot when type changes (duration changes → slots shift)
watch(() => form.appointment_type, () => { form.time = '' })

// ── Doctors ────────────────────────────────────────────────────────────────────
const fetchDoctors = async () => {
  loadingDoctors.value = true
  try {
    const { data } = await api.get('/doctors')
    doctors.value = data.data || data || []
  } catch {
    alerts.error('Failed to load doctors list')
  } finally {
    loadingDoctors.value = false
  }
}

// ── Validation ────────────────────────────────────────────────────────────────
const validate = () => {
  errors.doctor_id = ''; errors.appointment_type = ''; errors.date = ''; errors.time = ''
  let ok = true
  if (!form.doctor_id)         { errors.doctor_id         = 'Please select a doctor'; ok = false }
  if (!form.appointment_type)  { errors.appointment_type  = 'Please select an appointment type'; ok = false }
  if (!form.date)              { errors.date              = 'Date is required'; ok = false }
  else if (form.date < today.value) { errors.date         = 'Date cannot be in the past'; ok = false }
  if (!form.time)              { errors.time              = 'Please select a time slot'; ok = false }
  return ok
}

// ── Submit ────────────────────────────────────────────────────────────────────
const submit = async () => {
  if (!validate()) return
  submitting.value = true
  try {
    await api.post('/appointments', {
      doctor_id:        form.doctor_id,
      patient_id:       auth.user.id,
      date:             form.date,
      time:             form.time,
      appointment_type: form.appointment_type,
      notes:            form.notes,
    })
    alerts.success('Appointment booked successfully!')
    router.push('/appointments')
  } catch (e) {
    alerts.error(e?.response?.data?.error || e?.response?.data?.message || 'Failed to book appointment')
  } finally {
    submitting.value = false
  }
}

onMounted(fetchDoctors)
</script>

<template>
  <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="h-1 w-full rounded-full bg-gradient-to-r from-emerald-600 to-teal-400 mb-6"></div>

    <div class="flex items-center gap-4 mb-6">
      <div class="w-12 h-12 rounded-xl bg-emerald-600 text-white flex items-center justify-center shadow-sm">
        <Icon name="plus" :size="22" />
      </div>
      <div>
        <h1 class="text-2xl font-semibold text-gray-900">Book an Appointment</h1>
        <p class="text-sm text-gray-500">Select a doctor, type, date and available time slot.</p>
      </div>
    </div>

    <form class="space-y-5" @submit.prevent="submit" novalidate>

      <!-- ══════════════════════════════════════════
           STEP 1 — Select Doctor
      ═══════════════════════════════════════════ -->
      <div class="card space-y-3">
        <div class="flex items-center gap-2 mb-1">
          <div class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs font-bold">1</div>
          <h2 class="font-semibold text-gray-800">Select Doctor</h2>
        </div>

        <div v-if="loadingDoctors" class="py-4 flex justify-center">
          <LoadingSpinner size="sm" label="Loading doctors..." />
        </div>
        <div v-else-if="doctors.length === 0" class="text-sm text-gray-500 py-4 text-center">No doctors available.</div>
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <button
            v-for="d in doctors" :key="d.id" type="button"
            class="card !p-3 text-left transition relative"
            :class="form.doctor_id === d.id
              ? '!border-emerald-500 !bg-emerald-50 ring-2 ring-emerald-200'
              : 'border-gray-200 bg-white hover:border-emerald-300 hover:bg-emerald-50/30'"
            @click="form.doctor_id = d.id; form.time = ''"
          >
            <div v-if="form.doctor_id === d.id" class="absolute top-2 right-2 w-5 h-5 rounded-full bg-emerald-500 text-white flex items-center justify-center">
              <Icon name="check" :size="12" />
            </div>
            <div class="flex items-center gap-3">
              <Avatar :name="d.name || d.user?.name" size="md" />
              <div class="flex-1 min-w-0">
                <p class="font-medium truncate" :class="form.doctor_id === d.id ? 'text-emerald-700' : 'text-gray-900'">
                  Dr. {{ d.name || d.user?.name }}
                </p>
                <p class="text-xs truncate" :class="form.doctor_id === d.id ? 'text-emerald-600' : 'text-gray-500'">{{ d.specialization }}</p>
                <p v-if="d.availability" class="text-xs text-gray-400 mt-0.5 truncate">{{ d.availability }}</p>
              </div>
            </div>
          </button>
        </div>
        <p v-if="errors.doctor_id" class="field-error">{{ errors.doctor_id }}</p>
      </div>

      <!-- ══════════════════════════════════════════
           STEP 2 — Appointment Type
      ═══════════════════════════════════════════ -->
      <div class="card space-y-3">
        <div class="flex items-center gap-2 mb-1">
          <div class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs font-bold">2</div>
          <h2 class="font-semibold text-gray-800">Appointment Type</h2>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <button
            v-for="t in TYPES" :key="t.key" type="button"
            class="card !p-3 text-left transition"
            :class="form.appointment_type === t.key
              ? '!border-emerald-500 !bg-emerald-50 ring-2 ring-emerald-200'
              : 'border-gray-200 bg-white hover:border-emerald-300 hover:bg-emerald-50/30'"
            @click="form.appointment_type = t.key"
          >
            <div class="flex items-start gap-3">
              <div :class="['w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 transition', form.appointment_type === t.key ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-500']">
                <Icon :name="t.icon" :size="18" />
              </div>
              <div class="flex-1 min-w-0">
                <p class="font-medium text-sm leading-tight" :class="form.appointment_type === t.key ? 'text-emerald-700' : 'text-gray-900'">{{ t.key }}</p>
                <p class="text-xs text-gray-500 mt-0.5 leading-snug">{{ t.desc }}</p>
                <span :class="['inline-flex mt-1.5 items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full', form.appointment_type === t.key ? 'bg-emerald-200 text-emerald-800' : 'bg-gray-100 text-gray-600']">
                  <Icon name="clock" :size="10" /> {{ t.duration }} min
                </span>
              </div>
            </div>
          </button>
        </div>
        <p v-if="errors.appointment_type" class="field-error">{{ errors.appointment_type }}</p>
      </div>

      <!-- ══════════════════════════════════════════
           STEP 3 — Date
      ═══════════════════════════════════════════ -->
      <div class="card space-y-3">
        <div class="flex items-center gap-2 mb-1">
          <div class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs font-bold">3</div>
          <h2 class="font-semibold text-gray-800">Select Date</h2>
        </div>
        <div class="max-w-xs">
          <input v-model="form.date" type="date" class="input focus:!ring-emerald-400/40 focus:!border-emerald-400" :min="today" />
          <p v-if="errors.date" class="field-error">{{ errors.date }}</p>
        </div>
      </div>

      <!-- ══════════════════════════════════════════
           STEP 4 — Time Slot Grid
      ═══════════════════════════════════════════ -->
      <div v-if="form.doctor_id && form.date" class="card space-y-3">
        <div class="flex items-center gap-2 mb-1">
          <div class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs font-bold">4</div>
          <h2 class="font-semibold text-gray-800">Select Time Slot</h2>
          <span v-if="selectedType" class="ml-1 text-xs text-gray-400">({{ duration }}-min appointment)</span>
        </div>

        <!-- Loading -->
        <div v-if="loadingSlots" class="py-8 flex justify-center">
          <LoadingSpinner size="sm" label="Loading available slots..." />
        </div>

        <!-- Fetch error -->
        <div v-else-if="availError" class="bg-rose-50 border border-rose-200 rounded-xl px-4 py-4 flex items-start gap-3 text-rose-700">
          <Icon name="x" :size="18" class="mt-0.5 flex-shrink-0" />
          <div>
            <p class="font-semibold text-sm">Could not load availability</p>
            <p class="text-xs mt-0.5">Failed to fetch the doctor's schedule. Please ensure the database has been updated, then try selecting a different date to retry.</p>
          </div>
        </div>

        <!-- Off day -->
        <div v-else-if="availData?.is_off_day" class="bg-rose-50 border border-rose-200 rounded-xl px-4 py-4 flex items-start gap-3 text-rose-700">
          <Icon name="x" :size="18" class="mt-0.5 flex-shrink-0" />
          <div>
            <p class="font-semibold text-sm">Approved Day Off</p>
            <p class="text-xs mt-0.5">This doctor has an approved day off on the selected date. Please choose another date.</p>
          </div>
        </div>

        <!-- Not a working day -->
        <div v-else-if="availData && !availData.works_this_day" class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-4 flex items-start gap-3 text-amber-700">
          <Icon name="clock" :size="18" class="mt-0.5 flex-shrink-0" />
          <div>
            <p class="font-semibold text-sm">Not a working day</p>
            <p class="text-xs mt-0.5">The doctor does not work on this day. Please choose a weekday within their working schedule.</p>
          </div>
        </div>

        <!-- No availability data yet (before first successful fetch) -->
        <div v-else-if="!availData" class="bg-gray-50 rounded-xl px-4 py-5 text-center text-sm text-gray-400">
          <Icon name="calendar" :size="22" class="mx-auto mb-2 text-gray-300" />
          Checking doctor's availability...
        </div>

        <!-- Type not selected yet (data loaded, awaiting type selection) -->
        <div v-else-if="!form.appointment_type" class="bg-gray-50 rounded-xl px-4 py-5 text-center text-sm text-gray-400">
          <Icon name="clock" :size="22" class="mx-auto mb-2 text-gray-300" />
          Select an appointment type above to see available time slots.
        </div>

        <!-- Fully booked -->
        <div v-else-if="noSlotsLeft" class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-6 text-center text-sm text-gray-500">
          <Icon name="calendar" :size="24" class="mx-auto mb-2 text-gray-300" />
          <p class="font-medium">No slots available</p>
          <p class="text-xs text-gray-400 mt-0.5">This doctor is fully booked on the selected date. Please try another date.</p>
        </div>

        <!-- Slot grid -->
        <div v-else-if="slots.length > 0">
          <!-- Working hours info bar -->
          <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs mb-3">
            <span class="text-gray-500">
              Working hours:
              <span class="font-semibold text-gray-700">
                {{ formatTime(availData.work_start) }} – {{ formatTime(availData.work_end) }}
              </span>
            </span>
            <span v-if="availData.booked.length" class="text-amber-600 font-medium">
              {{ availData.booked.length }} slot(s) already booked
            </span>
          </div>

          <!-- Time buttons -->
          <div class="grid grid-cols-4 sm:grid-cols-6 gap-2">
            <button
              v-for="slot in slots" :key="slot.time" type="button"
              :disabled="slot.taken"
              :title="slot.taken ? 'Already booked (includes 5-min buffer)' : `Book at ${formatTime(slot.time)}`"
              :class="[
                'py-2 px-1 rounded-lg text-xs font-semibold border transition text-center select-none',
                slot.taken
                  ? 'bg-gray-100 text-gray-300 border-gray-100 cursor-not-allowed line-through'
                  : form.time === slot.time
                    ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm ring-2 ring-emerald-300'
                    : 'bg-white text-gray-700 border-gray-200 hover:border-emerald-400 hover:bg-emerald-50 hover:text-emerald-700 cursor-pointer'
              ]"
              @click="!slot.taken && (form.time = slot.time)"
            >
              {{ formatTime(slot.time) }}
            </button>
          </div>

          <!-- Selected slot confirmation -->
          <div v-if="form.time" class="mt-3 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-2.5 flex items-center gap-3">
            <Icon name="check" :size="16" class="text-emerald-600 flex-shrink-0" />
            <span class="text-sm font-medium text-emerald-800">
              Selected: <strong>{{ formatTime(form.time) }}</strong>
              <span class="text-emerald-600 font-normal"> · ends {{ formatTime(minToTime(timeToMin(form.time) + duration)) }}</span>
            </span>
          </div>

          <p v-if="errors.time" class="field-error mt-2">{{ errors.time }}</p>

          <!-- Legend -->
          <div class="flex items-center gap-5 text-xs text-gray-400 mt-3">
            <span class="flex items-center gap-1.5">
              <span class="w-3 h-3 rounded bg-emerald-600 inline-block"></span> Selected
            </span>
            <span class="flex items-center gap-1.5">
              <span class="w-3 h-3 rounded bg-white border border-gray-300 inline-block"></span> Available
            </span>
            <span class="flex items-center gap-1.5">
              <span class="w-3 h-3 rounded bg-gray-100 inline-block"></span> Taken
            </span>
          </div>
        </div>
      </div>

      <!-- ══════════════════════════════════════════
           STEP 5 — Notes
      ═══════════════════════════════════════════ -->
      <div class="card space-y-3">
        <div class="flex items-center gap-2 mb-1">
          <div class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs font-bold">5</div>
          <h2 class="font-semibold text-gray-800">Notes <span class="text-gray-400 font-normal text-sm">(optional)</span></h2>
        </div>
        <textarea v-model="form.notes" rows="3" class="input" placeholder="Briefly describe the reason for your visit..."></textarea>
      </div>

      <!-- Submit -->
      <div class="flex justify-end gap-2 pt-1">
        <button type="button" class="btn-secondary !border-emerald-200 !text-emerald-700 hover:!bg-emerald-50" @click="router.back()">Cancel</button>
        <button
          type="submit"
          class="btn text-white shadow-sm"
          style="background-image: linear-gradient(135deg, #059669 0%, #064E3B 100%)"
          :disabled="submitting"
        >
          <LoadingSpinner v-if="submitting" size="sm" />
          <Icon v-else name="calendar" :size="16" />
          {{ submitting ? 'Booking...' : 'Book Appointment' }}
        </button>
      </div>
    </form>
  </div>
</template>
