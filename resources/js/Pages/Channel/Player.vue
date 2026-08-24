<template>
  <div class="min-h-screen bg-black relative" ref="playerContainer">
    <!-- Video Player -->
    <div class="w-full h-screen flex items-center justify-center">
      <ChannelPlayer
        v-if="channel.stream_url"
        ref="videoPlayer"
        :src="channel.stream_url"
        :channel="channel"
        class="w-full h-full"
        style="position:absolute;inset:0;"
        @timeupdate="onTimeUpdate"
        @ended="onEnded"
      />
      <div v-else class="w-full h-full flex items-center justify-center bg-gray-950">
        <div class="text-center">
          <svg class="w-16 h-16 sm:w-20 sm:h-20 text-gray-700 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
          </svg>
          <h2 class="text-xl font-bold text-white mb-2">Stream Unavailable</h2>
          <p class="text-gray-500 text-sm mb-4">This channel doesn't have a stream URL configured.</p>
          <Link :href="route('channels.show', channel.id)" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition-colors tv-touch-target tv-focusable">
            Go Back
          </Link>
        </div>
      </div>
    </div>

    <!-- Channel Info Overlay (auto-hide) -->
    <div
      class="absolute top-0 left-0 right-0 bg-gradient-to-b from-black/80 to-transparent p-3 sm:p-4 transition-opacity duration-300"
      :class="showOverlay ? 'opacity-100' : 'opacity-0 pointer-events-none'"
    >
      <div class="flex items-center justify-between max-w-screen-2xl mx-auto">
        <div class="flex items-center gap-2 sm:gap-3">
          <Link :href="route('channels.show', channel.id)" class="p-1.5 sm:p-2 rounded-lg bg-white/10 hover:bg-white/20 transition-colors tv-touch-target tv-focusable">
            <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </Link>
          <div>
            <h1 class="text-white font-bold text-base sm:text-lg">{{ channel.name }}</h1>
            <p class="text-gray-400 text-xs sm:text-sm">{{ channel.category?.name || '' }}</p>
          </div>
        </div>
        <div class="flex items-center gap-1 sm:gap-2">
          <span v-if="channel.is_live" class="flex items-center gap-1 px-1.5 sm:px-2 py-0.5 bg-red-600 rounded text-white text-[10px] sm:text-xs font-bold">
            <div class="w-1.5 h-1.5 bg-white rounded-full animate-pulse" />
            LIVE
          </span>
          <button
            @click="showEpgSidebar = !showEpgSidebar"
            class="p-1.5 sm:p-2 rounded-lg bg-white/10 hover:bg-white/20 transition-colors tv-touch-target tv-focusable"
            title="EPG"
          >
            <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </button>
          <button
            @click="toggleFullscreen"
            class="p-1.5 sm:p-2 rounded-lg bg-white/10 hover:bg-white/20 transition-colors tv-touch-target tv-focusable"
            title="Fullscreen"
          >
            <svg v-if="!isFullscreen" class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
            </svg>
            <svg v-else class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9V4.5M9 9H4.5M9 9l-5.25 5.25M9 15v4.5M9 15H4.5M9 15l-5.25-5.25M15 9h4.5M15 9V4.5M15 9l5.25 5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25" />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Bottom Controls -->
    <div
      class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent transition-opacity duration-300"
      :class="showOverlay ? 'opacity-100' : 'opacity-0 pointer-events-none'"
    >
      <div class="max-w-screen-2xl mx-auto p-3 sm:p-4">
        <!-- Progress bar area -->
        <div class="mb-3 sm:mb-4">
          <div class="w-full h-1 bg-white/20 rounded-full overflow-hidden cursor-pointer group" @click="seekTo">
            <div class="h-full bg-purple-500 rounded-full transition-all" :style="{ width: progress + '%' }" />
          </div>
          <div class="flex justify-between mt-1">
            <span class="text-white text-xs">{{ formatDuration(currentTime) }}</span>
            <span class="text-gray-400 text-xs">LIVE</span>
          </div>
        </div>

        <div class="flex items-center justify-between flex-wrap gap-2">
          <div class="flex items-center gap-2 sm:gap-3">
            <!-- Play/Pause -->
            <button @click="togglePlay" class="p-1.5 sm:p-2 rounded-lg bg-white/10 hover:bg-white/20 transition-colors tv-touch-target tv-focusable">
              <svg v-if="isPlaying" class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <svg v-else class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </button>

            <!-- Skip buttons -->
            <button @click="skip(-30)" class="p-1.5 sm:p-2 rounded-lg bg-white/10 hover:bg-white/20 transition-colors hidden sm:flex items-center gap-1 tv-touch-target tv-focusable">
              <svg class="w-3 h-3 sm:w-4 sm:h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0019 16V8a1 1 0 00-1.6-.8l-5.333 4zM4.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0011 16V8a1 1 0 00-1.6-.8l-5.333 4z" />
              </svg>
              <span class="text-white text-xs">30s</span>
            </button>
            <button @click="skip(30)" class="p-1.5 sm:p-2 rounded-lg bg-white/10 hover:bg-white/20 transition-colors hidden sm:flex items-center gap-1 tv-touch-target tv-focusable">
              <span class="text-white text-xs">30s</span>
              <svg class="w-3 h-3 sm:w-4 sm:h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.933 12.8a1 1 0 000-1.6L6.6 7.2A1 1 0 005 8v8a1 1 0 001.6.8l5.333-4zM19.933 12.8a1 1 0 000-1.6l-5.333-4A1 1 0 0013 8v8a1 1 0 001.6.8l5.333-4z" />
              </svg>
            </button>
          </div>

          <!-- Center: Volume -->
          <div class="flex items-center gap-2 sm:gap-3">
            <button @click="toggleMute" class="p-1.5 sm:p-2 rounded-lg bg-white/10 hover:bg-white/20 transition-colors tv-touch-target tv-focusable">
              <svg v-if="isMuted || volume === 0" class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
              </svg>
              <svg v-else-if="volume < 50" class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
              </svg>
              <svg v-else class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
              </svg>
            </button>
            <input
              v-model="volume"
              type="range"
              min="0"
              max="100"
              class="w-20 sm:w-24 accent-purple-500 tv-focusable"
              @input="updateVolume"
            />
          </div>

          <!-- Right: Quality -->
          <div class="flex items-center gap-1 sm:gap-2">
            <select
              v-model="selectedQuality"
              class="px-1.5 sm:px-2 py-0.5 sm:py-1 bg-white/10 border-none rounded text-white text-xs sm:text-sm focus:outline-none tv-focusable"
            >
              <option v-for="q in qualities" :key="q.value" :value="q.value">{{ q.label }}</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- EPG Sidebar -->
    <Transition
      enter-active-class="transition-transform duration-300"
      enter-from-class="translate-x-full"
      enter-to-class="translate-x-0"
      leave-active-class="transition-transform duration-300"
      leave-from-class="translate-x-0"
      leave-to-class="translate-x-full"
    >
      <div
        v-if="showEpgSidebar"
        class="absolute top-0 right-0 bottom-0 w-64 sm:w-80 bg-gray-950/95 backdrop-blur-sm border-l border-gray-800 overflow-y-auto z-40"
      >
        <div class="p-3 sm:p-4">
          <div class="flex items-center justify-between mb-3 sm:mb-4">
            <h3 class="text-white font-semibold">Schedule</h3>
            <button @click="showEpgSidebar = false" class="p-1 rounded hover:bg-gray-800 transition-colors tv-touch-target tv-focusable">
              <svg class="w-4 h-4 sm:w-5 sm:h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div v-if="epg.length === 0" class="text-center py-6 sm:py-8">
            <p class="text-gray-500 text-sm">No schedule available</p>
          </div>
          <div v-else class="space-y-2">
            <div
              v-for="program in epg"
              :key="program.id"
              class="p-2 sm:p-3 rounded-lg transition-colors"
              :class="program.is_current ? 'bg-purple-600/20 border border-purple-500/30' : 'hover:bg-gray-800'"
            >
              <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-mono" :class="program.is_current ? 'text-purple-400' : 'text-gray-500'">
                  {{ formatTime(program.start_time) }} - {{ formatTime(program.end_time) }}
                </span>
                <div v-if="program.is_current" class="w-2 h-2 bg-purple-500 rounded-full animate-pulse" />
              </div>
              <p class="text-sm font-medium" :class="program.is_current ? 'text-white' : 'text-gray-300'">
                {{ program.title }}
              </p>
              <p v-if="program.description" class="text-xs text-gray-500 mt-1 line-clamp-2">{{ program.description }}</p>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Click overlay to toggle controls -->
    <div
      class="absolute inset-0 z-10"
      @click="toggleOverlay"
      @mousemove="showOverlayTemp"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import ChannelPlayer from '@/Components/Player/ChannelPlayer.vue'

