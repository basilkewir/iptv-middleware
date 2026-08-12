<template>
  <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
    <!-- Channel Header -->
    <div class="flex items-center space-x-3 sm:space-x-4 p-3 sm:p-4 border-b border-gray-700">
      <img
        v-if="channel?.logo"
        :src="channel.logo"
        :alt="channel.name"
        class="w-10 h-10 sm:w-12 sm:h-12 rounded-lg"
      />
      <div v-else class="w-10 h-10 sm:w-12 sm:h-12 rounded-lg bg-gray-700 flex items-center justify-center">
        <TvIcon class="w-5 h-5 sm:w-6 sm:h-6 text-gray-500" />
      </div>
      <div class="flex-1 min-w-0">
        <h3 class="text-base sm:text-lg font-semibold text-white">{{ channel?.name }}</h3>
        <p class="text-sm text-gray-400">{{ channel?.category }}</p>
      </div>
      <div class="flex items-center space-x-2">
        <span class="px-2 py-1 text-xs font-medium bg-red-500/20 text-red-400 rounded">
          LIVE
        </span>
        <button
          @click="$emit('favorite')"
          class="p-1.5 sm:p-2 rounded-lg hover:bg-gray-700 transition-colors tv-touch-target tv-focusable"
        >
          <HeartIcon
            :class="[
              'w-4 h-4 sm:w-5 sm:h-5',
              isFavorite ? 'text-red-500 fill-red-500' : 'text-gray-400'
            ]"
          />
        </button>
      </div>
    </div>

    <!-- Current Program -->
    <div v-if="program" class="p-3 sm:p-4">
      <div class="flex items-start justify-between gap-3">
        <div class="flex-1 min-w-0">
          <h4 class="text-base font-semibold text-white">{{ program.title }}</h4>
          <p v-if="program.description" class="mt-1 text-sm text-gray-400 line-clamp-2">
            {{ program.description }}
          </p>
        </div>
        <button
          v-if="program.id"
          @click="$emit('watch')"
          class="ml-3 sm:ml-4 px-3 sm:px-4 py-1.5 sm:py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors flex items-center space-x-2 tv-touch-target tv-focusable"
        >
          <PlayIcon class="w-3 h-3 sm:w-4 sm:h-4" />
          <span>Watch</span>
        </button>
      </div>

      <!-- Program Meta -->
      <div class="mt-3 flex items-center space-x-3 sm:space-x-4 text-sm text-gray-500">
        <div class="flex items-center space-x-1">
          <ClockIcon class="w-3 h-3 sm:w-4 sm:h-4" />
          <span>{{ program.time }}</span>
        </div>
        <div v-if="program.genre" class="flex items-center space-x-1">
          <TagIcon class="w-3 h-3 sm:w-4 sm:h-4" />
          <span>{{ program.genre }}</span>
        </div>
      </div>

      <!-- Progress Bar -->
      <div class="mt-3 sm:mt-4">
        <WatchProgress
          :progress="progressPercent"
          :current-time="elapsedSeconds"
          :total-time="totalSeconds"
          color="indigo"
          show-text
        />
      </div>
    </div>

    <!-- No Program -->
    <div v-else class="p-4 text-center text-gray-500">
      <p>No program information available</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { TvIcon, HeartIcon, PlayIcon, ClockIcon, TagIcon } from '@heroicons/vue/24/outline'
import WatchProgress from '../Content/WatchProgress.vue'

const props = defineProps({
  channel: {
    type: Object,
    default: () => ({}),
  },
  program: {
    type: Object,
    default: () => ({}),
  },
  isFavorite: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['favorite', 'watch'])

const progressPercent = computed(() => {
  if (!props.program?.start_time || !props.program?.end_time) return 0
  const now = new Date()
  const start = new Date(props.program.start_time)
  const end = new Date(props.program.end_time)
  const total = end - start
  const elapsed = now - start
  return Math.min(100, Math.max(0, (elapsed / total) * 100))
})

const elapsedSeconds = computed(() => {
  if (!props.program?.start_time) return 0
  const now = new Date()
  const start = new Date(props.program.start_time)
  return Math.floor((now - start) / 1000)
})

const totalSeconds = computed(() => {
  if (!props.program?.start_time || !props.program?.end_time) return 0
  const start = new Date(props.program.start_time)
  const end = new Date(props.program.end_time)
  return Math.floor((end - start) / 1000)
})
</script>
