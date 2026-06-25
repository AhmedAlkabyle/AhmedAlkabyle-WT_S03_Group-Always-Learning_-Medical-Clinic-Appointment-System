<script setup>
import { computed } from 'vue'
import { useAlertStore } from '../stores/alertStore'
import { useAuthStore } from '../stores/authStore'

const alerts = useAlertStore()
const auth = useAuthStore()

const role = computed(() => auth.role)

const cls = (type) => {
  // Error is always red regardless of role
  if (type === 'error') {
    return {
      wrap: 'bg-rose-50 border-rose-300 text-rose-800',
      icon: '✕',
      iconColor: 'text-rose-500',
      bar: 'bg-rose-500'
    }
  }
  if (type === 'warning') {
    return {
      wrap: 'bg-amber-50 border-amber-300 text-amber-800',
      icon: '⚠',
      iconColor: 'text-amber-500',
      bar: 'bg-amber-500'
    }
  }
  // Success and info follow role color
  if (role.value === 'admin') {
    return {
      wrap: 'bg-rose-50 border-rose-300 text-rose-800',
      icon: '✓',
      iconColor: 'text-rose-600',
      bar: 'bg-rose-600'
    }
  }
  if (role.value === 'doctor') {
    return {
      wrap: 'bg-blue-50 border-blue-300 text-blue-800',
      icon: '✓',
      iconColor: 'text-blue-600',
      bar: 'bg-blue-600'
    }
  }
  // patient or default = emerald
  return {
    wrap: 'bg-emerald-50 border-emerald-300 text-emerald-800',
    icon: '✓',
    iconColor: 'text-emerald-600',
    bar: 'bg-emerald-600'
  }
}
</script>

<template>
  <div class="fixed top-4 right-4 z-[60] space-y-2 w-80 max-w-[90vw] pointer-events-none">
    <transition-group name="toast" tag="div" class="space-y-2">
      <div
        v-for="a in alerts.items" :key="a.id"
        class="border rounded-xl shadow-lg overflow-hidden pointer-events-auto"
        :class="cls(a.type).wrap"
      >
        <!-- Colored top bar -->
        <div class="h-1 w-full" :class="cls(a.type).bar"></div>

        <div class="px-4 py-3 flex items-start gap-3">
          <!-- Icon -->
          <span class="text-base font-bold flex-shrink-0 mt-0.5" :class="cls(a.type).iconColor">
            {{ cls(a.type).icon }}
          </span>
          <!-- Message -->
          <span class="flex-1 text-sm font-medium break-words leading-snug">{{ a.message }}</span>
          <!-- Dismiss -->
          <button
            class="flex-shrink-0 opacity-50 hover:opacity-100 transition text-lg leading-none"
            @click="alerts.dismiss(a.id)"
          >×</button>
        </div>
      </div>
    </transition-group>
  </div>
</template>

<style scoped>
.toast-enter-active, .toast-leave-active { transition: all .25s cubic-bezier(.4,0,.2,1); }
.toast-enter-from { opacity: 0; transform: translateX(24px) scale(0.95); }
.toast-leave-to   { opacity: 0; transform: translateX(24px) scale(0.95); }
</style>
