<template>
  <VideoPlayer
    ref="playerRef"
    :poster="poster"
    :autoplay="autoplay"
    :muted="muted"
    :loop="loop"
    :aspect-ratio="aspectRatio"
    :qualities="qualities"
    :current-quality="currentQuality"
    @quality-change="setQuality"
    @time-update="onTimeUpdate"
  />
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import Hls from 'hls.js'
import VideoPlayer from './VideoPlayer.vue'

const props = defineProps({
  src: {
    type: String,
    required: true,
  },
  poster: {
    type: String,
    default: '',
  },
  autoplay: {
    type: Boolean,
    default: false,
  },
  muted: {
    type: Boolean,
    default: false,
  },
  loop: {
    type: Boolean,
    default: false,
  },
  aspectRatio: {
    type: String,
    default: '16:9',
  },
})

const emit = defineEmits(['quality-change', 'time-update', 'error'])

const playerRef = ref(null)
const hls = ref(null)
const qualities = ref([])
const currentQuality = ref(-1)

const initHLS = () => {
  if (!Hls.isSupported()) {
    // Fallback to native video for Safari
    const video = playerRef.value?.$refs?.videoEl
    if (video) {
      video.src = props.src
    }
    return
  }

  hls.value = new Hls({
    maxBufferLength: 30,
    maxMaxBufferLength: 60,
    startLevel: -1, // Auto quality
  })

  const video = playerRef.value?.$refs?.videoEl
  if (!video) return

  hls.value.loadSource(props.src)
  hls.value.attachMedia(video)

  hls.value.on(Hls.Events.MANIFEST_PARSED, (event, data) => {
    qualities.value = data.levels.map((level, index) => ({
      index,
      height: level.height,
      width: level.width,
      bitrate: level.bitrate,
      label: `${level.height}p`,
    }))

    if (props.autoplay) {
      video.play()
    }
  })

  hls.value.on(Hls.Events.LEVEL_SWITCHED, (event, data) => {
    currentQuality.value = data.level
    emit('quality-change', data.level)
  })

  hls.value.on(Hls.Events.ERROR, (event, data) => {
    if (data.fatal) {
      switch (data.type) {
        case Hls.ErrorTypes.NETWORK_ERROR:
          hls.value.startLoad()
          break
        case Hls.ErrorTypes.MEDIA_ERROR:
          hls.value.recoverMediaError()
          break
        default:
          emit('error', data)
          break
      }
    }
  })
}

const setQuality = (level) => {
  if (hls.value) {
    hls.value.currentLevel = level
    currentQuality.value = level
  }
}

const onTimeUpdate = (time) => {
  emit('time-update', time)
}

watch(() => props.src, (newSrc) => {
  if (hls.value) {
    hls.value.destroy()
  }
  initHLS()
})

onMounted(() => {
  initHLS()
})

onUnmounted(() => {
  if (hls.value) {
    hls.value.destroy()
  }
})

defineExpose({
  setQuality,
  qualities,
  currentQuality,
})
</script>
