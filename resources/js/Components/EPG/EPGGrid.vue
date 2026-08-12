<template>
  <div class="overflow-x-auto">
    <div class="min-w-[600px] sm:min-w-[800px]">
      <!-- Time Header -->
      <div class="flex border-b border-gray-700">
        <div class="w-32 sm:w-48 flex-shrink-0 px-3 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm font-medium text-gray-400">
          Channel
        </div>
        <div class="flex-1 flex">
          <div
            v-for="hour in timeSlots"
            :key="hour"
            class="flex-1 px-1 sm:px-2 py-2 sm:py-3 text-center text-xs font-medium text-gray-500 border-l border-gray-700"
          >
            {{ formatHour(hour) }}
          </div>
        </div>
      </div>

      <!-- Channel Rows -->
      <div
        v-for="channel in channels"
        :key="channel.id"
        class="flex border-b border-gray-700/50 hover:bg-gray-800/50"
      >
        <!-- Channel Info -->
        <div class="w-32 sm:w-48 flex-shrink-0 px-3 sm:px-4 py-2 sm:py-3 flex items-center space-x-2 sm:space-x-3">
          <img
            v-if="channel.logo"
            :src="channel.logo"
            :alt="channel.name"
            class="w-6 h-6 sm:w-8 sm:h-8 rounded"
          />
          <div v-else class="w-6 h-6 sm:w-8 sm:h-8 rounded bg-gray-700 flex items-center justify-center">
            <TvIcon class="w-3 h-3 sm:w-4 sm:h-4 text-gray-500" />
          </div>
          <span class="text-xs sm:text-sm font-medium text-white truncate">
            {{ channel.name }}
          </span>
        </div>

        <!-- Programs -->
        <div class="flex-1 flex relative">
          <div
            v-for="program in getChannelPrograms(channel.id)"
            :key="program.id"
            :style="getProgramStyle(program)"
            :class="[
              'absolute top-1 bottom-1 rounded px-1 sm:px-2 py-1 cursor-pointer transition-colors overflow-hidden',
              isNowPlaying(program)
                ? 'bg-indigo-600/30 border border-indigo-500/50 hover:bg-indigo-600/40'
                : 'bg-gray-700/50 border border-gray-600/50 hover:bg-gray-700'
            ]"
            @click="$emit('program-click', program)"
          >
            <p class="text-xs font-medium text-white truncate">
              {{ program.title }}
            </p>
            <p class="text-xs text-gray-400 truncate">
              {{ program.time }}
            </p>
          </div>
        </div>
      </div>

      <!-- Current Time Indicator -->
      <div
        :style="{ left: `${currentPosition}px` }"
        class="absolute top-0 bottom-0 w-0.5 bg-red-500 z-10"
      >
        <div class="absolute -top-1 -left-1 w-2 h-2 sm:w-2.5 sm:h-2.5 bg-red-500 rounded-full" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { TvIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  channels: {
    type: Array,
    required: true,
  },
  programs: {
    type: Array,
    required: true,
  },
  startHour: {
    type: Number,
    default: 6,
  },
  endHour: {
    type: Number,
    default: 24,
  },
  currentTime: {
    type: Date,
    default: () => new Date(),
  },
})

defineEmits(['program-click'])

const timeSlots = computed(() => {
  const slots = []
  for (let i = props.startHour; i < props.endHour; i++) {
    slots.push(i)
  }
  return slots
})

const formatHour = (hour) => {
  return `${hour.toString().padStart(2, '0')}:00`
}

const getChannelPrograms = (channelId) => {
  return props.programs.filter(p => p.channel_id === channelId)
}

const getProgramStyle = (program) => {
  const slotWidth = 200
  const start = new Date(program.start_time)
  const end = new Date(program.end_time)
  const startHour = start.getHours() + start.getMinutes() / 60
  const duration = (end - start) / (1000 * 60 * 60)

  return {
    left: `${(startHour - props.startHour) * slotWidth}px`,
    width: `${duration * slotWidth - 4}px`,
  }
}

const isNowPlaying = (program) => {
  const now = props.currentTime
  const start = new Date(program.start_time)
  const end = new Date(program.end_time)
  return now >= start && now < end
}

const currentPosition = computed(() => {
  const slotWidth = 200
  const now = props.currentTime
  const hour = now.getHours() + now.getMinutes() / 60
  return (hour - props.startHour) * slotWidth
})
</script>
