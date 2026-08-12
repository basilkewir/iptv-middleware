<template>
  <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
    <div v-if="title" class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-white">{{ title }}</h3>
      <slot name="header" />
    </div>

    <div class="flex items-center justify-center">
      <div class="relative" :style="{ width: `${size}px`, height: `${size}px` }">
        <!-- SVG Pie Chart -->
        <svg :width="size" :height="size" viewBox="0 0 100 100">
          <circle
            cx="50"
            cy="50"
            r="40"
            fill="none"
            stroke="#374151"
            stroke-width="20"
          />
          <circle
            v-for="(segment, index) in segments"
            :key="index"
            cx="50"
            cy="50"
            r="40"
            fill="none"
            :stroke="segment.color"
            stroke-width="20"
            :stroke-dasharray="segment.dashArray"
            :stroke-dashoffset="segment.dashOffset"
            :transform="`rotate(-90 50 50)`"
            class="transition-all duration-500 hover:opacity-80"
          />
        </svg>

        <!-- Center Content -->
        <div class="absolute inset-0 flex flex-col items-center justify-center">
          <span class="text-2xl font-bold text-white">{{ totalValue }}</span>
          <span class="text-xs text-gray-500">{{ centerLabel }}</span>
        </div>
      </div>
    </div>

    <!-- Legend -->
    <div class="mt-6 grid grid-cols-2 gap-2">
      <div
        v-for="(item, index) in data"
        :key="index"
        class="flex items-center space-x-2"
      >
        <div
          class="w-3 h-3 rounded-full flex-shrink-0"
          :style="{ backgroundColor: colors[index % colors.length] }"
        />
        <div class="flex-1 min-w-0">
          <p class="text-sm text-gray-300 truncate">{{ item.label }}</p>
          <p class="text-xs text-gray-500">
            {{ item.value }} ({{ getPercentage(item.value) }}%)
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  data: {
    type: Array,
    required: true,
    validator: (d) => d.every(item => 'label' in item && 'value' in item),
  },
  title: {
    type: String,
    default: '',
  },
  size: {
    type: Number,
    default: 200,
  },
  centerLabel: {
    type: String,
    default: 'Total',
  },
})

const colors = [
  '#6366f1', // indigo
  '#22c55e', // green
  '#3b82f6', // blue
  '#ef4444', // red
  '#eab308', // yellow
  '#8b5cf6', // violet
  '#ec4899', // pink
  '#14b8a6', // teal
]

const totalValue = computed(() => {
  return props.data.reduce((sum, item) => sum + item.value, 0)
})

const getPercentage = (value) => {
  if (totalValue.value === 0) return 0
  return Math.round((value / totalValue.value) * 100)
}

const segments = computed(() => {
  const circumference = 2 * Math.PI * 40
  let currentOffset = 0

  return props.data.map((item, index) => {
    const percentage = totalValue.value > 0 ? item.value / totalValue.value : 0
    const dashArray = `${percentage * circumference} ${circumference}`
    const segment = {
      color: colors[index % colors.length],
      dashArray,
      dashOffset: -currentOffset,
    }
    currentOffset += percentage * circumference
    return segment
  })
})
</script>
