<template>
  <div class="space-y-6">
    <!-- Top bar: status + controls -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <span class="flex items-center gap-1.5 text-sm font-medium"
          :class="isLive ? 'text-red-400' : 'text-gray-400'">
          <span v-if="isLive" class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
          <span v-else class="w-2 h-2 rounded-full bg-gray-500"></span>
          {{ isLive ? 'ON AIR' : 'OFF AIR' }}
        </span>
        <span class="text-gray-600">|</span>
        <span class="text-gray-400 text-sm">{{ channel.channel_name }}</span>
        <span v-if="isLive" class="text-gray-500 text-xs font-mono">{{ liveTimer }}</span>
      </div>
      <div class="flex gap-2">
        <button v-if="!isLive" @click="startBroadcast" :disabled="starting"
          class="px-5 py-2 bg-red-600 hover:bg-red-500 text-white rounded-lg font-medium transition flex items-center gap-2 disabled:opacity-50">
          <Radio class="w-4 h-4" v-if="!starting" />
          <Loader2 class="w-4 h-4 animate-spin" v-else />
          Go Live
        </button>
        <button v-else @click="stopBroadcast" :disabled="stopping"
          class="px-5 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg font-medium transition flex items-center gap-2 disabled:opacity-50">
          <StopCircle class="w-4 h-4" v-if="!stopping" />
          <Loader2 class="w-4 h-4 animate-spin" v-else />
          End Broadcast
        </button>
      </div>
    </div>
    <div v-if="broadcastError" class="mt-2 text-red-400 text-sm font-medium">{{ broadcastError }}</div>

    <!-- Main playout layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

      <!-- Preview monitor -->
      <div class="lg:col-span-2 space-y-3">
        <div class="relative bg-black rounded-xl overflow-hidden aspect-video border border-gray-700"
          :class="isLive ? 'border-red-600/60 ring-1 ring-red-600/30' : ''">

          <!-- Video player -->
          <video ref="videoRef"
            class="w-full h-full object-contain" controls autoplay muted
            @timeupdate="onTimeUpdate" @ended="onVideoEnded">
          </video>

          <!-- Offline slate -->
          <div v-if="!isLive" class="absolute inset-0 flex flex-col items-center justify-center gap-3">
            <Tv class="w-16 h-16 text-gray-700" />
            <span class="text-gray-500 text-sm">Channel is offline</span>
          </div>

          <!-- ON AIR badge -->
          <div v-if="isLive" class="absolute top-3 left-3 flex items-center gap-1.5 px-2 py-1 bg-red-600 rounded text-white text-xs font-bold">
            <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
            LIVE
          </div>

          <!-- Overlay preview: ticker -->
          <div v-if="channel.enable_ticker && channel.ticker_text"
            class="absolute bottom-0 left-0 right-0 py-1.5 text-sm font-medium overflow-hidden whitespace-nowrap"
            :style="{ background: channel.ticker_background || '#000000cc', color: channel.ticker_color || '#ffffff' }">
            <span class="ticker-text px-2" :style="{ animationDuration: tickerDuration }">{{ channel.ticker_text }}</span>
          </div>

          <!-- Overlay preview: logo -->
          <div v-if="channel.enable_overlay_logo && channel.logo_url"
            class="absolute pointer-events-none"
            :class="logoPositionClass"
            :style="{ opacity: channel.overlay_logo_opacity || 1 }">
            <img :src="channel.logo_url" class="h-8 w-auto object-contain"
              :style="{ transform: `scale(${(channel.overlay_logo_size || 100) / 100})`, transformOrigin: 'top left' }" />
          </div>

          <!-- Overlay preview: clock -->
          <div v-if="channel.enable_overlay_clock"
            class="absolute text-white text-xs font-mono bg-black/50 px-2 py-0.5 rounded pointer-events-none"
            :class="clockPositionClass">
            {{ clockDisplay }}
          </div>

          <!-- Watermark -->
          <div v-if="channel.enable_watermark && channel.watermark_url"
            class="absolute pointer-events-none"
            :class="watermarkPositionClass"
            :style="{ opacity: channel.watermark_opacity || 0.5 }">
            <img :src="channel.watermark_url" class="h-6 w-auto object-contain" />
          </div>
        </div>

        <!-- Progress bar for current item -->
        <div v-if="currentItem" class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-2 min-w-0">
              <Play class="w-3.5 h-3.5 text-red-400 shrink-0" />
              <span class="text-white text-sm font-medium truncate">{{ currentItem.content?.title || currentItem.content?.file_name }}</span>
              <span class="text-xs px-1.5 py-0.5 rounded font-medium shrink-0"
                :class="qualityColor(currentItem.content?.quality_level)">
                {{ currentItem.content?.quality_level?.toUpperCase() }}
              </span>
            </div>
            <span class="text-gray-400 text-xs font-mono shrink-0 ml-2">
              {{ formatTime(currentTime) }} / {{ formatTime(currentItem.content?.duration) }}
            </span>
          </div>
          <div class="w-full bg-gray-700 rounded-full h-1.5">
            <div class="bg-red-500 h-1.5 rounded-full transition-all"
              :style="{ width: itemProgress + '%' }"></div>
          </div>
        </div>

        <!-- Stream info -->
        <div class="grid grid-cols-3 gap-3">
          <div class="bg-gray-800 rounded-xl p-3 border border-gray-700 text-center">
            <div class="text-lg font-bold text-white">{{ broadcast?.total_viewers ?? 0 }}</div>
            <div class="text-xs text-gray-400 mt-0.5">Viewers</div>
          </div>
          <div class="bg-gray-800 rounded-xl p-3 border border-gray-700 text-center">
            <div class="text-lg font-bold text-white">{{ broadcast?.peak_viewers ?? 0 }}</div>
            <div class="text-xs text-gray-400 mt-0.5">Peak</div>
          </div>
          <div class="bg-gray-800 rounded-xl p-3 border border-gray-700 text-center">
            <div class="text-lg font-bold text-white">{{ liveTimer || '—' }}</div>
            <div class="text-xs text-gray-400 mt-0.5">Duration</div>
          </div>
        </div>

        <!-- Stream URL -->
        <div v-if="streamUrl" class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center justify-between mb-1">
            <span class="text-xs text-gray-400 uppercase tracking-wide">Stream URL</span>
            <button @click="copyUrl" class="text-xs text-indigo-400 hover:text-indigo-300 transition">
              {{ copied ? '✓ Copied' : 'Copy' }}
            </button>
          </div>
          <code class="text-xs text-green-400 font-mono break-all">{{ streamUrl }}</code>
        </div>
      </div>

      <!-- Right panel: playlist queue -->
      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <h4 class="text-sm font-medium text-gray-300">Up Next</h4>
          <span class="text-xs text-gray-500">{{ playlist.length }} items</span>
        </div>

        <div v-if="playlist.length" class="space-y-1.5 max-h-[520px] overflow-y-auto pr-1">
          <div v-for="(item, idx) in playlist" :key="item.id"
            class="flex items-center gap-2.5 p-2.5 rounded-lg border transition"
            :class="idx === currentIndex
              ? 'bg-red-600/10 border-red-600/40'
              : 'bg-gray-800 border-gray-700 hover:border-gray-600'">
            <!-- Thumbnail -->
            <div class="w-14 h-10 bg-gray-700 rounded overflow-hidden shrink-0 relative">
              <img v-if="item.content?.thumbnail_url"
                :src="'/storage/' + item.content.thumbnail_url"
                class="w-full h-full object-cover" />
              <Play v-else class="w-4 h-4 text-gray-500 absolute inset-0 m-auto" />
              <div v-if="idx === currentIndex"
                class="absolute inset-0 bg-red-600/30 flex items-center justify-center">
                <span class="text-white text-xs font-bold">NOW</span>
              </div>
            </div>
            <!-- Info -->
            <div class="flex-1 min-w-0">
              <div class="text-white text-xs font-medium truncate">
                {{ item.content?.title || item.content?.file_name || 'Unknown' }}
              </div>
              <div class="flex items-center gap-1.5 mt-0.5">
                <span class="text-xs font-medium" :class="qualityColor(item.content?.quality_level)">
                  {{ item.content?.quality_level?.toUpperCase() }}
                </span>
                <span class="text-gray-500 text-xs">{{ formatDuration(item.content?.duration) }}</span>
              </div>
            </div>
            <!-- Index -->
            <span class="text-gray-600 text-xs shrink-0">{{ idx + 1 }}</span>
          </div>
        </div>

        <div v-else class="text-center py-8 text-gray-500 text-sm bg-gray-800 rounded-xl border border-gray-700">
          <ListVideo class="w-8 h-8 mx-auto mb-2 opacity-40" />
          No playlist items
        </div>

        <!-- Settings summary -->
        <div v-if="settings" class="bg-gray-800 rounded-xl p-4 border border-gray-700 space-y-2 text-xs">
          <div class="text-gray-400 font-medium uppercase tracking-wide mb-2">Broadcast Settings</div>
          <div class="flex justify-between"><span class="text-gray-500">Mode</span><span class="text-white">{{ settings.broadcast_mode || '—' }}</span></div>
          <div class="flex justify-between"><span class="text-gray-500">Quality</span><span class="text-white">{{ settings.default_quality || '—' }}</span></div>
          <div class="flex justify-between"><span class="text-gray-500">DVR</span><span class="text-white">{{ settings.enable_dvr ? 'On' : 'Off' }}</span></div>
          <div class="flex justify-between"><span class="text-gray-500">Timeshift</span><span class="text-white">{{ settings.enable_timeshift ? settings.timeshift_duration + 'm' : 'Off' }}</span></div>
          <div class="flex justify-between"><span class="text-gray-500">Loop</span><span class="text-white">{{ channel.loop_playlist ? 'Yes' : 'No' }}</span></div>
          <div class="flex justify-between"><span class="text-gray-500">Shuffle</span><span class="text-white">{{ channel.shuffle_mode ? 'Yes' : 'No' }}</span></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import { useApiFetch } from '@/Composables/useApiFetch'
