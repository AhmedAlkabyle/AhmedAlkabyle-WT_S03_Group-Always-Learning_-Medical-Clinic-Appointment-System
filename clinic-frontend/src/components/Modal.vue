<script setup>
defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: '' },
  size: { type: String, default: 'md' } // sm | md | lg
})
defineEmits(['close'])
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="$emit('close')"></div>
        <div
          class="relative bg-white rounded-xl shadow-lg w-full"
          :class="{
            'max-w-sm': size === 'sm',
            'max-w-md': size === 'md',
            'max-w-2xl': size === 'lg'
          }"
        >
          <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">{{ title }}</h3>
            <button class="text-gray-400 hover:text-gray-600" @click="$emit('close')" aria-label="close">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="p-5 max-h-[70vh] overflow-y-auto">
            <slot />
          </div>
          <div v-if="$slots.footer" class="px-5 py-3 border-t border-gray-100 bg-gray-50 rounded-b-xl flex justify-end gap-2">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
