<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'

const props = defineProps({
  item: Object,
  episode: Object,
  episodes: Array,
  seasons: Array,
  videoUrl: String,
  subtitles: Array,
  qualities: Array,
  currentTime: Number,
})

const videoRef = ref(null)
const isPlaying = ref(false)
const progress = ref(0)
const volume = ref(1)
const isMuted = ref(false)
const currentTimeVal = ref(props.currentTime || 0)
const duration = ref(0)
const showControls = ref(true)
const showEpisodeList = ref(false)
const selectedQuality = ref(props.qualities?.[0]?.value || 'auto')
const selectedSubtitle = ref('')
const buffered = ref(0)
const isFullscreen = ref(false)
const controlsTimeout = ref(null)

const isSeries = computed(() => props.item?.type === 'series')
const currentEpisode = computed(() => props.episode)

const formatTime = (seconds) => {
  const h = Math.floor(seconds / 3600)
  const m = Math.floor((seconds % 3600) / 60)
  const s = Math.floor(seconds % 60)
  return h > 0
    ? `${h}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`
    : `${m}:${s.toString().padStart(2, '0')}`
}

const togglePlay = () => {
  const video = videoRef.value
  if (!video) return
  if (video.paused) {
    video.play()
    isPlaying.value = true
  } else {
    video.pause()
    isPlaying.value = false
  }
}

const seek = (e) => {
  const video = videoRef.value
  if (!video) return
  const rect = e.currentTarget.getBoundingClientRect()
  const percent = (e.clientX - rect.left) / rect.width
  video.currentTime = percent * video.duration
}

const toggleMute = () => {
  const video = videoRef.value
  if (!video) return
  video.muted = !video.muted
  isMuted.value = video.muted
}

const changeVolume = (e) => {
  const video = videoRef.value
  if (!video) return
  const rect = e.currentTarget.getBoundingClientRect()
  const percent = (e.clientX - rect.left) / rect.width
  video.volume = Math.max(0, Math.min(1, percent))
  volume.value = video.volume
  isMuted.value = video.volume === 0
}

const toggleFullscreen = () => {
  const container = document.getElementById('player-container')
  if (!container) return
  if (!document.fullscreenElement) {
    container.requestFullscreen()
    isFullscreen.value = true
  } else {
    document.exitFullscreen()
    isFullscreen.value = false
  }
}

const playEpisode = (ep) => {
  router.visit(route('vod.player', { item: props.item.slug, episode: ep.id }))
}

const saveProgress = () => {
  const video = videoRef.value
  if (!video || !props.item) return
  fetch(route('vod.progress.store', props.item.slug), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify({
      episode_id: props.episode?.id,
      progress: video.currentTime,
      duration: video.duration,
    }),
  })
}

const showControlsTemporarily = () => {
  showControls.value = true
  clearTimeout(controlsTimeout.value)
  controlsTimeout.value = setTimeout(() => {
    if (isPlaying.value) showControls.value = false
  }, 3000)
}

const goBack = () => {
  window.history.back()
}

const handleKeydown = (e) => {
  if (!document.fullscreenElement) return
  switch (e.key) {
    case ' ':
      e.preventDefault()
      togglePlay()
      break
    case 'ArrowLeft':
      e.preventDefault()
      if (videoRef.value) videoRef.value.currentTime -= 10
      break
    case 'ArrowRight':
      e.preventDefault()
      if (videoRef.value) videoRef.value.currentTime += 10
      break
    case 'ArrowUp':
      e.preventDefault()
      if (videoRef.value) videoRef.value.volume = Math.min(1, videoRef.value.volume + 0.1)
      break
    case 'ArrowDown':
      e.preventDefault()
      if (videoRef.value) videoRef.value.volume = Math.max(0, videoRef.value.volume - 0.1)
      break
    case 'Escape':
      if (showEpisodeList.value) {
        showEpisodeList.value = false
      } else if (document.fullscreenElement) {
        document.exitFullscreen()
      }
      break
  }
}

