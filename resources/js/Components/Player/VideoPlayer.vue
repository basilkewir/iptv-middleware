<template>
  <div
    ref="playerContainer"
    :class="[
      'relative bg-black rounded-lg overflow-hidden group',
      aspectRatioClass,
      { 'cursor-none': !showControls }
    ]"
    @mousemove="showControlsTemporarily"
    @mouseleave="hideControlsImmediately"
    @click="togglePlay"
    @dblclick="toggleFullscreen"
  >
    <!-- Video Element -->
    <video
      ref="videoEl"
      :src="src"
      :poster="poster"
      :autoplay="autoplay"
      :muted="muted"
      :loop="loop"
      class="w-full h-full object-contain"
      @loadedmetadata="onLoadedMetadata"
      @timeupdate="onTimeUpdate"
      @play="onPlay"
      @pause="onPause"
      @ended="onEnded"
      @volumechange="onVolumeChange"
      @waiting="buffering = true"
      @canplay="buffering = false"
    />

    <!-- Buffering Indicator -->
    <Transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="buffering" class="absolute inset-0 flex items-center justify-center bg-black/40">
        <div class="w-12 h-12 sm:w-16 sm:h-16 border-4 border-white/30 border-t-white rounded-full animate-spin" />
      </div>
    </Transition>

    <!-- Play Button Overlay -->
    <Transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="!isPlaying && !buffering"
        class="absolute inset-0 flex items-center justify-center bg-black/30"
      >
        <button
          @click.stop="togglePlay"
          class="w-20 h-20 sm:w-24 sm:h-24 bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center hover:bg-white/30 transition-colors tv-touch-target tv-focusable"
        >
          <PlayIcon class="w-10 h-10 sm:w-12 sm:h-12 text-white ml-1" />
        </button>
      </div>
    </Transition>

    <!-- Controls -->
    <Controls
      v-show="showControls"
      :is-playing="isPlaying"
      :current-time="currentTime"
      :duration="duration"
      :volume="volume"
      :is-muted="isMuted"
      :buffered="buffered"
      :current-quality="currentQuality"
      :qualities="qualities"
      @toggle-play="togglePlay"
      @seek="seek"
      @volume-change="setVolume"
      @toggle-mute="toggleMute"
      @toggle-fullscreen="toggleFullscreen"
      @quality-change="$emit('quality-change', $event)"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { PlayIcon } from '@heroicons/vue/24/solid'
import Controls from './Controls.vue'

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
    validator: (v) => ['16:9', '4:3', '21:9'].includes(v),
  },
  qualities: {
    type: Array,
    default: () => [],
  },
  currentQuality: {
    type: Number,
    default: -1,
  },
})

const emit = defineEmits(['play', 'pause', 'ended', 'quality-change', 'time-update'])

const playerContainer = ref(null)
const videoEl = ref(null)
const isPlaying = ref(false)
const currentTime = ref(0)
const duration = ref(0)
const volume = ref(1)
const isMuted = ref(false)
const buffered = ref(0)
const buffering = ref(false)
const showControls = ref(true)
const controlsTimeout = ref(null)

const aspectRatioClass = computed(() => {
  const ratios = {
    '16:9': 'aspect-video',
    '4:3': 'aspect-[4/3]',
    '21:9': 'aspect-[21/9]',
  }
  return ratios[props.aspectRatio] || 'aspect-video'
})

const showControlsTemporarily = () => {
  showControls.value = true
  clearTimeout(controlsTimeout.value)
  if (isPlaying.value) {
    controlsTimeout.value = setTimeout(() => {
      showControls.value = false
    }, 3000)
  }
}

const hideControlsImmediately = () => {
  if (isPlaying.value) {
    showControls.value = false
  }
}

const togglePlay = () => {
  if (videoEl.value.paused) {
    videoEl.value.play()
  } else {
    videoEl.value.pause()
  }
}

const seek = (time) => {
  videoEl.value.currentTime = time
}

const setVolume = (val) => {
  volume.value = val
  videoEl.value.volume = val
}

const toggleMute = () => {
  isMuted.value = !isMuted.value
  videoEl.value.muted = isMuted.value
}

const toggleFullscreen = () => {
  if (document.fullscreenElement) {
    document.exitFullscreen()
  } else {
    playerContainer.value.requestFullscreen()
  }
}

const onLoadedMetadata = () => {
  duration.value = videoEl.value.duration
}

const onTimeUpdate = () => {
  currentTime.value = videoEl.value.currentTime
  if (videoEl.value.buffered.length > 0) {
    buffered.value = (videoEl.value.buffered.end(videoEl.value.buffered.length - 1) / duration.value) * 100
  }
  emit('time-update', currentTime.value)
}

const onPlay = () => {
  isPlaying.value = true
  emit('play')
}

const onPause = () => {
  isPlaying.value = false
  showControls.value = true
  emit('pause')
}

const onEnded = () => {
  isPlaying.value = false
  showControls.value = true
  emit('ended')
}

const onVolumeChange = () => {
  volume.value = videoEl.value.volume
  isMuted.value = videoEl.value.muted
}

onMounted(() => {
  if (props.muted) {
    videoEl.value.muted = true
  }
})

onUnmounted(() => {
  clearTimeout(controlsTimeout.value)
})
</script>