const props = defineProps({
  channel: { type: Object, required: true },
  epg: { type: Array, default: () => [] },
})

const playerContainer = ref(null)
const videoPlayer = ref(null)
const showOverlay = ref(true)
const showEpgSidebar = ref(false)
const isPlaying = ref(true)
const isMuted = ref(false)
const volume = ref(80)
const currentTime = ref(0)
const progress = ref(0)
const isFullscreen = ref(false)
const selectedQuality = ref('auto')

const qualities = [
  { label: 'Auto', value: 'auto' },
  { label: '1080p', value: '1080' },
  { label: '720p', value: '720' },
  { label: '480p', value: '480' },
  { label: '360p', value: '360' },
]

let overlayTimeout = null

const toggleOverlay = () => {
  showOverlay.value = !showOverlay.value
}

const showOverlayTemp = () => {
  showOverlay.value = true
  clearTimeout(overlayTimeout)
  overlayTimeout = setTimeout(() => {
    if (isPlaying.value) {
      showOverlay.value = false
    }
  }, 3000)
}

const togglePlay = () => {
  if (videoPlayer.value) {
    if (isPlaying.value) {
      videoPlayer.value.pause()
    } else {
      videoPlayer.value.play()
    }
    isPlaying.value = !isPlaying.value
  }
}

