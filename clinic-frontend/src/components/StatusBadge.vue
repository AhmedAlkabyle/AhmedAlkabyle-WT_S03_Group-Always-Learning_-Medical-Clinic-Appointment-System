<script setup>
import { computed } from 'vue'
const props = defineProps({ status: { type: String, default: 'pending' } })

const cls = computed(() => {
  const s = (props.status || '').toLowerCase()
  if (s === 'confirmed') return { wrap: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200', dot: 'bg-emerald-500' }
  if (s === 'cancelled' || s === 'canceled') return { wrap: 'bg-rose-50 text-rose-700 ring-1 ring-rose-200', dot: 'bg-rose-500' }
  if (s === 'completed') return { wrap: 'bg-clinic-50 text-clinic-700 ring-1 ring-clinic-200', dot: 'bg-clinic-500' }
  return { wrap: 'bg-amber-50 text-amber-700 ring-1 ring-amber-200', dot: 'bg-amber-500' }
})
</script>

<template>
  <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium capitalize" :class="cls.wrap">
    <span class="w-1.5 h-1.5 rounded-full" :class="cls.dot"></span>
    {{ status }}
  </span>
</template>
