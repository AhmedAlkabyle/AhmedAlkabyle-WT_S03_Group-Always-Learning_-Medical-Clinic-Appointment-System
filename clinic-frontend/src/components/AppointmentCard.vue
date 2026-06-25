<script setup>
import StatusBadge from './StatusBadge.vue'

defineProps({
  appointment: { type: Object, required: true },
  perspective: { type: String, default: 'patient' } // patient | doctor
})
</script>

<template>
  <div class="card flex items-start justify-between gap-4">
    <div>
      <p class="text-xs uppercase tracking-wide text-gray-500">
        {{ perspective === 'doctor' ? 'Patient' : 'Doctor' }}
      </p>
      <p class="font-semibold text-gray-800">
        <template v-if="perspective === 'doctor'">
          {{ appointment.patient?.name || appointment.patient_name || '—' }}
        </template>
        <template v-else>
          Dr. {{ appointment.doctor?.name || appointment.doctor_name || '—' }}
        </template>
      </p>
      <p v-if="appointment.doctor?.specialization && perspective !== 'doctor'" class="text-xs text-gray-500">
        {{ appointment.doctor.specialization }}
      </p>
      <p class="mt-2 text-sm text-gray-600">
        <span class="font-medium">{{ appointment.date }}</span>
        <span class="mx-1">·</span>
        <span>{{ appointment.time }}</span>
      </p>
    </div>
    <StatusBadge :status="appointment.status" />
  </div>
</template>
