<template>
  <div :class="['relative', wrapperClass]">
    <!-- Progress Bar -->
    <div :class="['bg-gray-700 rounded-full overflow-hidden', heightClass]">
      <div
        :class="[
          'h-full rounded-full transition-all duration-300',
          barColorClass
        ]"
        :style="{ width: `${clampedProgress}%` }"
      />
    </div>

    <!-- Progress Text -->
    <div
      v-if="showText"
      class="flex items-center justify-between mt-1"
    >
      <span class="text-xs text-gray-500">
        {{ formatTime(currentTime) }} / {{ formatTime(totalTime) }}
      </span>
      <span class="text-xs text-gray-500">
        {{ Math.round(clampedProgress) }}%
      </span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  progress: {
    type: Number,
    default: 0,
  },
  currentTime: {
    type: Number,
    default: 0,
  },
  totalTime: {
    type: Number,
    default: 0,
  },
  height: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md', 'lg'].includes(v),
  },
  color: {
    type: String,
    default: 'indigo',
    validator: (v) => ['indigo', 'green', 'blue', 'red', 'yellow'].includes(v),
  },
  showText: {
    type: Boolean,
    default: false,
  },
})

const clampedProgress = computed(() => Math.min(100, Math.max(0, props.progress)))

const heightClass = computed(() => {
  const heights = {
    sm: 'h-1',
    md: 'h-2',
    lg: 'h-3',
  }
  return heights[props.height]
})

const wrapperClass = computed(() => props.showText ? 'w-full' : '')

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

const formatTime = (seconds) => {
  if (!seconds || isNaN(seconds)) return '0:00'
  const h = Math.floor(seconds / 3600)
  const m = Math.floor((seconds % 3600) / 60)
  const s = Math.floor(seconds % 60)
  if (h > 0) {
    return `${h}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`
  }
  return `${m}:${s.toString().padStart(2, '0')}`
}
</script>