onMounted(() => {
  const video = videoRef.value
  if (!video) return

  video.addEventListener('timeupdate', () => {
    currentTimeVal.value = video.currentTime
    progress.value = (video.currentTime / video.duration) * 100
  })

  video.addEventListener('loadedmetadata', () => {
    duration.value = video.duration
  })

  video.addEventListener('progress', () => {
    if (video.buffered.length > 0) {
      buffered.value = (video.buffered.end(video.buffered.length - 1) / video.duration) * 100
    }
  })

  video.addEventListener('ended', () => {
    isPlaying.value = false
    if (isSeries.value && props.episodes) {
      const idx = props.episodes.findIndex(ep => ep.id === props.episode?.id)
      if (idx >= 0 && idx < props.episodes.length - 1) {
        playEpisode(props.episodes[idx + 1])
      }
    }
  })

  document.addEventListener('mousemove', showControlsTemporarily)
  document.addEventListener('fullscreenchange', () => {
    isFullscreen.value = !!document.fullscreenElement
  })
  document.addEventListener('keydown', handleKeydown)

  video.play().then(() => { isPlaying.value = true }).catch(() => {})
})

onUnmounted(() => {
  saveProgress()
  clearTimeout(controlsTimeout.value)
  document.removeEventListener('mousemove', showControlsTemporarily)
  document.removeEventListener('keydown', handleKeydown)
})

watch(() => selectedQuality.value, (q) => {
  const video = videoRef.value
  if (!video) return
  const currentTime = video.currentTime
  const wasPlaying = !video.paused
  video.src = `${props.videoUrl}?quality=${q}`
  video.currentTime = currentTime
  if (wasPlaying) video.play()
})
</script>

