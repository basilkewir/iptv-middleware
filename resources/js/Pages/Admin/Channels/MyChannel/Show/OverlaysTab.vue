<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h3 class="text-lg font-semibold text-white">Overlays Configuration</h3>
      <button @click="saveOverlays" :disabled="saving"
        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition flex items-center gap-2 disabled:opacity-50">
        <span v-if="saved" class="text-green-300">✓ Saved</span>
        <span v-else-if="saving">Saving…</span>
        <span v-else>Save Changes</span>
      </button>
    </div>

    <div v-if="error" class="px-4 py-3 bg-red-500/20 border border-red-500/40 rounded-lg text-red-400 text-sm">{{ error }}</div>

    <!-- Live Stream Preview -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-700 flex items-center justify-between">
        <span class="text-white font-medium text-sm">Live Preview</span>
        <span class="text-xs text-gray-500 font-mono truncate max-w-xs">{{ streamUrl }}</span>
      </div>
      <div class="relative bg-black" style="aspect-ratio:16/9;" ref="previewContainer">
        <video ref="videoEl" class="w-full h-full object-fill" muted autoplay playsinline></video>

        <!-- Logo overlay preview -->
        <div v-if="f.enable_overlay_logo && (logoPreview || f.logo_url)"
          class="absolute pointer-events-none"
          :style="logoOverlayStyle">
          <img :src="logoPreview || f.logo_url"
            @load="onLogoLoad"
            :style="{ opacity: f.overlay_logo_opacity, width: logoSizePx + 'px' }"
            class="object-contain block" />
        </div>

        <!-- Ticker overlay preview -->
        <div v-if="f.enable_ticker && f.ticker_text"
          class="absolute bottom-0 left-0 right-0 h-8 overflow-hidden flex items-center"
          :style="{ background: f.ticker_background || '#000000cc' }">
          <span class="ticker-preview whitespace-nowrap text-sm font-medium px-2"
            :style="{ color: f.ticker_color || '#ffffff', animationDuration: tickerDuration }">
            {{ f.ticker_text }}
          </span>
        </div>

        <!-- Clock overlay preview -->
        <div v-if="f.enable_overlay_clock"
          ref="clockEl"
          class="absolute text-white font-mono bg-black/50 px-2 py-1 rounded"
          :style="clockPositionStyle">
          {{ currentTime }}
        </div>

        <!-- Drag hint -->
        <div v-if="f.enable_overlay_logo && (logoPreview || f.logo_url)"
          class="absolute bottom-2 right-2 text-xs text-gray-400 bg-black/60 px-2 py-1 rounded">
          Use X/Y sliders to position
        </div>
      </div>
    </div>

    <!-- Ticker -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
      <div class="flex items-center justify-between px-5 py-4 border-b border-gray-700">
        <span class="text-white font-medium">Ticker Overlay</span>
        <label class="relative inline-flex items-center cursor-pointer">
          <input type="checkbox" v-model="f.enable_ticker" class="sr-only peer" />
          <div class="w-10 h-5 bg-gray-600 peer-checked:bg-indigo-600 rounded-full transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition peer-checked:after:translate-x-5"></div>
        </label>
      </div>
      <div v-if="f.enable_ticker" class="p-5 space-y-4">
        <div>
          <label class="block text-xs text-gray-400 mb-1">Ticker Text</label>
          <textarea v-model="f.ticker_text" rows="2"
            class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm"
            placeholder="Breaking News • Now showing: {item}"></textarea>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs text-gray-400 mb-1">Speed ({{ f.ticker_speed }})</label>
            <input v-model.number="f.ticker_speed" type="range" min="10" max="100" class="w-full accent-indigo-500" />
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">Direction</label>
            <select v-model="f.ticker_direction" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm">
              <option value="left">Left</option>
              <option value="right">Right</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">Text Color</label>
            <div class="flex items-center gap-2">
              <input type="color" v-model="f.ticker_color" class="w-10 h-9 rounded cursor-pointer bg-gray-700 border border-gray-600 p-0.5" />
              <input v-model="f.ticker_color" type="text" maxlength="7"
                class="flex-1 px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm font-mono" />
            </div>
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">Background Color</label>
            <div class="flex items-center gap-2">
              <input type="color" :value="f.ticker_background?.slice(0,7)" @input="e => f.ticker_background = e.target.value + (f.ticker_background?.slice(7) || '')"
                class="w-10 h-9 rounded cursor-pointer bg-gray-700 border border-gray-600 p-0.5" />
              <input v-model="f.ticker_background" type="text" maxlength="9"
                class="flex-1 px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm font-mono"
                placeholder="#000000" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Logo Overlay -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
      <div class="flex items-center justify-between px-5 py-4 border-b border-gray-700">
        <span class="text-white font-medium">Logo Overlay</span>
        <label class="relative inline-flex items-center cursor-pointer">
          <input type="checkbox" v-model="f.enable_overlay_logo" class="sr-only peer" />
          <div class="w-10 h-5 bg-gray-600 peer-checked:bg-indigo-600 rounded-full transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition peer-checked:after:translate-x-5"></div>
        </label>
      </div>
      <div v-if="f.enable_overlay_logo" class="p-5 space-y-4">
        <!-- Logo upload -->
        <div>
          <label class="block text-xs text-gray-400 mb-2">Logo Image</label>
          <div class="flex items-center gap-4">
            <div class="w-20 h-20 bg-gray-700 rounded-lg border border-gray-600 flex items-center justify-center overflow-hidden shrink-0">
              <img v-if="logoPreview || f.logo_url" :src="logoPreview || f.logo_url" class="w-full h-full object-contain p-1" />
              <ImageIcon v-else class="w-8 h-8 text-gray-500" />
            </div>
            <div class="flex-1 space-y-2">
              <label class="block w-full px-4 py-2 bg-gray-700 hover:bg-gray-600 border border-gray-600 border-dashed rounded-lg text-sm text-gray-300 text-center cursor-pointer transition">
                <input type="file" accept="image/*" class="hidden" @change="onLogoFileChange" />
                {{ uploadingLogo ? 'Uploading…' : 'Choose image or drag & drop' }}
              </label>
              <p v-if="f.logo_url && !logoPreview" class="text-xs text-gray-500 truncate">{{ f.logo_url }}</p>
              <button v-if="f.logo_url" @click="f.logo_url = ''; logoPreview = null"
                class="text-xs text-red-400 hover:text-red-300">Remove logo</button>
            </div>
          </div>
        </div>

        <!-- X/Y Position -->
        <div class="space-y-3">
          <p class="text-xs text-gray-400">Position</p>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs text-gray-400 mb-1">Quick Position</label>
              <select v-model="f.overlay_logo_position" @change="onLogoPositionChange"
                class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm">
                <option value="top-left">Top Left</option>
                <option value="top-right">Top Right</option>
                <option value="bottom-left">Bottom Left</option>
                <option value="bottom-right">Bottom Right</option>
                <option value="center">Center</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">Custom X/Y (% from top-left corner)</label>
              <p class="text-xs text-gray-500">Snap to a corner, then fine-tune below.</p>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs text-gray-400 mb-1">X Position ({{ f.overlay_logo_x }}%)</label>
              <input v-model.number="f.overlay_logo_x" type="range" min="0" max="95" step="0.5" class="w-full accent-indigo-500" />
              <input v-model.number="f.overlay_logo_x" type="number" min="0" max="95" step="0.5"
                class="mt-1 w-full px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm" />
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">Y Position ({{ f.overlay_logo_y }}%)</label>
              <input v-model.number="f.overlay_logo_y" type="range" min="0" max="95" step="0.5" class="w-full accent-indigo-500" />
              <input v-model.number="f.overlay_logo_y" type="number" min="0" max="95" step="0.5"
                class="mt-1 w-full px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm" />
            </div>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs text-gray-400 mb-1">Size ({{ f.overlay_logo_size }}%)</label>
            <input v-model.number="f.overlay_logo_size" type="range" min="10" max="200" class="w-full accent-indigo-500" />
          </div>
          <div class="col-span-1">
            <label class="block text-xs text-gray-400 mb-1">Opacity ({{ Math.round(f.overlay_logo_opacity * 100) }}%)</label>
            <input v-model.number="f.overlay_logo_opacity" type="range" min="0" max="1" step="0.01" class="w-full accent-indigo-500" />
          </div>
        </div>
      </div>
    </div>

    <!-- Watermark -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
      <div class="flex items-center justify-between px-5 py-4 border-b border-gray-700">
        <span class="text-white font-medium">Watermark</span>
        <label class="relative inline-flex items-center cursor-pointer">
          <input type="checkbox" v-model="f.enable_watermark" class="sr-only peer" />
          <div class="w-10 h-5 bg-gray-600 peer-checked:bg-indigo-600 rounded-full transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition peer-checked:after:translate-x-5"></div>
        </label>
      </div>
      <div v-if="f.enable_watermark" class="p-5 space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs text-gray-400 mb-1">Position</label>
            <select v-model="f.watermark_position" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm">
              <option value="top-left">Top Left</option>
              <option value="top-right">Top Right</option>
              <option value="bottom-left">Bottom Left</option>
              <option value="bottom-right">Bottom Right</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">Opacity ({{ Math.round(f.watermark_opacity * 100) }}%)</label>
            <input v-model.number="f.watermark_opacity" type="range" min="0" max="1" step="0.01" class="w-full accent-indigo-500" />
          </div>
        </div>
      </div>
    </div>

    <!-- Clock -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
      <div class="flex items-center justify-between px-5 py-4 border-b border-gray-700">
        <span class="text-white font-medium">Clock Overlay</span>
        <label class="relative inline-flex items-center cursor-pointer">
          <input type="checkbox" v-model="f.enable_overlay_clock" class="sr-only peer" />
          <div class="w-10 h-5 bg-gray-600 peer-checked:bg-indigo-600 rounded-full transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition peer-checked:after:translate-x-5"></div>
        </label>
      </div>
      <div v-if="f.enable_overlay_clock" class="p-5 space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs text-gray-400 mb-1">Quick Position</label>
            <select v-model="f.overlay_clock_position" @change="onClockPositionChange"
              class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm">
              <option value="top-left">Top Left</option>
              <option value="top-right">Top Right</option>
              <option value="bottom-left">Bottom Left</option>
              <option value="bottom-right">Bottom Right</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">Format</label>
            <select v-model="f.overlay_clock_format" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm">
              <option value="HH:MM:SS">HH:MM:SS</option>
              <option value="HH:MM">HH:MM</option>
              <option value="MM/DD/YYYY">MM/DD/YYYY</option>
              <option value="YYYY-MM-DD">YYYY-MM-DD</option>
            </select>
          </div>
        </div>
        <div>
          <p class="text-xs text-gray-400 mb-1">Custom X/Y (% from top-left corner)</p>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs text-gray-400 mb-1">X Position ({{ f.overlay_clock_x }}%)</label>
              <input v-model.number="f.overlay_clock_x" type="range" min="0" max="95" step="0.5" class="w-full accent-indigo-500" />
              <input v-model.number="f.overlay_clock_x" type="number" min="0" max="95" step="0.5"
                class="mt-1 w-full px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm" />
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">Y Position ({{ f.overlay_clock_y }}%)</label>
              <input v-model.number="f.overlay_clock_y" type="range" min="0" max="95" step="0.5" class="w-full accent-indigo-500" />
              <input v-model.number="f.overlay_clock_y" type="number" min="0" max="95" step="0.5"
                class="mt-1 w-full px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Image as ImageIcon } from 'lucide-vue-next'
