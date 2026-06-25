<script setup>
defineProps({
  name: { type: String, required: true },
  size: { type: [String, Number], default: 20 },
  stroke: { type: Number, default: 2 }
})
// Heroicons-style outline icons (subset).
const paths = {
  logo: 'M12 4v16M4 12h16',
  dashboard: 'M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3v-7h6v7h3a1 1 0 001-1V10',
  calendar: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
  plus: 'M12 4v16m8-8H4',
  users: 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-5.13a4 4 0 11-8 0 4 4 0 018 0zm6 0a4 4 0 11-8 0 4 4 0 018 0z',
  user: 'M16 14a4 4 0 10-8 0m12 7a8 8 0 10-16 0',
  stethoscope: 'M6 3v6a4 4 0 008 0V3M10 19a3 3 0 106 0v-3a5 5 0 00-5-5',
  clock: 'M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z',
  check: 'M5 13l4 4L19 7',
  x: 'M6 18L18 6M6 6l12 12',
  search: 'M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z',
  trash: 'M19 7l-1 12a2 2 0 01-2 2H8a2 2 0 01-2-2L5 7m5 0V4a1 1 0 011-1h2a1 1 0 011 1v3m-7 0h10',
  edit: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.6a2 2 0 112.83 2.83L11 17l-4 1 1-4 9.6-9.6z',
  logout: 'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h6a2 2 0 012 2v1',
  filter: 'M3 4h18M6 12h12M10 20h4',
  chart: 'M3 3v18h18M7 14l4-4 4 4 5-5',
  shield: 'M12 3l8 4v5c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V7l8-4z',
  heart: 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z',
  lock: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
  key: 'M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4'
}
</script>

<template>
  <svg
    xmlns="http://www.w3.org/2000/svg"
    :width="size" :height="size"
    viewBox="0 0 24 24" fill="none"
    :stroke-width="stroke" stroke="currentColor"
    stroke-linecap="round" stroke-linejoin="round"
    aria-hidden="true"
  >
    <path :d="paths[name] || paths.dashboard" />
  </svg>
</template>
