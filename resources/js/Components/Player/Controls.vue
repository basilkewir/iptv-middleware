<template>
  <div
    class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent px-2 sm:px-4 pb-4 pt-12 sm:pt-16"
    @click.stop
  >
    <!-- Progress Bar -->
    <div
      class="relative h-1 bg-gray-600 rounded-full cursor-pointer group mb-3 sm:mb-4"
      @click="seekTo($event)"
      ref="progressBar"
    >
      <!-- Buffered -->
      <div
        class="absolute h-full bg-gray-500 rounded-full"
        :style="{ width: `${buffered}%` }"
      />
      <!-- Progress -->
      <div
        class="absolute h-full bg-indigo-500 rounded-full"
        :style="{ width: `${progress}%` }"
      />
      <!-- Hover Time -->
      <div
        v-show="isHovering"
        class="absolute -top-6 sm:-top-8 px-2 py-1 bg-gray-800 rounded text-xs text-white transform -translate-x-1/2"
        :style="{ left: `${hoverPosition}%` }"
      >
        {{ formatTime(hoverTime) }}
      </div>
      <!-- Thumb -->
      <div
        class="absolute top-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity"
        :style="{ left: `calc(${progress}% - 6px)` }"
      />
    </div>

    <!-- Controls Row -->
    <div class="flex items-center justify-between flex-wrap gap-2">
      <div class="flex items-center gap-2 sm:gap-3">
        <!-- Play/Pause -->
        <button
          @click="$emit('toggle-play')"
          class="p-1.5 sm:p-2 rounded-md hover:bg-white/20 transition-colors tv-touch-target tv-focusable"
        >
          <PauseIcon v-if="isPlaying" class="w-6 h-6 sm:w-7 sm:h-7" />
          <PlayIcon v-else class="w-6 h-6 sm:w-7 sm:h-7" />
        </button>

        <!-- Volume -->
        <div class="flex items-center gap-1 sm:gap-2 group/volume">
          <button
            @click="$emit('toggle-mute')"
            class="p-1.5 sm:p-2 rounded-md hover:bg-white/20 transition-colors tv-touch-target tv-focusable"
          >
            <SpeakerXMarkIcon v-if="isMuted || volume === 0" class="w-5 h-5 sm:w-6 sm:h-6" />
            <SpeakerWaveIcon v-else class="w-5 h-5 sm:w-6 sm:h-6" />
          </button>
          <div class="w-0 sm:group-hover/volume:w-20 overflow-hidden transition-all duration-200">
            <input
              type="range"
              min="0"
              max="1"
              step="0.01"
              :value="isMuted ? 0 : volume"
              @input="$emit('volume-change', parseFloat($event.target.value))"
              class="w-full h-1 bg-gray-600 rounded-full appearance-none cursor-pointer accent-indigo-500"
            />
          </div>
        </div>

        <!-- Time Display -->
        <span class="text-xs text-gray-300">
          {{ formatTime(currentTime) }} / {{ formatTime(duration) }}
        </span>
      </div>

      <div class="flex items-center gap-1 sm:gap-2">
        <!-- Quality Selector -->
        <QualitySelector
          v-if="qualities.length > 0"
          :qualities="qualities"
          :current-quality="currentQuality"
          @change="$emit('quality-change', $event)"
        />

        <!-- Fullscreen -->
        <button
          @click="$emit('toggle-fullscreen')"
          class="p-1.5 sm:p-2 rounded-md hover:bg-white/20 transition-colors tv-touch-target tv-focusable"
        >
          <ArrowsPointingOutIcon class="w-5 h-5 sm:w-6 sm:h-6" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import {
  PlayIcon,
  PauseIcon,
  SpeakerWaveIcon,
  SpeakerXMarkIcon,
  ArrowsPointingOutIcon,
} from '@heroicons/vue/24/solid'
import QualitySelector from './QualitySelector.vue'

const props = defineProps({
  isPlaying: Boolean,
  currentTime: Number,
  duration: Number,
  volume: Number,
  isMuted: Boolean,
  buffered: Number,
  currentQuality: Number,
  qualities: Array,
})

defineEmits(['toggle-play', 'seek', 'volume-change', 'toggle-mute', 'toggle-fullscreen', 'quality-change'])

const progressBar = ref(null)
const isHovering = ref(false)
const hoverPosition = ref(0)
const hoverTime = ref(0)

const progress = computed(() => {
  if (!props.duration) return 0
  return (props.currentTime / props.duration) * 100
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

const seekTo = (event) => {
  const rect = progressBar.value.getBoundingClientRect()
  const pos = (event.clientX - rect.left) / rect.width
  const time = pos * props.duration
}
</script>