import { route } from '@/Composables/useRoute'
import { useApiFetch } from '@/Composables/useApiFetch'
import { useOverlayPosition } from '@/Composables/useOverlayPosition'

const props = defineProps({
  channel: { type: Object, required: true },
})

const { apiFetch } = useApiFetch()
const { snapLogoPosition, snapClockPosition } = useOverlayPosition()

const f = ref({
  enable_ticker:          props.channel?.enable_ticker          ?? false,
  ticker_text:            props.channel?.ticker_text            ?? '',
  ticker_speed:           props.channel?.ticker_speed           ?? 30,
  ticker_direction:       props.channel?.ticker_direction       ?? 'left',
  ticker_color:           props.channel?.ticker_color           ?? '#ffffff',
  ticker_background:      props.channel?.ticker_background      ?? '#000000',
  enable_overlay_logo:    props.channel?.enable_overlay_logo    ?? false,
  logo_url:               props.channel?.logo_url               ?? '',
  overlay_logo_position:  props.channel?.overlay_logo_position  ?? 'top-left',
  overlay_logo_x:         props.channel?.overlay_logo_x         ?? 2,
  overlay_logo_y:         props.channel?.overlay_logo_y         ?? 2,
  overlay_logo_size:      props.channel?.overlay_logo_size      ?? 100,
  overlay_logo_opacity:   props.channel?.overlay_logo_opacity   ?? 1,
  enable_watermark:       props.channel?.enable_watermark       ?? false,
  watermark_position:     props.channel?.watermark_position     ?? 'bottom-right',
  watermark_opacity:      props.channel?.watermark_opacity      ?? 0.5,
  enable_overlay_clock:   props.channel?.enable_overlay_clock   ?? false,
  overlay_clock_position: props.channel?.overlay_clock_position ?? 'top-right',
  overlay_clock_x:        props.channel?.overlay_clock_x        ?? 2,
  overlay_clock_y:        props.channel?.overlay_clock_y        ?? 2,
  overlay_clock_format:   props.channel?.overlay_clock_format   ?? 'HH:MM:SS',
})