import { Radio, StopCircle, Loader2, Play, Tv, ListVideo } from 'lucide-vue-next'
import Hls from 'hls.js'

const props = defineProps({
  channel: { type: Object, required: true },
  broadcast: Object,
})

const { apiFetch } = useApiFetch()
const broadcastError = ref('')
const settings = ref(null)
const playlist = ref([])
const starting = ref(false)
const stopping = ref(false)
const broadcast = ref(props.broadcast || null)
const videoRef = ref(null)
const currentTime = ref(0)
const currentIndex = ref(0)
const copied = ref(false)
const clockDisplay = ref('')
const liveTimer = ref('')
const liveStartTime = ref(null)

// Timers
let clockInterval = null
let timerInterval = null

const isLive = computed(() => {
  const s = broadcast.value?.status || props.channel?.broadcast_status
  return s === 'live' || s === 'starting' || s === 'running'
})

const currentItem = computed(() => playlist.value[currentIndex.value] || null)

const itemProgress = computed(() => {
  const dur = currentItem.value?.content?.duration
  if (!dur || !currentTime.value) return 0
  return Math.min((currentTime.value / dur) * 100, 100)
})

const streamUrl = computed(() => {
  if (!isLive.value) return null
  const base = window.location.origin
  return `${base}/hls/admin-channel-${props.channel.channel_slug}/index.m3u8`
})

