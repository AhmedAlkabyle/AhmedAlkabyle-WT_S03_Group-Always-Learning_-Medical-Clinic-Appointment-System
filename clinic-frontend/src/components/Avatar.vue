<script setup>
defineProps({
  name: { type: String, default: '?' },
  size: { type: String, default: 'md' } // xs | sm | md | lg
})

const palettes = [
  'bg-clinic-100 text-clinic-700',
  'bg-emerald-100 text-emerald-700',
  'bg-amber-100 text-amber-700',
  'bg-rose-100 text-rose-700',
  'bg-violet-100 text-violet-700',
  'bg-sky-100 text-sky-700'
]

const initials = (n) => {
  if (!n) return '?'
  const parts = String(n).trim().split(/\s+/)
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
}
const palette = (n) => {
  let h = 0
  for (const c of String(n || '')) h = (h * 31 + c.charCodeAt(0)) >>> 0
  return palettes[h % palettes.length]
}
</script>

<template>
  <span
    class="inline-flex items-center justify-center rounded-full font-semibold flex-shrink-0"
    :class="[
      palette(name),
      {
        'w-6 h-6 text-[10px]': size === 'xs',
        'w-8 h-8 text-xs': size === 'sm',
        'w-10 h-10 text-sm': size === 'md',
        'w-12 h-12 text-base': size === 'lg'
      }
    ]"
  >{{ initials(name) }}</span>
</template>
