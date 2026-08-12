<template>
  <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
    <div v-if="title" class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-white">{{ title }}</h3>
      <slot name="header" />
    </div>

    <div class="relative" :style="{ height: `${height}px` }">
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
      <div class="ml-14 h-full relative">
        <!-- Grid Lines -->
        <div class="absolute inset-0 flex flex-col justify-between">
          <div
            v-for="i in 6"
            :key="i"
            class="border-t border-gray-700/50"
          />
        </div>

        <!-- SVG Chart -->
        <svg class="absolute inset-0 w-full h-full" preserveAspectRatio="none">
          <!-- Line Path -->
          <path
            :d="linePath"
            fill="none"
            :stroke="strokeColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          />

          <!-- Area Fill -->
          <path
            :d="areaPath"
            :fill="`url(#gradient-${uid})`"
            opacity="0.3"
          />

          <!-- Gradient Definition -->
          <defs>
            <linearGradient :id="`gradient-${uid}`" x1="0%" y1="0%" x2="0%" y2="100%">
              <stop offset="0%" :stop-color="strokeColor" stop-opacity="0.4" />
              <stop offset="100%" :stop-color="strokeColor" stop-opacity="0" />
            </linearGradient>
          </defs>
        </svg>

        <!-- Data Points -->
        <div class="absolute inset-0 flex items-end">
          <div
            v-for="(point, index) in dataPoints"
            :key="index"
            class="absolute w-3 h-3 rounded-full bg-gray-800 border-2 transition-transform hover:scale-150 cursor-pointer"
            :style="{
              left: `${point.x}%`,
              bottom: `${point.y}%`,
              borderColor: strokeColor
            }"
          >
            <!-- Tooltip -->
            <div class="absolute -top-8 left-1/2 -translate-x-1/2 px-2 py-1 bg-gray-900 rounded text-xs text-white whitespace-nowrap opacity-0 hover:opacity-100 transition-opacity pointer-events-none">
              {{ data[index].value }}
            </div>
          </div>
        </div>
      </div>

      <!-- X-Axis Labels -->
      <div class="ml-14 mt-2 flex justify-between">
        <span
          v-for="(item, index) in data"
          :key="index"
          class="text-xs text-gray-500"
        >
          {{ item.label }}
        </span>
      </div>
    </div>

    <!-- Legend -->
    <div v-if="showLegend" class="mt-4 flex items-center justify-center space-x-4">
      <div class="flex items-center space-x-2">
        <div class="w-3 h-0.5 rounded" :style="{ backgroundColor: strokeColor }" />
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

const uid = computed(() => Math.random().toString(36).slice(2, 9))

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

const dataPoints = computed(() => {
  const width = 100
  const height = 100
  const step = width / Math.max(props.data.length - 1, 1)

  return props.data.map((item, index) => ({
    x: index * step,
    y: (item.value / maxValue.value) * height,
  }))
})

const linePath = computed(() => {
  if (dataPoints.value.length === 0) return ''

  const points = dataPoints.value
  let path = `M ${points[0].x} ${100 - points[0].y}`

  for (let i = 1; i < points.length; i++) {
    path += ` L ${points[i].x} ${100 - points[i].y}`
  }

  return path
})

const areaPath = computed(() => {
  if (dataPoints.value.length === 0) return ''

  const points = dataPoints.value
  let path = `M ${points[0].x} ${100 - points[0].y}`

  for (let i = 1; i < points.length; i++) {
    path += ` L ${points[i].x} ${100 - points[i].y}`
  }

  path += ` L ${points[points.length - 1].x} 100 L ${points[0].x} 100 Z`

  return path
})

const strokeColor = computed(() => {
  const colors = {
    indigo: '#6366f1',
    green: '#22c55e',
    blue: '#3b82f6',
    red: '#ef4444',
    yellow: '#eab308',
  }
  return colors[props.color]
})
</script>