const tickerDuration = computed(() => {
  const s = Math.round(40 - ((props.channel.ticker_speed - 10) / 90) * 35)
  return `${s}s`
})

const positionMap = {
  'top-left': 'top-3 left-3',
  'top-right': 'top-3 right-3',
  'bottom-left': 'bottom-8 left-3',
  'bottom-right': 'bottom-8 right-3',
  'center': 'top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2',
}

const logoPositionClass = computed(() => positionMap[props.channel.overlay_logo_position] || 'top-3 left-3')
const clockPositionClass = computed(() => positionMap[props.channel.overlay_clock_position] || 'top-3 right-3')
const watermarkPositionClass = computed(() => positionMap[props.channel.watermark_position] || 'bottom-8 right-3')

let hlsInstance = null

const initHlsPlayer = (url) => {
  if (!videoRef.value) return
  if (hlsInstance) { hlsInstance.destroy(); hlsInstance = null }

  if (Hls.isSupported()) {
    hlsInstance = new Hls({
      liveSyncDurationCount: 3,
      liveMaxLatencyDurationCount: 6,
      manifestLoadingMaxRetry: 20,
      manifestLoadingRetryDelay: 1000,
      levelLoadingMaxRetry: 20,
      levelLoadingRetryDelay: 1000,
      fragLoadingMaxRetry: 20,
      fragLoadingRetryDelay: 1000,
      lowLatencyMode: false,
    })
    hlsInstance.loadSource(url)
    hlsInstance.attachMedia(videoRef.value)
    hlsInstance.on(Hls.Events.MANIFEST_PARSED, () => videoRef.value?.play().catch(() => {}))
    hlsInstance.on(Hls.Events.ERROR, (event, data) => {
      if (data.fatal) {
        if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
          hlsInstance.startLoad()
        } else if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
          hlsInstance.recoverMediaError()
        }
      }
    })
  } else if (videoRef.value.canPlayType('application/vnd.apple.mpegurl')) {
    videoRef.value.src = url
    videoRef.value.play().catch(() => {})
  }
}

const destroyHlsPlayer = () => {
  if (hlsInstance) { hlsInstance.destroy(); hlsInstance = null }
  if (videoRef.value) { videoRef.value.src = '' }
}

const onTimeUpdate = () => {
  if (videoRef.value) currentTime.value = videoRef.value.currentTime
}

const onVideoEnded = () => {
  if (props.channel.loop_playlist) {
    currentIndex.value = (currentIndex.value + 1) % Math.max(playlist.value.length, 1)
  }
}

const qualityColor = (q) => ({
  '4k': 'text-purple-400 bg-purple-400/10',
  fhd: 'text-blue-400 bg-blue-400/10',
  hd: 'text-green-400 bg-green-400/10',
  sd: 'text-yellow-400 bg-yellow-400/10',
  low: 'text-gray-400 bg-gray-400/10',
}[q] || 'text-gray-400')

