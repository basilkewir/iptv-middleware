<template>
  <div class="relative">
    <!-- Timeline Header -->
    <div class="flex items-center border-b border-gray-700">
      <div class="w-32 sm:w-48 flex-shrink-0 px-3 sm:px-4 py-2 text-xs sm:text-sm font-medium text-gray-400">
        Time
      </div>
      <div
        ref="timelineContainer"
        class="flex-1 flex overflow-x-auto scrollbar-thin"
        @scroll="onScroll"
      >
        <div
          v-for="hour in hours"
          :key="hour"
          class="flex-shrink-0 px-2 sm:px-4 py-2 text-center text-xs font-medium text-gray-500 border-l border-gray-700"
          :style="{ width: `${hourWidth}px` }"
        >
          {{ formatHour(hour) }}
        </div>
      </div>
    </div>

    <!-- Time Markers -->
    <div class="flex">
      <div class="w-32 sm:w-48 flex-shrink-0" />
      <div class="flex-1 flex">
        <div
          v-for="hour in hours"
          :key="`marker-${hour}`"
          class="flex-shrink-0 border-l border-gray-700/50"
          :style="{ width: `${hourWidth}px` }"
        >
          <!-- 15-minute markers -->
          <div class="flex h-3 sm:h-4">
            <div class="flex-1 border-r border-gray-700/30" />
            <div class="flex-1 border-r border-gray-700/30" />
            <div class="flex-1 border-r border-gray-700/30" />
            <div class="flex-1" />
          </div>
        </div>
      </div>
    </div>

    <!-- Current Time Indicator -->
    <div
      :style="{ left: `calc(128px + ${currentPosition}px)` }"
      class="absolute top-0 bottom-0 w-0.5 bg-red-500 z-10 pointer-events-none"
    >
      <div class="absolute -top-1 -left-1.5 w-3 h-3 sm:w-3.5 sm:h-3.5 bg-red-500 rounded-full" />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const props = defineProps({
  startHour: {
    type: Number,
    default: 6,
  },
  endHour: {
    type: Number,
    default: 24,
  },
  hourWidth: {
    type: Number,
    default: 200,
  },
  currentTime: {
    type: Date,
    default: () => new Date(),
  },
})

const timelineContainer = ref(null)

const hours = computed(() => {
  const result = []
  for (let i = props.startHour; i < props.endHour; i++) {
    result.push(i)
  }
  return result
})

const formatHour = (hour) => {
  const period = hour >= 12 ? 'PM' : 'AM'
  const displayHour = hour > 12 ? hour - 12 : hour === 0 ? 12 : hour
  return `${displayHour} ${period}`
}

const currentPosition = computed(() => {
  const now = props.currentTime
  const hour = now.getHours() + now.getMinutes() / 60
  return (hour - props.startHour) * props.hourWidth
})

const onScroll = () => {
  // Emit scroll event if needed
}

onMounted(() => {
  // Scroll to current time
  if (timelineContainer.value) {
    const containerWidth = timelineContainer.value.clientWidth
    const scrollTo = currentPosition.value - containerWidth / 2
    timelineContainer.value.scrollLeft = Math.max(0, scrollTo)
  }
})
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
