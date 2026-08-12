<template>
  <div class="bg-gray-800 rounded-xl p-4 border border-gray-700 text-center">
    <div class="text-2xl font-bold" :class="color || 'text-white'">{{ formattedValue }}</div>
    <div class="text-gray-400 text-sm mt-1">{{ label }}</div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  label: { type: String, required: true },
  value: { type: [Number, String, null], default: 0 },
  color: { type: String, default: 'text-white' },
})

const formattedValue = computed(() => {
  if (props.value === null || props.value === undefined) return '0'
  if (typeof props.value === 'string') return props.value || '0'
  const n = Number(props.value)
  if (isNaN(n)) return String(props.value)
  if (n >= 1e6) return (n / 1e6).toFixed(1) + 'M'
  if (n >= 1e3) return (n / 1e3).toFixed(1) + 'K'
  return String(n)
})
</script>