const formatTime = (s) => {
  if (!s) return '0:00'
  const m = Math.floor(s / 60)
  const sec = Math.floor(s % 60)
  return `${m}:${sec.toString().padStart(2, '0')}`
}

const formatDuration = (s) => {
  if (!s) return '—'
  if (s < 60) return `${s}s`
  return `${Math.floor(s / 60)}m ${s % 60}s`
}

const copyUrl = async () => {
  if (!streamUrl.value) return
  await navigator.clipboard.writeText(streamUrl.value)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}

const updateClock = () => {
  const now = new Date()
  const fmt = props.channel.overlay_clock_format || 'HH:MM:SS'
  const pad = (n) => String(n).padStart(2, '0')
  if (fmt === 'HH:MM:SS') clockDisplay.value = `${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`
  else if (fmt === 'HH:MM') clockDisplay.value = `${pad(now.getHours())}:${pad(now.getMinutes())}`
  else if (fmt === 'MM/DD/YYYY') clockDisplay.value = `${pad(now.getMonth()+1)}/${pad(now.getDate())}/${now.getFullYear()}`
  else clockDisplay.value = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}`
}

const updateTimer = () => {
  if (!liveStartTime.value) return
  const elapsed = Math.floor((Date.now() - liveStartTime.value) / 1000)
  const h = Math.floor(elapsed / 3600)
  const m = Math.floor((elapsed % 3600) / 60)
  const s = elapsed % 60
  const pad = (n) => String(n).padStart(2, '0')
  liveTimer.value = h > 0 ? `${pad(h)}:${pad(m)}:${pad(s)}` : `${pad(m)}:${pad(s)}`
}

const fetchSettings = async () => {
  const res = await apiFetch(route('admin.channels.my-channel.settings', props.channel.channel_slug))
  const json = await res.json()
  settings.value = json.settings || null
}

const fetchPlaylist = async () => {
  const res = await apiFetch(route('admin.channels.my-channel.playlist', props.channel.channel_slug))
  const json = await res.json()
  playlist.value = json.playlist || []
}

const fetchBroadcast = async () => {
  const res = await apiFetch(route('admin.channels.my-channel.broadcast', props.channel.channel_slug))
  const json = await res.json()
  broadcast.value = json.broadcast || null
  if (broadcast.value?.start_time) {
    liveStartTime.value = new Date(broadcast.value.start_time).getTime()
  }
}

const startBroadcast = async () => {
  starting.value = true
  broadcastError.value = ''
  try {
    const res = await apiFetch(route('admin.channels.my-channel.broadcast.start', props.channel.channel_slug), { method: 'POST' })
    if (!res.ok) {
      const json = await res.json().catch(() => ({}))
      broadcastError.value = json.error || json.message || 'Failed to start broadcast'
      return
    }
    await fetchBroadcast()
    router.reload({ only: ['channel'] })
  } catch (e) {
    broadcastError.value = e?.message || 'Failed to start broadcast'
  } finally {
    starting.value = false
  }
}

const stopBroadcast = async () => {
  stopping.value = true
  broadcastError.value = ''
  try {
    const res = await apiFetch(route('admin.channels.my-channel.broadcast.stop', props.channel.channel_slug), { method: 'POST' })
    if (!res.ok) {
      const json = await res.json().catch(() => ({}))
      broadcastError.value = json.error || json.message || 'Failed to stop broadcast'
      return
    }
    broadcast.value = null
    liveStartTime.value = null
    liveTimer.value = ''
  } catch (e) {
    broadcastError.value = e?.message || 'Failed to stop broadcast'
  } finally {
    stopping.value = false
  }
}

watch(isLive, (live) => {
  if (live) {
    if (!timerInterval) timerInterval = setInterval(updateTimer, 1000)
    if (streamUrl.value) initHlsPlayer(streamUrl.value)
  } else {
    clearInterval(timerInterval)
    timerInterval = null
    liveTimer.value = ''
    destroyHlsPlayer()
  }
})

onMounted(async () => {
  await Promise.all([fetchSettings(), fetchPlaylist(), fetchBroadcast()])
  clockInterval = setInterval(updateClock, 1000)
  updateClock()
  if (isLive.value) {
    timerInterval = setInterval(updateTimer, 1000)
    if (streamUrl.value) initHlsPlayer(streamUrl.value)
  }
})

onUnmounted(() => {
  clearInterval(clockInterval)
  clearInterval(timerInterval)
  destroyHlsPlayer()
})
</script>

<style scoped>
@keyframes ticker-scroll {
  0%   { transform: translateX(100vw); }
  100% { transform: translateX(-100%); }
}
.ticker-text {
  display: inline-block;
  animation: ticker-scroll linear infinite;
}
</style>
