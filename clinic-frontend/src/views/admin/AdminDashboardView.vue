<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import api from '../../api/axios'
import { useAlertStore } from '../../stores/alertStore'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import Icon from '../../components/Icon.vue'

const alerts = useAlertStore()
const doctors = ref([])
const appointments = ref([])
const users = ref([])
const offDays = ref([])
const loading = ref(false)

const totals = computed(() => ({
  doctors: doctors.value.length,
  appointments: appointments.value.length,
  pending: appointments.value.filter(a => a.status === 'pending').length,
  patients: users.value.filter(u => u.role === 'patient').length,
  pendingOffDays: offDays.value.filter(o => o.status === 'pending').length
}))

const load = async () => {
  loading.value = true
  try {
    const reqs = [
      api.get('/doctors').catch(() => ({ data: [] })),
      api.get('/appointments').catch(() => ({ data: [] })),
      api.get('/users').catch(() => ({ data: [] })),
      api.get('/off-days').catch(() => ({ data: [] }))
    ]
    const [d, a, u, o] = await Promise.all(reqs)
    doctors.value = d.data?.data || d.data || []
    appointments.value = a.data?.data || a.data || []
    users.value = u.data?.data || u.data || []
    offDays.value = o.data?.data || o.data || []
  } catch (e) {
    alerts.error('Failed to load admin data')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- HERO -->
    <div
      class="rounded-2xl text-white p-6 sm:p-8 mb-8 relative overflow-hidden"
      style="background-image: linear-gradient(135deg, #881337 0%, #BE123C 55%, #FB7185 100%)"
    >
      <div class="absolute -right-20 -top-20 w-72 h-72 rounded-full bg-white/10 blur-2xl"></div>
      <div class="absolute -left-16 -bottom-24 w-72 h-72 rounded-full bg-white/10 blur-3xl"></div>
      <div class="absolute right-8 top-8 opacity-10">
        <Icon name="shield" :size="120" :stroke="1" />
      </div>
      <div class="relative inline-flex items-center gap-1.5 bg-white/20 backdrop-blur text-white text-xs font-semibold px-3 py-1 rounded-full mb-4">
        <Icon name="shield" :size="12" /> Admin Portal
      </div>
      <div class="relative flex flex-col sm:flex-row sm:items-end justify-between gap-6">
        <div>
          <h1 class="text-3xl sm:text-4xl font-bold tracking-tight mb-1">Admin Overview</h1>
          <p class="text-rose-100/80 text-sm">Manage clinic operations from one place.</p>
        </div>
        <div class="flex flex-wrap gap-3">
          <div class="bg-white/15 backdrop-blur rounded-2xl px-5 py-3 text-center min-w-[80px] border border-white/10">
            <p class="text-3xl font-bold">{{ totals.doctors }}</p>
            <p class="text-xs text-white/75 mt-0.5">Doctors</p>
          </div>
          <div class="bg-white/15 backdrop-blur rounded-2xl px-5 py-3 text-center min-w-[80px] border border-white/10">
            <p class="text-3xl font-bold">{{ totals.appointments }}</p>
            <p class="text-xs text-white/75 mt-0.5">Appointments</p>
          </div>
          <div class="bg-white/15 backdrop-blur rounded-2xl px-5 py-3 text-center min-w-[80px] border border-white/10">
            <p class="text-3xl font-bold">{{ totals.patients }}</p>
            <p class="text-xs text-white/75 mt-0.5">Patients</p>
          </div>
          <div class="bg-white/25 backdrop-blur rounded-2xl px-5 py-3 text-center min-w-[80px] border border-white/30 ring-2 ring-white/20">
            <p class="text-3xl font-bold">{{ totals.pending }}</p>
            <p class="text-xs text-white/75 mt-0.5">Pending</p>
          </div>
        </div>
      </div>
    </div>

    <div v-if="loading" class="py-12 flex justify-center"><LoadingSpinner size="md" label="Loading..." /></div>
    <div v-else>

      <!-- Section label -->
      <div class="flex items-center gap-3 mb-5">
        <div class="w-1 h-6 rounded-full bg-rose-500"></div>
        <h2 class="text-base font-semibold text-gray-800">Quick Access</h2>
        <div class="flex-1 h-px bg-gray-100"></div>
      </div>

      <!-- Quick access grid — 3 columns -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">

        <!-- Manage Doctors -->
        <RouterLink to="/admin/doctors" class="group card card-hover border-t-4 border-rose-400 flex flex-col gap-3">
          <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center group-hover:bg-rose-200 transition">
              <Icon name="stethoscope" :size="20" />
            </div>
            <div class="flex-1">
              <p class="font-semibold text-gray-900">Manage Doctors</p>
              <p class="text-xs text-gray-500">Add, edit, or remove doctors.</p>
            </div>
          </div>
          <div class="flex items-center justify-between pt-2 border-t border-gray-100">
            <span class="text-2xl font-bold text-rose-600">{{ totals.doctors }}</span>
            <span class="text-rose-400 group-hover:translate-x-1 transition-transform text-lg">→</span>
          </div>
        </RouterLink>

        <!-- Manage Appointments -->
        <RouterLink to="/admin/appointments" class="group card card-hover border-t-4 border-pink-400 flex flex-col gap-3">
          <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-pink-100 text-pink-600 flex items-center justify-center group-hover:bg-pink-200 transition">
              <Icon name="calendar" :size="20" />
            </div>
            <div class="flex-1">
              <p class="font-semibold text-gray-900">Appointments</p>
              <p class="text-xs text-gray-500">Update statuses and review bookings.</p>
            </div>
          </div>
          <div class="flex items-center justify-between pt-2 border-t border-gray-100">
            <div class="flex items-center gap-2">
              <span class="text-2xl font-bold text-pink-600">{{ totals.appointments }}</span>
              <span v-if="totals.pending > 0" class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-medium">{{ totals.pending }} pending</span>
            </div>
            <span class="text-pink-400 group-hover:translate-x-1 transition-transform text-lg">→</span>
          </div>
        </RouterLink>

        <!-- Off Day Requests -->
        <RouterLink to="/admin/off-days" class="group card card-hover border-t-4 border-amber-400 flex flex-col gap-3">
          <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center group-hover:bg-amber-200 transition">
              <Icon name="clock" :size="20" />
            </div>
            <div class="flex-1">
              <p class="font-semibold text-gray-900">Off Day Requests</p>
              <p class="text-xs text-gray-500">Approve or reject doctor requests.</p>
            </div>
          </div>
          <div class="flex items-center justify-between pt-2 border-t border-gray-100">
            <div class="flex items-center gap-2">
              <span class="text-2xl font-bold text-amber-600">{{ totals.pendingOffDays }}</span>
              <span v-if="totals.pendingOffDays > 0" class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-medium animate-pulse">Needs review</span>
              <span v-else class="text-xs text-gray-400">No pending</span>
            </div>
            <span class="text-amber-400 group-hover:translate-x-1 transition-transform text-lg">→</span>
          </div>
        </RouterLink>

        <!-- Patient Overview -->
        <RouterLink to="/admin/appointments" class="group card card-hover border-t-4 border-rose-300 flex flex-col gap-3">
          <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-rose-50 text-rose-400 flex items-center justify-center group-hover:bg-rose-100 transition">
              <Icon name="users" :size="20" />
            </div>
            <div class="flex-1">
              <p class="font-semibold text-gray-900">Patient Overview</p>
              <p class="text-xs text-gray-500">View all registered patients.</p>
            </div>
          </div>
          <div class="flex items-center justify-between pt-2 border-t border-gray-100">
            <span class="text-2xl font-bold text-rose-400">{{ totals.patients }}</span>
            <span class="text-rose-300 group-hover:translate-x-1 transition-transform text-lg">→</span>
          </div>
        </RouterLink>

        <!-- User Accounts -->
        <RouterLink to="/admin/users" class="group card card-hover border-t-4 border-rose-200 flex flex-col gap-3">
          <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center group-hover:bg-rose-100 transition">
              <Icon name="user" :size="20" />
            </div>
            <div class="flex-1">
              <p class="font-semibold text-gray-900">User Accounts</p>
              <p class="text-xs text-gray-500">Manage patient and doctor accounts.</p>
            </div>
          </div>
          <div class="flex items-center justify-between pt-2 border-t border-gray-100">
            <span class="text-2xl font-bold text-rose-500">{{ totals.doctors + totals.patients }}</span>
            <span class="text-rose-300 group-hover:translate-x-1 transition-transform text-lg">→</span>
          </div>
        </RouterLink>

      </div>
    </div>
  </div>
</template>
