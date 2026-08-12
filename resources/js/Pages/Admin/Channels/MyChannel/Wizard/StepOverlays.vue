<template>
  <div class="space-y-6">
    <h3 class="text-lg font-semibold text-white mb-4">Overlays Configuration</h3>

    <div class="bg-gray-700/30 rounded-lg p-4 border border-gray-600">
      <div class="flex items-center justify-between mb-3">
        <h4 class="text-white font-medium">Ticker Overlay</h4>
        <label class="flex items-center gap-2 cursor-pointer text-gray-300">
          <input type="checkbox" v-model="form.enable_ticker"
            class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600" />
          <span>Enable</span>
        </label>
      </div>

      <div v-if="form.enable_ticker" class="space-y-3">
        <div>
          <label class="block text-xs text-gray-400 mb-1">Ticker Text (supports variables)</label>
          <p class="text-xs text-gray-500 mb-1">Variables: {channel_name}, {time}, {date}, {viewers}, {item}, {next}</p>
          <textarea v-model="form.ticker_text" rows="2"
            class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm"
            placeholder="Breaking News • Now showing: {item}"></textarea>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs text-gray-400 mb-1">Speed (1-100)</label>
            <input v-model.number="form.ticker_speed" type="range" min="1" max="100"
              class="w-full" />
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">Direction</label>
            <select v-model="form.ticker_direction"
              class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm">
              <option value="left">Left</option>
              <option value="right">Right</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">Text Color</label>
            <input v-model="form.ticker_color" type="color" class="w-full h-8 p-0" />
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">Background</label>
            <input v-model="form.ticker_background" type="color" class="w-full h-8 p-0" />
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="bg-gray-700/30 rounded-lg p-4 border border-gray-600">
        <div class="flex items-center justify-between mb-3">
          <h4 class="text-white font-medium">Logo Overlay</h4>
          <label class="flex items-center gap-2 cursor-pointer text-gray-300">
            <input type="checkbox" v-model="form.enable_overlay_logo"
              class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600" />
            <span>Enable</span>
          </label>
        </div>
        <div v-if="form.enable_overlay_logo" class="space-y-3">
          <p class="text-xs text-gray-400">Position</p>
          <div>
            <label class="block text-xs text-gray-400 mb-1">Quick Position</label>
            <select v-model="form.overlay_logo_position" @change="onLogoPositionChange"
              class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm">
              <option value="top-left">Top Left</option>
              <option value="top-right">Top Right</option>
              <option value="bottom-left">Bottom Left</option>
              <option value="bottom-right">Bottom Right</option>
              <option value="center">Center</option>
            </select>
          </div>
          <p class="text-xs text-gray-400">Custom X/Y (% from top-left corner)</p>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-gray-400 mb-1">X ({{ form.overlay_logo_x ?? 2 }}%)</label>
              <input v-model.number="form.overlay_logo_x" type="range" min="0" max="95" step="0.5" class="w-full" />
              <input v-model.number="form.overlay_logo_x" type="number" min="0" max="95" step="0.5"
                class="mt-1 w-full px-3 py-1 bg-gray-700 border border-gray-600 rounded text-white text-sm" />
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">Y ({{ form.overlay_logo_y ?? 2 }}%)</label>
              <input v-model.number="form.overlay_logo_y" type="range" min="0" max="95" step="0.5" class="w-full" />
              <input v-model.number="form.overlay_logo_y" type="number" min="0" max="95" step="0.5"
                class="mt-1 w-full px-3 py-1 bg-gray-700 border border-gray-600 rounded text-white text-sm" />
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">Size (%)</label>
              <input v-model.number="form.overlay_logo_size" type="number" min="10" max="200"
                class="w-full px-3 py-1 bg-gray-700 border border-gray-600 rounded text-white text-sm" />
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">Opacity (%)</label>
              <input v-model.number="logoOpacity" type="number" min="0" max="100"
                class="w-full px-3 py-1 bg-gray-700 border border-gray-600 rounded text-white text-sm" />
            </div>
          </div>
        </div>
      </div>

      <div class="bg-gray-700/30 rounded-lg p-4 border border-gray-600">
        <div class="flex items-center justify-between mb-3">
          <h4 class="text-white font-medium">Watermark</h4>
          <label class="flex items-center gap-2 cursor-pointer text-gray-300">
            <input type="checkbox" v-model="form.enable_watermark"
              class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600" />
            <span>Enable</span>
          </label>
        </div>
        <div v-if="form.enable_watermark" class="space-y-3">
          <label class="block text-xs text-gray-400 mb-1">Position</label>
          <select v-model="form.watermark_position"
            class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm">
            <option value="top-left">Top Left</option>
            <option value="top-right">Top Right</option>
            <option value="bottom-left">Bottom Left</option>
            <option value="bottom-right">Bottom Right</option>
          </select>
          <div>
            <label class="block text-xs text-gray-400 mb-1">Opacity (%)</label>
            <input v-model.number="watermarkOpacity" type="number" min="0" max="100"
              class="w-full px-3 py-1 bg-gray-700 border border-gray-600 rounded text-white text-sm" />
            <p class="text-xs text-gray-500 mt-1">PNG with transparency recommended</p>
            <input v-model="form.watermark_url" type="url"
              class="w-full mt-2 px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm"
              placeholder="https://example.com/watermark.png" />
          </div>
        </div>
      </div>
    </div>

    <div class="bg-gray-700/30 rounded-lg p-4 border border-gray-600">
      <div class="flex items-center justify-between mb-3">
        <h4 class="text-white font-medium">Clock Overlay</h4>
        <label class="flex items-center gap-2 cursor-pointer text-gray-300">
          <input type="checkbox" v-model="form.enable_overlay_clock"
            class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600" />
          <span>Enable</span>
        </label>
      </div>
      <div v-if="form.enable_overlay_clock" class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs text-gray-400 mb-1">Format</label>
          <select v-model="form.overlay_clock_format"
            class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm">
            <option value="HH:MM:SS">24hr (HH:MM:SS)</option>
            <option value="HH:MM">12hr (HH:MM)</option>
            <option value="MM/DD/YYYY">MM/DD/YYYY</option>
            <option value="YYYY-MM-DD">YYYY-MM-DD</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Quick Position</label>
          <select v-model="form.overlay_clock_position" @change="onClockPositionChange"
            class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm">
            <option value="top-left">Top Left</option>
            <option value="top-right">Top Right</option>
            <option value="bottom-left">Bottom Left</option>
            <option value="bottom-right">Bottom Right</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">X ({{ form.overlay_clock_x ?? 2 }}%)</label>
          <input v-model.number="form.overlay_clock_x" type="range" min="0" max="95" step="0.5" class="w-full" />
          <input v-model.number="form.overlay_clock_x" type="number" min="0" max="95" step="0.5"
            class="mt-1 w-full px-3 py-1 bg-gray-700 border border-gray-600 rounded text-white text-sm" />
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Y ({{ form.overlay_clock_y ?? 2 }}%)</label>
          <input v-model.number="form.overlay_clock_y" type="range" min="0" max="95" step="0.5" class="w-full" />
          <input v-model.number="form.overlay_clock_y" type="number" min="0" max="95" step="0.5"
            class="mt-1 w-full px-3 py-1 bg-gray-700 border border-gray-600 rounded text-white text-sm" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, watch } from 'vue'
import { useOverlayPosition } from '@/Composables/useOverlayPosition'

const props = defineProps({
  form: { type: Object, required: true },
})

const { snapLogoPosition, snapClockPosition } = useOverlayPosition()

const onLogoPositionChange = (e) => snapLogoPosition(props.form, e.target.value)

const onClockPositionChange = (e) => snapClockPosition(props.form, e.target.value)

const logoOpacity = computed({
  get: () => Math.round(props.form.overlay_logo_opacity * 100),
  set: (v) => { props.form.overlay_logo_opacity = v / 100 }
})

const watermarkOpacity = computed({
  get: () => Math.round(props.form.watermark_opacity * 100),
  set: (v) => { props.form.watermark_opacity = v / 100 }
})
</script>