<template>
  <div class="min-h-screen bg-black text-white">
    <div id="player-container" class="relative w-full h-screen flex">
      <div class="flex-1 relative" @mousemove="showControlsTemporarily">
        <video
          ref="videoRef"
          :src="videoUrl"
          class="w-full h-full object-contain"
          preload="auto"
          crossorigin="anonymous"
          @click="togglePlay"
        />

        <transition
          enter-active-class="transition-opacity duration-200"
          leave-active-class="transition-opacity duration-200"
          enter-from-class="opacity-0"
          leave-to-class="opacity-0"
        >
          <div v-show="showControls" class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-black/40 pointer-events-none">
            <div class="absolute top-2 sm:top-4 left-2 sm:left-4 pointer-events-auto">
              <button @click="goBack" class="flex items-center gap-1 sm:gap-2 text-white hover:text-indigo-400 transition tv-touch-target tv-focusable">
                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                <span class="hidden sm:inline">Back</span>
              </button>
            </div>

            <div class="absolute top-2 sm:top-4 right-2 sm:right-4 flex items-center gap-3 sm:gap-4 pointer-events-auto">
              <button v-if="isSeries" @click="showEpisodeList = !showEpisodeList" class="text-white hover:text-indigo-400 transition tv-touch-target tv-focusable">
                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" /></svg>
              </button>
            </div>

            <div class="absolute inset-0 flex items-center justify-center pointer-events-auto">
              <button @click="togglePlay" class="w-16 h-16 sm:w-20 sm:h-20 bg-white/20 hover:bg-white/30 rounded-full flex items-center justify-center backdrop-blur-sm transition tv-touch-target tv-focusable">
                <svg v-if="!isPlaying" class="w-8 h-8 sm:w-10 sm:h-10 ml-1" fill="currentColor" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11v11.78a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z" /></svg>
                <svg v-else class="w-8 h-8 sm:w-10 sm:h-10" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM7 8a1 1 0 012 0v4a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v4a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
              </button>
            </div>

            <div class="absolute bottom-0 left-0 right-0 p-3 sm:p-4 pointer-events-auto">
              <div class="max-w-4xl mx-auto">
                <p v-if="isSeries && currentEpisode" class="text-sm text-gray-300 mb-2">
                  S{{ currentEpisode.season_number }}E{{ currentEpisode.number }} - {{ currentEpisode.title }}
                </p>

                <div class="relative h-1 bg-gray-600 rounded-full mb-3 sm:mb-4 cursor-pointer group" @click="seek">
                  <div class="absolute h-full bg-gray-500 rounded-full" :style="{ width: buffered + '%' }" />
                  <div class="absolute h-full bg-indigo-500 rounded-full" :style="{ width: progress + '%' }" />
                  <div class="absolute w-3 h-3 bg-indigo-400 rounded-full top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 transition" :style="{ left: `calc(${progress}% - 6px)` }" />
                </div>

                <div class="flex items-center justify-between flex-wrap gap-2 sm:gap-4">
                  <div class="flex items-center gap-3 sm:gap-4">
                    <button @click="togglePlay" class="hover:text-indigo-400 transition tv-touch-target tv-focusable">
                      <svg v-if="!isPlaying" class="w-7 h-7 sm:w-8 sm:h-8" fill="currentColor" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11v11.78a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z" /></svg>
                      <svg v-else class="w-7 h-7 sm:w-8 sm:h-8" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM7 8a1 1 0 012 0v4a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v4a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                    </button>

                    <div class="flex items-center gap-2">
                      <button @click="toggleMute" class="hover:text-indigo-400 transition tv-touch-target tv-focusable">
                        <svg v-if="isMuted || volume === 0" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" /></svg>
                        <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072M18.364 5.636a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" /></svg>
                      </button>
                      <div class="w-16 sm:w-20 h-1 bg-gray-600 rounded-full cursor-pointer" @click="changeVolume">
                        <div class="h-full bg-white rounded-full" :style="{ width: (isMuted ? 0 : volume) * 100 + '%' }" />
                      </div>
                    </div>

                    <span class="text-xs sm:text-sm text-gray-300">{{ formatTime(currentTimeVal) }} / {{ formatTime(duration) }}</span>
                  </div>

                  <div class="flex items-center gap-2 sm:gap-4">
                    <select v-model="selectedSubtitle" class="bg-transparent text-xs sm:text-sm border border-gray-600 rounded px-1.5 sm:px-2 py-0.5 sm:py-1 focus:ring-1 focus:ring-indigo-500 tv-focusable">
                      <option value="">Subtitles Off</option>
                      <option v-for="sub in subtitles" :key="sub.id" :value="sub.id">{{ sub.label }}</option>
                    </select>

                    <select v-model="selectedQuality" class="bg-transparent text-xs sm:text-sm border border-gray-600 rounded px-1.5 sm:px-2 py-0.5 sm:py-1 focus:ring-1 focus:ring-indigo-500 tv-focusable">
                      <option v-for="q in qualities" :key="q.value" :value="q.value">{{ q.label }}</option>
                    </select>

                    <button @click="toggleFullscreen" class="hover:text-indigo-400 transition tv-touch-target tv-focusable">
                      <svg v-if="!isFullscreen" class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
                      <svg v-else class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9V4m0 0H4m5 0l-5 5m11-5l-5 5m0 0v5m0-5h5m-5 0l5-5M9 15v5m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </transition>
      </div>

      <transition
        enter-active-class="transition-transform duration-300"
        leave-active-class="transition-transform duration-300"
        enter-from-class="translate-x-full"
        leave-to-class="translate-x-full"
      >
        <aside v-if="showEpisodeList && isSeries" class="w-64 sm:w-80 bg-gray-900 border-l border-gray-800 overflow-y-auto flex-shrink-0">
          <div class="p-4">
            <div class="flex items-center justify-between mb-4">
              <h3 class="font-semibold">{{ item.title }}</h3>
              <button @click="showEpisodeList = false" class="text-gray-400 hover:text-white tv-touch-target tv-focusable">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
              </button>
            </div>

            <div v-for="season in seasons" :key="season.id" class="mb-4">
              <h4 class="text-sm font-medium text-gray-400 mb-2">Season {{ season.number }}</h4>
              <div class="space-y-1">
                <button
                  v-for="ep in episodes?.filter(e => e.season_id === season.id)"
                  :key="ep.id"
                  @click="playEpisode(ep)"
                  :class="[
                    'w-full text-left px-3 py-2 rounded text-sm transition tv-touch-target tv-focusable',
                    currentEpisode?.id === ep.id ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-800'
                  ]"
                >
                  <span class="text-gray-500 mr-2">{{ ep.number }}.</span>
                  {{ ep.title }}
                </button>
              </div>
            </div>
          </div>
        </aside>
      </transition>
    </div>
  </div>
</template>