const saving = ref(false)
const saved = ref(false)
const error = ref(null)
const logoPreview = ref(null)
const uploadingLogo = ref(false)
const videoEl = ref(null)
const previewContainer = ref(null)
const clockEl = ref(null)
const currentTime = ref('')
const previewSize = ref({ width: 800, height: 450 })
const logoNaturalAspect = ref(1)
let clockInterval = null
let hlsInstance = null
let resizeObserver = null

// Stream URL for this channel
const streamUrl = computed(() => {
  const slug = props.channel?.channel_slug
  return `http://localhost:25460/hls/admin-channel-${slug}/index.m3u8`
})

// Logo overlay CSS position based on X/Y percentages (matches FFmpeg top-left anchor)
const logoOverlayStyle = computed(() => ({
  left: (f.value.overlay_logo_x ?? 2) + '%',
  top: (f.value.overlay_logo_y ?? 2) + '%',
  transform: 'none',
}))

// Mirrors FFmpeg: width * (overlay_logo_size / 100) * 0.15
const logoSizePx = computed(() => {
  const w = previewSize.value.width || 800
  return Math.max(1, Math.round(w * ((f.value.overlay_logo_size || 100) / 100) * 0.15))
})

// Clock position uses the same X/Y % semantics as FFmpeg drawtext (top-left anchor)
const clockPositionStyle = computed(() => ({
  left: (f.value.overlay_clock_x ?? 2) + '%',
  top: (f.value.overlay_clock_y ?? 2) + '%',
  fontSize: Math.max(14, Math.round((previewSize.value.height || 450) * 0.03)) + 'px',
}))

