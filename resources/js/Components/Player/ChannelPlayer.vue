<template>
  <div class="w-full h-full bg-black relative">
    <video
      ref="videoEl"
      class="w-full h-full object-cover"
      playsinline
      @waiting="buffering = true"
      @canplay="buffering = false"
      @playing="buffering = false"
      @timeupdate="onTimeUpdate"
      @ended="$emit('ended')"
    />
    <div v-if="buffering" class="absolute inset-0 flex items-center justify-center pointer-events-none">
      <div class="w-12 h-12 border-4 border-white/30 border-t-white rounded-full animate-spin" />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import Hls from 'hls.js'

const props = defineProps({
  src: { type: String, required: true },
  channel: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['timeupdate', 'ended'])

const videoEl = ref(null)
const buffering = ref(false)
let hls = null

const onTimeUpdate = () => {
  emit('timeupdate', videoEl.value?.currentTime || 0)
}

const initHLS = () => {
  destroyHLS()
  const video = videoEl.value
  if (!video || !props.src) return

  if (Hls.isSupported()) {
    hls = new Hls({
      maxBufferLength: 10,
      maxMaxBufferLength: 30,
      startLevel: -1,
      enableWorker: true,
      lowLatencyMode: true,
      backBufferLength: 0,
      liveSyncDurationCount: 3,
      liveMaxLatencyDurationCount: 6,
    })
    hls.loadSource(props.src)
    hls.attachMedia(video)
    hls.on(Hls.Events.MANIFEST_PARSED, () => {
      video.play().catch(() => {})
    })
    hls.on(Hls.Events.ERROR, (_event, data) => {
      if (data.fatal) {
        if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
          hls.startLoad()
        } else if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
          hls.recoverMediaError()
        }
      }
    })
  } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
    video.src = props.src
    video.addEventListener('loadedmetadata', () => {
      video.play().catch(() => {})
    }, { once: true })
  }
}

const destroyHLS = () => {
  if (hls) {
    hls.destroy()
    hls = null
  }
}

watch(() => props.src, () => {
  initHLS()
})

onMounted(() => {
  initHLS()
})

onUnmounted(() => {
  destroyHLS()
})

defineExpose({
  play: () => videoEl.value?.play(),
  pause: () => videoEl.value?.pause(),
  get paused() { return videoEl.value?.paused ?? true },
  set muted(v) { if (videoEl.value) videoEl.value.muted = v },
  get muted() { return videoEl.value?.muted ?? false },
  set volume(v) { if (videoEl.value) videoEl.value.volume = v },
  get volume() { return videoEl.value?.volume ?? 1 },
  set currentTime(v) { if (videoEl.value) videoEl.value.currentTime = v },
  get currentTime() { return videoEl.value?.currentTime ?? 0 },
  get duration() { return videoEl.value?.duration ?? 0 },
})
</script>
