<script setup>
defineProps({
  size: { type: String, default: 'md' }, // sm | md | lg
  label: { type: String, default: '' }
})
</script>

<template>
  <div class="inline-flex items-center gap-2">
    <svg
      class="animate-spin text-clinic-600"
      :class="{ 'w-4 h-4': size==='sm', 'w-6 h-6': size==='md', 'w-10 h-10': size==='lg' }"
      xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
    >
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor"
        d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
    </svg>
    <span v-if="label" class="text-sm text-gray-600">{{ label }}</span>
  </div>
</template>
