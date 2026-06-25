<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import api from '../../api/axios'
import { useAuthStore } from '../../stores/authStore'
import { useAlertStore } from '../../stores/alertStore'
import StatusBadge from '../../components/StatusBadge.vue'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import Avatar from '../../components/Avatar.vue'
import Icon from '../../components/Icon.vue'

const auth = useAuthStore()
const router = useRouter()
const alerts = useAlertStore()

const appointments = ref([])
const loading = ref(false)

const stats = computed(() => {
  const total = appointments.value.length
  const upcoming = appointments.value.filter(a =>
    a.status !== 'cancelled' && new Date(`${a.date}T${a.time || '00:00'}`) >= new Date(new Date().toDateString())
  ).length
  const cancelled = appointments.value.filter(a => a.status === 'cancelled').length
  const confirmed = appointments.value.filter(a => a.status === 'confirmed').length
  return { total, upcoming, cancelled, confirmed }
})

const upcoming = computed(() =>
  appointments.value
    .filter(a => a.status !== 'cancelled')
    .sort((a, b) => new Date(`${a.date}T${a.time||'00:00'}`) - new Date(`${b.date}T${b.time||'00:00'}`))
    .slice(0, 5)
)

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'Good morning'
  if (h < 18) return 'Good afternoon'
  return 'Good evening'
})

const fetch = async () => {
  loading.value = true
  try {
    const { data } = await api.get(`/appointments/patient/${auth.user.id}`)
    appointments.value = data.data || data || []
  } catch (e) {
    alerts.error(e?.response?.data?.message || 'Failed to load appointments')
  } finally {
    loading.value = false
  }
}

onMounted(fetch)
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Hero -->
    <div
      class="rounded-2xl text-white p-6 sm:p-8 mb-6 relative overflow-hidden"
      style="background-image: linear-gradient(135deg, #064E3B 0%, #059669 60%, #34D399 100%)"
    >
      <div class="absolute -right-20 -top-20 w-72 h-72 rounded-full bg-white/10 blur-2xl"></div>
      <div class="absolute -left-16 -bottom-24 w-72 h-72 rounded-full bg-white/10 blur-3xl"></div>
      <div class="relative inline-flex items-center gap-1.5 bg-white/20 backdrop-blur text-white text-xs font-semibold px-3 py-1 rounded-full mb-3">
        <Icon name="user" :size="12" /> Patient Portal
      </div>
      <div class="relative flex items-start justify-between gap-4 flex-wrap">
        <div>
          <p class="text-emerald-100/80 text-sm">{{ greeting }},</p>
          <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight">{{ auth.user?.name }}</h1>
          <p class="text-emerald-100/80 text-sm mt-1 max-w-md">
            Here's an overview of your upcoming appointments and recent activity.
          </p>
        </div>
        <RouterLink to="/book" class="bg-white text-emerald-700 hover:bg-emerald-50 btn !px-5">
          <Icon name="plus" :size="16" /> Book New Appointment
        </RouterLink>
      </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="stat-card border-t-4 border-t-emerald-400">
        <div class="stat-icon bg-emerald-50 text-emerald-600"><Icon name="calendar" :size="22" /></div>
        <div>
          <p class="text-xs text-gray-500 uppercase tracking-wider">Total</p>
          <p class="text-2xl font-semibold text-gray-900 mt-0.5">{{ stats.total }}</p>
        </div>
      </div>
      <div class="stat-card border-t-4 border-t-amber-400">
        <div class="stat-icon bg-amber-50 text-amber-600"><Icon name="clock" :size="22" /></div>
        <div>
          <p class="text-xs text-gray-500 uppercase tracking-wider">Upcoming</p>
          <p class="text-2xl font-semibold text-gray-900 mt-0.5">{{ stats.upcoming }}</p>
        </div>
      </div>
      <div class="stat-card border-t-4 border-t-emerald-300">
        <div class="stat-icon bg-emerald-50 text-emerald-600"><Icon name="check" :size="22" /></div>
        <div>
          <p class="text-xs text-gray-500 uppercase tracking-wider">Confirmed</p>
          <p class="text-2xl font-semibold text-gray-900 mt-0.5">{{ stats.confirmed }}</p>
        </div>
      </div>
      <div class="stat-card border-t-4 border-t-rose-300">
        <div class="stat-icon bg-rose-50 text-rose-600"><Icon name="x" :size="22" /></div>
        <div>
          <p class="text-xs text-gray-500 uppercase tracking-wider">Cancelled</p>
          <p class="text-2xl font-semibold text-gray-900 mt-0.5">{{ stats.cancelled }}</p>
        </div>
      </div>
    </div>

    <!-- Upcoming list -->
    <div class="card">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-semibold text-gray-900">Upcoming Appointments</h2>
        <RouterLink to="/appointments" class="text-sm text-emerald-600 hover:underline">View all →</RouterLink>
      </div>

      <div v-if="loading" class="py-10 flex justify-center">
        <LoadingSpinner size="md" label="Loading..." />
      </div>
      <div v-else-if="upcoming.length === 0" class="py-12 text-center">
        <div class="w-14 h-14 mx-auto rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mb-3">
          <Icon name="calendar" :size="24" />
        </div>
        <p class="text-gray-700 font-medium">No upcoming appointments</p>
        <p class="text-sm text-gray-500 mt-1">Book your first visit to get started.</p>
        <RouterLink to="/book" class="inline-flex mt-4 items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
          <Icon name="plus" :size="16" /> Book now
        </RouterLink>
      </div>
      <ul v-else class="divide-y divide-gray-100">
        <li v-for="a in upcoming" :key="a.id" class="py-3 flex items-center gap-4 group hover:bg-emerald-50/30 rounded-xl px-2 -mx-2 transition">
          <Avatar :name="a.doctor?.name || a.doctor_name" size="md" />
          <div class="flex-1 min-w-0">
            <p class="font-medium text-gray-900 truncate">Dr. {{ a.doctor?.name || a.doctor_name || '—' }}</p>
            <p class="text-xs text-gray-500 truncate">{{ a.doctor?.specialization || 'Consultation' }}</p>
          </div>
          <div class="text-right">
            <p class="text-sm font-semibold text-gray-800">{{ a.date }}</p>
            <p class="text-xs text-emerald-600 font-medium">{{ a.time }}</p>
          </div>
          <StatusBadge :status="a.status" />
        </li>
      </ul>
      <div class="mt-5 pt-4 border-t border-gray-100">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Quick Actions</p>
        <div class="grid grid-cols-2 gap-3">
          <RouterLink to="/book" class="flex items-center gap-3 p-3 rounded-xl border-2 border-emerald-200 bg-emerald-50 hover:bg-emerald-100 transition group">
            <div class="w-9 h-9 rounded-lg bg-emerald-600 text-white flex items-center justify-center">
              <Icon name="plus" :size="18" />
            </div>
            <div>
              <p class="text-sm font-semibold text-emerald-800">Book Appointment</p>
              <p class="text-xs text-emerald-600">Schedule a visit</p>
            </div>
          </RouterLink>
          <RouterLink to="/appointments" class="flex items-center gap-3 p-3 rounded-xl border-2 border-gray-200 bg-white hover:border-emerald-200 hover:bg-emerald-50/30 transition">
            <div class="w-9 h-9 rounded-lg bg-gray-100 text-gray-600 flex items-center justify-center">
              <Icon name="calendar" :size="18" />
            </div>
            <div>
              <p class="text-sm font-semibold text-gray-800">View All</p>
              <p class="text-xs text-gray-500">All appointments</p>
            </div>
          </RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>