const toggleMute = () => {
  if (videoPlayer.value) {
    isMuted.value = !isMuted.value
    videoPlayer.value.muted = isMuted.value
  }
}

const updateVolume = () => {
  if (videoPlayer.value) {
    videoPlayer.value.volume = volume.value / 100
    isMuted.value = volume.value === 0
  }
}

const seekTo = (e) => {
  const rect = e.target.getBoundingClientRect()
  const x = e.clientX - rect.left
  const percent = (x / rect.width) * 100
  progress.value = percent
}

const skip = (seconds) => {
  if (videoPlayer.value) {
    videoPlayer.value.currentTime += seconds
  }
}

const onTimeUpdate = (time) => {
  currentTime.value = time
}

const onEnded = () => {
  isPlaying.value = false
}

const toggleFullscreen = () => {
  if (!document.fullscreenElement) {
    playerContainer.value?.requestFullscreen()
    isFullscreen.value = true
  } else {
    document.exitFullscreen()
    isFullscreen.value = false
  }
}

const formatTime = (dateStr) => {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  })
}

const formatDuration = (seconds) => {
  const mins = Math.floor(seconds / 60)
  const secs = Math.floor(seconds % 60)
  return `${mins}:${secs.toString().padStart(2, '0')}`
}

const handleFullscreenChange = () => {
  isFullscreen.value = !!document.fullscreenElement
}

onMounted(() => {
  document.addEventListener('fullscreenchange', handleFullscreenChange)
  showOverlayTemp()
})

onUnmounted(() => {
  document.removeEventListener('fullscreenchange', handleFullscreenChange)
  clearTimeout(overlayTimeout)
})
</script>

<style scoped>
input[type="range"] {
  -webkit-appearance: none;
  height: 4px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 2px;
  outline: none;
}

input[type="range"]::-webkit-slider-thumb {
  -webkit-appearance: none;
  width: 14px;
  height: 14px;
  background: #a855f7;
  border-radius: 50%;
  cursor: pointer;
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
