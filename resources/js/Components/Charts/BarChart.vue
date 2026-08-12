<template>
  <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
    <div v-if="title" class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-white">{{ title }}</h3>
      <slot name="header" />
    </div>

    <div ref="chartContainer" class="relative" :style="{ height: `${height}px` }">
      <!-- Y-Axis Labels -->
      <div class="absolute left-0 top-0 bottom-0 w-12 flex flex-col justify-between text-right pr-2">
        <span
          v-for="label in yLabels"
          :key="label"
          class="text-xs text-gray-500"
        >
          {{ label }}
        </span>
      </div>

      <!-- Chart Area -->
      <div class="ml-14 h-full flex items-end space-x-1">
        <div
          v-for="(item, index) in data"
          :key="index"
          class="flex-1 flex flex-col items-center group"
        >
          <!-- Tooltip -->
          <div class="absolute -top-8 px-2 py-1 bg-gray-900 rounded text-xs text-white opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
            {{ item.value }}
          </div>

          <!-- Bar -->
          <div
            class="w-full rounded-t-md transition-all duration-300 hover:opacity-80"
            :class="barColorClass"
            :style="{ height: `${getBarHeight(item.value)}%` }"
          />
        </div>
      </div>

      <!-- X-Axis Labels -->
      <div class="ml-14 mt-2 flex space-x-1">
        <div
          v-for="(item, index) in data"
          :key="index"
          class="flex-1 text-center text-xs text-gray-500 truncate"
          :title="item.label"
        >
          {{ item.label }}
        </div>
      </div>
    </div>

    <!-- Legend -->
    <div v-if="showLegend" class="mt-4 flex items-center justify-center space-x-4">
      <div class="flex items-center space-x-2">
        <div :class="['w-3 h-3 rounded', barColorClass]" />
        <span class="text-xs text-gray-400">{{ legendLabel }}</span>
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
  height: {
    type: Number,
    default: 300,
  },
  color: {
    type: String,
    default: 'indigo',
    validator: (v) => ['indigo', 'green', 'blue', 'red', 'yellow'].includes(v),
  },
  showLegend: {
    type: Boolean,
    default: false,
  },
  legendLabel: {
    type: String,
    default: 'Value',
  },
})

const maxValue = computed(() => {
  return Math.max(...props.data.map(d => d.value), 1)
})

const yLabels = computed(() => {
  const labels = []
  const steps = 5
  for (let i = steps; i >= 0; i--) {
    labels.push(Math.round((maxValue.value / steps) * i))
  }
  return labels
})

const getBarHeight = (value) => {
  return (value / maxValue.value) * 100
}

const barColorClass = computed(() => {
  const colors = {
    indigo: 'bg-indigo-500',
    green: 'bg-green-500',
    blue: 'bg-blue-500',
    red: 'bg-red-500',
    yellow: 'bg-yellow-500',
  }
  return colors[props.color]
})
</script>
