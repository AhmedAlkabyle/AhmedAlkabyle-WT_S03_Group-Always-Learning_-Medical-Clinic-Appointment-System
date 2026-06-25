<script setup>
import { RouterView } from 'vue-router'
import Navbar from './components/Navbar.vue'
import AlertMessage from './components/AlertMessage.vue'
import { useAuthStore } from './stores/authStore'

const auth = useAuthStore()
auth.hydrate()
</script>

<template>
  <div class="min-h-full flex flex-col">
    <Navbar v-if="auth.isAuthenticated" />
    <main class="flex-1">
      <RouterView />
    </main>
    <AlertMessage />
  </div>
</template>
