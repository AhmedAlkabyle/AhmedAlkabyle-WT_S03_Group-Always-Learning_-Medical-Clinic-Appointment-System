<script setup>
import { computed, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
import Avatar from './Avatar.vue'
import Icon from './Icon.vue'

const auth = useAuthStore()
const router = useRouter()
const open = ref(false)
const menuOpen = ref(false)

const links = computed(() => {
  const role = auth.role
  if (role === 'patient') return [
    { to: '/dashboard',    label: 'Dashboard',        icon: 'dashboard' },
    { to: '/book',         label: 'Book Appointment', icon: 'plus' },
    { to: '/appointments', label: 'My Appointments',  icon: 'calendar' }
  ]
  if (role === 'doctor') return [
    { to: '/schedule', label: 'My Schedule', icon: 'calendar' },
    { to: '/off-days', label: 'Off Days',    icon: 'clock' }
  ]
  if (role === 'admin') return [
    { to: '/admin',              label: 'Overview',     icon: 'chart' },
    { to: '/admin/users',        label: 'Users',        icon: 'users' },
    { to: '/admin/doctors',      label: 'Doctors',      icon: 'stethoscope' },
    { to: '/admin/appointments', label: 'Appointments', icon: 'calendar' },
    { to: '/admin/off-days',     label: 'Off Days',     icon: 'clock' }
  ]
  return []
})

const navAccent = computed(() => {
  if (auth.role === 'admin') return 'border-b-rose-400'
  if (auth.role === 'doctor') return 'border-b-blue-400'
  if (auth.role === 'patient') return 'border-b-emerald-400'
  return 'border-b-gray-100'
})

const logout = () => {
  auth.logout()
  router.push('/login')
}
</script>

<template>
  <header :class="['bg-white/80 backdrop-blur border-b-2 sticky top-0 z-30', navAccent]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
      <RouterLink to="/" class="flex items-center gap-2.5 group">
        <div
          class="w-9 h-9 rounded-xl text-white flex items-center justify-center shadow-sm"
          style="background-image:linear-gradient(135deg,#2B6CB0 0%,#1E4E8C 100%)"
        >
          <Icon name="heart" :size="18" :stroke="2.2" />
        </div>
        <span class="text-lg font-semibold text-clinic-800 tracking-tight">MediCare</span>
      </RouterLink>

      <nav class="hidden md:flex items-center gap-1">
        <RouterLink
          v-for="l in links" :key="l.to" :to="l.to"
          class="px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:text-clinic-700 hover:bg-clinic-50 inline-flex items-center gap-2"
          active-class="!text-clinic-700 !bg-clinic-50"
        >
          <Icon :name="l.icon" :size="16" />
          {{ l.label }}
        </RouterLink>
      </nav>

      <div class="hidden md:block relative">
        <button
          class="flex items-center gap-3 pl-1 pr-2 py-1 rounded-full hover:bg-gray-100 transition"
          @click="menuOpen = !menuOpen"
        >
          <Avatar :name="auth.user?.name" size="sm" />
          <div class="text-left leading-tight pr-1">
            <div class="text-sm font-semibold text-gray-800">{{ auth.user?.name }}</div>
            <div class="text-[11px] text-gray-500 capitalize">{{ auth.user?.role }}</div>
          </div>
        </button>
        <div
          v-if="menuOpen"
          class="absolute right-0 mt-2 w-52 bg-white rounded-xl border border-gray-100 shadow-lg overflow-hidden"
          @click="menuOpen = false"
        >
          <div class="px-3 py-2 text-xs text-gray-500 border-b border-gray-100">
            Signed in as<br /><span class="font-medium text-gray-700 truncate block">{{ auth.user?.email }}</span>
          </div>
          <RouterLink
            to="/profile"
            class="w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50 flex items-center gap-2"
          >
            <Icon name="user" :size="16" /> My Profile
          </RouterLink>
          <button
            class="w-full px-3 py-2 text-sm text-left text-red-600 hover:bg-red-50 inline-flex items-center gap-2 border-t border-gray-100"
            @click="logout"
          >
            <Icon name="logout" :size="16" /> Logout
          </button>
        </div>
      </div>

      <button class="md:hidden btn-ghost" @click="open = !open" aria-label="menu">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>
    </div>

    <div v-if="open" class="md:hidden border-t border-gray-100 px-4 py-3 bg-white space-y-1">
      <RouterLink
        v-for="l in links" :key="l.to" :to="l.to" @click="open=false"
        class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-clinic-50"
        active-class="!text-clinic-700 !bg-clinic-50"
      >
        <Icon :name="l.icon" :size="16" />
        {{ l.label }}
      </RouterLink>
      <div class="pt-2 mt-2 border-t border-gray-100 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <Avatar :name="auth.user?.name" size="sm" />
          <div class="leading-tight">
            <div class="text-sm font-medium text-gray-800">{{ auth.user?.name }}</div>
            <div class="text-xs text-gray-500 capitalize">{{ auth.user?.role }}</div>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <RouterLink to="/profile" class="btn-secondary !py-1.5" @click="open=false">Profile</RouterLink>
          <button class="btn-secondary" @click="logout">Logout</button>
        </div>
      </div>
    </div>
  </header>
</template>
