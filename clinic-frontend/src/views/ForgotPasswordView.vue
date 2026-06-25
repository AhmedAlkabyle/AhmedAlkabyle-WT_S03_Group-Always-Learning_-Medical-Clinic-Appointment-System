<script setup>
import { RouterLink } from 'vue-router'
import Icon from '../components/Icon.vue'
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50/50 px-6">
    <div class="w-full max-w-md">

      <!-- Logo -->
      <div class="flex items-center gap-2 mb-8 justify-center">
        <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white" style="background-image:linear-gradient(135deg,#2B6CB0,#1A365D)">
          <Icon name="heart" :size="18" />
        </div>
        <span class="font-bold text-gray-900 text-lg">MediCare</span>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">

        <!-- Icon -->
        <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center mx-auto mb-5">
          <Icon name="lock" :size="28" class="text-blue-600" />
        </div>

        <h2 class="text-2xl font-bold text-gray-900 mb-3">Forgot your password?</h2>

        <p class="text-gray-500 text-sm leading-relaxed mb-4">
          Please contact your clinic administrator to reset your password.
        </p>

        <div class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-4 text-sm text-blue-700 text-left flex items-start gap-3 mb-6">
          <Icon name="shield" :size="18" class="mt-0.5 flex-shrink-0 text-blue-500" />
          <div>
            <p class="font-semibold mb-1">How it works</p>
            <p>Once the admin resets your password, you'll receive a temporary default password. Log in with it and then go to <strong>Profile → Security</strong> to set a new one.</p>
          </div>
        </div>

        <RouterLink
          to="/login"
          class="inline-flex items-center gap-2 w-full justify-center py-3 rounded-xl text-white font-semibold text-sm transition"
          style="background-image: linear-gradient(135deg, #2B6CB0 0%, #1A365D 100%)"
        >
          <Icon name="logout" :size="16" />
          Back to Sign In
        </RouterLink>

      </div>
    </div>
  </div>
</template>