const onLogoLoad = (e) => {
  const nw = e.target?.naturalWidth
  const nh = e.target?.naturalHeight
  if (nw && nh) logoNaturalAspect.value = nw / nh
}

const onLogoPositionChange = (e) => snapLogoPosition(f.value, e.target.value, logoNaturalAspect.value)

const onClockPositionChange = (e) => {
  const metrics = clockMetrics()
  snapClockPosition(f.value, e.target.value, metrics)
}

const clockMetrics = () => {
  const el = clockEl.value
  const w = previewSize.value.width || 800
  const h = previewSize.value.height || 450
  return {
    width: w,
    height: h,
    textW: el?.offsetWidth || 70,
    textH: el?.offsetHeight || 24,
  }
}

// Speed 10=slow(40s) → 100=fast(5s)
const tickerDuration = computed(() => {
  const s = Math.round(40 - ((f.value.ticker_speed - 10) / 90) * 35)
  return `${s}s`
})

const updateClock = () => {
  const now = new Date()
  const fmt = f.value.overlay_clock_format
  if (fmt === 'HH:MM:SS') {
    currentTime.value = now.toTimeString().slice(0, 8)
  } else if (fmt === 'HH:MM') {
    currentTime.value = now.toTimeString().slice(0, 5)
  } else if (fmt === 'MM/DD/YYYY') {
    currentTime.value = `${String(now.getMonth()+1).padStart(2,'0')}/${String(now.getDate()).padStart(2,'0')}/${now.getFullYear()}`
  } else {
    currentTime.value = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`
  }
}

const initHls = async () => {
  if (!videoEl.value) return
  const url = streamUrl.value

  // Try native HLS first (Safari)
  if (videoEl.value.canPlayType('application/vnd.apple.mpegurl')) {
    videoEl.value.src = url
    videoEl.value.play().catch(() => {})
    return
  }

  // Use hls.js if available
  if (window.Hls && window.Hls.isSupported()) {
    hlsInstance = new window.Hls({ enableWorker: false })
    hlsInstance.loadSource(url)
    hlsInstance.attachMedia(videoEl.value)
    hlsInstance.on(window.Hls.Events.MANIFEST_PARSED, () => {
      videoEl.value.play().catch(() => {})
    })
    return
  }

  // Dynamically load hls.js
  try {
    await new Promise((resolve, reject) => {
      const s = document.createElement('script')
      s.src = 'https://cdn.jsdelivr.net/npm/hls.js@latest/dist/hls.min.js'
      s.onload = resolve
      s.onerror = reject
      document.head.appendChild(s)
    })
    if (window.Hls?.isSupported()) {
      hlsInstance = new window.Hls({ enableWorker: false })
      hlsInstance.loadSource(url)
      hlsInstance.attachMedia(videoEl.value)
      hlsInstance.on(window.Hls.Events.MANIFEST_PARSED, () => {
        videoEl.value.play().catch(() => {})
      })
    }
  } catch (e) {
    // hls.js failed to load — preview will be blank but controls still work
  }
}

onMounted(() => {
  initHls()
  updateClock()
  clockInterval = setInterval(updateClock, 1000)

  const container = previewContainer.value
  if (container) {
    const measure = () => {
      previewSize.value = { width: container.clientWidth || 800, height: container.clientHeight || 450 }
    }
    measure()
    resizeObserver = new ResizeObserver(measure)
    resizeObserver.observe(container)
  }
})

onUnmounted(() => {
  clearInterval(clockInterval)
  resizeObserver?.disconnect()
  resizeObserver = null
  if (hlsInstance) {
    hlsInstance.destroy()
    hlsInstance = null
  }
})

const onLogoFileChange = async (e) => {
  const file = e.target.files?.[0]
  if (!file) return
  logoPreview.value = URL.createObjectURL(file)
  uploadingLogo.value = true
  try {
    const form = new FormData()
    form.append('image', file)
    form.append('field', 'logo_url')
    const res = await apiFetch(
      route('admin.channels.my-channel.upload-image'),
      { method: 'POST', body: form }
    )
    const json = await res.json()
    if (json.url) {
      f.value.logo_url = json.url
      logoPreview.value = null
    } else {
      error.value = json.message || 'Upload failed'
      logoPreview.value = null
    }
  } catch (err) {
    error.value = err.message
    logoPreview.value = null
  } finally {
    uploadingLogo.value = false
  }
}

const saveOverlays = async () => {
  saving.value = true
  saved.value = false
  error.value = null
  try {
    const res = await apiFetch(
      route('admin.channels.my-channel.overlays-settings.update', props.channel.channel_slug),
      { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(f.value) }
    )
    if (res.ok) {
      const json = await res.json()
      Object.assign(props.channel, json.channel)
      saved.value = true
      setTimeout(() => { saved.value = false }, 2000)
    } else {
      const json = await res.json()
      error.value = json?.message || 'Save failed'
    }
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
@keyframes ticker-left {
  0%   { transform: translateX(100vw); }
  100% { transform: translateX(-100%); }
}
@keyframes ticker-right {
  0%   { transform: translateX(-100%); }
  100% { transform: translateX(100vw); }
}
.ticker-preview {
  animation: ticker-left v-bind(tickerDuration) linear infinite;
}
</style>
