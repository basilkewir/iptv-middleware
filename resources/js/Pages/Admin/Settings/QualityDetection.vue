<template>
  <AdminLayout>
    <div class="p-6 max-w-5xl mx-auto space-y-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">Quality Detection Settings</h1>
        <p class="text-gray-400 mt-1">Configure automatic quality detection for channels and VOD content</p>
      </div>

      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        <div class="grid grid-cols-5 gap-4 text-center">
          <div>
            <p class="text-2xl font-bold text-white">{{ stats.total_channels }}</p>
            <p class="text-gray-400 text-sm">Total Channels</p>
          </div>
          <div>
            <p class="text-2xl font-bold text-indigo-400">{{ stats.channels_with_quality }}</p>
            <p class="text-gray-400 text-sm">Channels with Quality</p>
          </div>
          <div>
            <p class="text-2xl font-bold text-white">{{ stats.total_vod }}</p>
            <p class="text-gray-400 text-sm">Total VOD</p>
          </div>
          <div>
            <p class="text-2xl font-bold text-indigo-400">{{ stats.vod_with_quality }}</p>
            <p class="text-gray-400 text-sm">VOD with Quality</p>
          </div>
          <div>
            <p class="text-2xl font-bold text-yellow-400">{{ stats.scan_pending }}</p>
            <p class="text-gray-400 text-sm">Pending Scans</p>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-1 bg-gray-800 rounded-xl p-1 border border-gray-700 w-fit">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          type="button"
          @click="activeTab = tab.id"
          :class="activeTab === tab.id ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white'"
          class="px-4 py-2 rounded-lg text-sm font-medium transition"
        >
          {{ tab.label }}
        </button>
      </div>

      <form @submit.prevent="form.put(route('admin.settings.quality-detection.update'))" class="space-y-6">
        <template v-if="activeTab === 'channels'">
          <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
            <h3 class="text-lg font-semibold text-white mb-4">Detection Method</h3>
            <div class="space-y-3">
              <label v-for="method in detectionMethods" :key="method.value" class="flex items-center gap-3 cursor-pointer">
                <input v-model="form.detection_method" type="radio" :value="method.value" class="w-4 h-4 bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                <div>
                  <span class="text-gray-300">{{ method.label }}</span>
                  <span class="text-gray-500 text-sm ml-2">{{ method.description }}</span>
                </div>
              </label>
            </div>
          </div>

          <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
            <h3 class="text-lg font-semibold text-white mb-4">Resolution Thresholds</h3>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">4K Minimum (px)</label>
                <input v-model="form.resolution_4k_min" type="number" min="0" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">FHD Minimum (px)</label>
                <input v-model="form.resolution_fhd_min" type="number" min="0" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">HD Minimum (px)</label>
                <input v-model="form.resolution_hd_min" type="number" min="0" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">SD Minimum (px)</label>
                <input v-model="form.resolution_sd_min" type="number" min="0" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
          </div>

          <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
            <h3 class="text-lg font-semibold text-white mb-4">Bitrate Thresholds</h3>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">4K Minimum (Kbps)</label>
                <input v-model="form.bitrate_4k_min" type="number" min="0" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">FHD Minimum (Kbps)</label>
                <input v-model="form.bitrate_fhd_min" type="number" min="0" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">HD Minimum (Kbps)</label>
                <input v-model="form.bitrate_hd_min" type="number" min="0" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">SD Minimum (Kbps)</label>
                <input v-model="form.bitrate_sd_min" type="number" min="0" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
          </div>

          <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
            <h3 class="text-lg font-semibold text-white mb-4">Badge Display</h3>
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-white font-medium">Show Badge in Channel List</p>
                  <p class="text-gray-400 text-sm">Display quality badge in channel listings</p>
                </div>
                <button type="button" @click="form.show_badge_channels = !form.show_badge_channels" :class="form.show_badge_channels ? 'bg-indigo-600' : 'bg-gray-600'" class="relative inline-flex h-6 w-11 items-center rounded-full transition">
                  <span :class="form.show_badge_channels ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition" />
                </button>
              </div>
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-white font-medium">Show Badge in EPG</p>
                  <p class="text-gray-400 text-sm">Display quality badge in EPG programs</p>
                </div>
                <button type="button" @click="form.show_badge_epg = !form.show_badge_epg" :class="form.show_badge_epg ? 'bg-indigo-600' : 'bg-gray-600'" class="relative inline-flex h-6 w-11 items-center rounded-full transition">
                  <span :class="form.show_badge_epg ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition" />
                </button>
              </div>
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-white font-medium">Show Badge in Player</p>
                  <p class="text-gray-400 text-sm">Display quality badge in video player</p>
                </div>
                <button type="button" @click="form.show_badge_player = !form.show_badge_player" :class="form.show_badge_player ? 'bg-indigo-600' : 'bg-gray-600'" class="relative inline-flex h-6 w-11 items-center rounded-full transition">
                  <span :class="form.show_badge_player ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition" />
                </button>
              </div>
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-white font-medium">Show Badge in Channel List</p>
                  <p class="text-gray-400 text-sm">Display quality badge in channel sidebar</p>
                </div>
                <button type="button" @click="form.show_badge_channel_list = !form.show_badge_channel_list" :class="form.show_badge_channel_list ? 'bg-indigo-600' : 'bg-gray-600'" class="relative inline-flex h-6 w-11 items-center rounded-full transition">
                  <span :class="form.show_badge_channel_list ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition" />
                </button>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Badge Style</label>
                <div class="flex items-center gap-4">
                  <label v-for="style in badgeStyles" :key="style.value" class="flex items-center gap-2 text-gray-300 cursor-pointer">
                    <input v-model="form.badge_style" type="radio" :value="style.value" class="w-4 h-4 bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                    {{ style.label }}
                  </label>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
            <h3 class="text-lg font-semibold text-white mb-4">Auto-Update</h3>
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-white font-medium">Auto-Update New Channels</p>
                  <p class="text-gray-400 text-sm">Automatically detect quality for newly added channels</p>
                </div>
                <button type="button" @click="form.auto_update_new = !form.auto_update_new" :class="form.auto_update_new ? 'bg-indigo-600' : 'bg-gray-600'" class="relative inline-flex h-6 w-11 items-center rounded-full transition">
                  <span :class="form.auto_update_new ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition" />
                </button>
              </div>
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-white font-medium">Auto-Update Existing Channels</p>
                  <p class="text-gray-400 text-sm">Periodically re-scan existing channels for quality changes</p>
                </div>
                <button type="button" @click="form.auto_update_existing = !form.auto_update_existing" :class="form.auto_update_existing ? 'bg-indigo-600' : 'bg-gray-600'" class="relative inline-flex h-6 w-11 items-center rounded-full transition">
                  <span :class="form.auto_update_existing ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition" />
                </button>
              </div>
              <div class="grid grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Update Interval</label>
                  <select v-model="form.update_interval" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                    <option value="hourly">Hourly</option>
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Max Concurrent Scans</label>
                  <input v-model="form.max_concurrent_scans" type="number" min="1" max="50" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Scan Timeout (seconds)</label>
                  <input v-model="form.scan_timeout" type="number" min="5" max="300" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
                </div>
              </div>
            </div>
          </div>
        </template>

        <template v-if="activeTab === 'vod'">
          <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
            <h3 class="text-lg font-semibold text-white mb-4">VOD Quality Detection</h3>
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-white font-medium">Enable VOD Quality Detection</p>
                  <p class="text-gray-400 text-sm">Automatically detect quality for VOD content</p>
                </div>
                <button type="button" @click="form.vod_detection_enabled = !form.vod_detection_enabled" :class="form.vod_detection_enabled ? 'bg-indigo-600' : 'bg-gray-600'" class="relative inline-flex h-6 w-11 items-center rounded-full transition">
                  <span :class="form.vod_detection_enabled ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition" />
                </button>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-300 mb-3">Detection Sources</label>
                <div class="space-y-2">
                  <label v-for="source in detectionSources" :key="source.value" class="flex items-center gap-3 cursor-pointer">
                    <input v-model="form.vod_detection_sources" type="checkbox" :value="source.value" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                    <div>
                      <span class="text-gray-300">{{ source.label }}</span>
                      <span class="text-gray-500 text-sm ml-2">{{ source.description }}</span>
                    </div>
                  </label>
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-300 mb-3">Detect For</label>
                <div class="space-y-2">
                  <label v-for="detect in detectForOptions" :key="detect.value" class="flex items-center gap-3 cursor-pointer">
                    <input v-model="form.vod_detect_for" type="checkbox" :value="detect.value" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                    <span class="text-gray-300">{{ detect.label }}</span>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
            <h3 class="text-lg font-semibold text-white mb-4">VOD Multi-Quality</h3>
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-white font-medium">Detect Multiple Qualities</p>
                  <p class="text-gray-400 text-sm">Detect and store multiple quality versions of the same content</p>
                </div>
                <button type="button" @click="form.detect_multi_quality = !form.detect_multi_quality" :class="form.detect_multi_quality ? 'bg-indigo-600' : 'bg-gray-600'" class="relative inline-flex h-6 w-11 items-center rounded-full transition">
                  <span :class="form.detect_multi_quality ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition" />
                </button>
              </div>
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-white font-medium">Auto-Select Best Quality</p>
                  <p class="text-gray-400 text-sm">Automatically select the highest quality version</p>
                </div>
                <button type="button" @click="form.auto_select_best = !form.auto_select_best" :class="form.auto_select_best ? 'bg-indigo-600' : 'bg-gray-600'" class="relative inline-flex h-6 w-11 items-center rounded-full transition">
                  <span :class="form.auto_select_best ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition" />
                </button>
              </div>
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-white font-medium">Allow Manual Override</p>
                  <p class="text-gray-400 text-sm">Let users manually select quality version</p>
                </div>
                <button type="button" @click="form.allow_manual_override = !form.allow_manual_override" :class="form.allow_manual_override ? 'bg-indigo-600' : 'bg-gray-600'" class="relative inline-flex h-6 w-11 items-center rounded-full transition">
                  <span :class="form.allow_manual_override ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition" />
                </button>
              </div>
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-white font-medium">Transcode Lower Qualities</p>
                  <p class="text-gray-400 text-sm">Auto-generate lower quality versions from source</p>
                </div>
                <button type="button" @click="form.transcode_lower_qualities = !form.transcode_lower_qualities" :class="form.transcode_lower_qualities ? 'bg-indigo-600' : 'bg-gray-600'" class="relative inline-flex h-6 w-11 items-center rounded-full transition">
                  <span :class="form.transcode_lower_qualities ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition" />
                </button>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-3">Quality Versions</label>
                <div class="flex flex-wrap gap-3">
                  <label v-for="qv in qualityVersions" :key="qv.value" class="flex items-center gap-2 text-gray-300 bg-gray-700/50 px-3 py-2 rounded-lg cursor-pointer hover:bg-gray-700 transition">
                    <input v-model="form.quality_versions" type="checkbox" :value="qv.value" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                    {{ qv.label }}
                  </label>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
            <h3 class="text-lg font-semibold text-white mb-4">VOD Badge Display</h3>
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-white font-medium">Show Badge on Thumbnail</p>
                  <p class="text-gray-400 text-sm">Display quality badge on VOD thumbnail images</p>
                </div>
                <button type="button" @click="form.show_vod_badge_thumbnail = !form.show_vod_badge_thumbnail" :class="form.show_vod_badge_thumbnail ? 'bg-indigo-600' : 'bg-gray-600'" class="relative inline-flex h-6 w-11 items-center rounded-full transition">
                  <span :class="form.show_vod_badge_thumbnail ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition" />
                </button>
              </div>
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-white font-medium">Show Badge in Details</p>
                  <p class="text-gray-400 text-sm">Display quality badge in VOD details page</p>
                </div>
                <button type="button" @click="form.show_vod_badge_details = !form.show_vod_badge_details" :class="form.show_vod_badge_details ? 'bg-indigo-600' : 'bg-gray-600'" class="relative inline-flex h-6 w-11 items-center rounded-full transition">
                  <span :class="form.show_vod_badge_details ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition" />
                </button>
              </div>
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-white font-medium">Show Badge in Player</p>
                  <p class="text-gray-400 text-sm">Display quality badge in VOD player</p>
                </div>
                <button type="button" @click="form.show_vod_badge_player = !form.show_vod_badge_player" :class="form.show_vod_badge_player ? 'bg-indigo-600' : 'bg-gray-600'" class="relative inline-flex h-6 w-11 items-center rounded-full transition">
                  <span :class="form.show_vod_badge_player ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition" />
                </button>
              </div>
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-white font-medium">Show Quality Options</p>
                  <p class="text-gray-400 text-sm">Show quality selector in player controls</p>
                </div>
                <button type="button" @click="form.show_vod_quality_options = !form.show_vod_quality_options" :class="form.show_vod_quality_options ? 'bg-indigo-600' : 'bg-gray-600'" class="relative inline-flex h-6 w-11 items-center rounded-full transition">
                  <span :class="form.show_vod_quality_options ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition" />
                </button>
              </div>
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-white font-medium">Auto-Select Best for Device</p>
                  <p class="text-gray-400 text-sm">Automatically select best quality based on device capabilities</p>
                </div>
                <button type="button" @click="form.auto_select_best_device = !form.auto_select_best_device" :class="form.auto_select_best_device ? 'bg-indigo-600' : 'bg-gray-600'" class="relative inline-flex h-6 w-11 items-center rounded-full transition">
                  <span :class="form.auto_select_best_device ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition" />
                </button>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Badge Position</label>
                <select v-model="form.vod_badge_position" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option value="top-left">Top Left</option>
                  <option value="top-right">Top Right</option>
                  <option value="bottom-left">Bottom Left</option>
                  <option value="bottom-right">Bottom Right</option>
                </select>
              </div>
            </div>
          </div>
        </template>

        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <button
              type="button"
              @click="scanAllChannels"
              class="px-4 py-2 bg-green-600 hover:bg-green-500 text-white rounded-lg transition flex items-center gap-2"
            >
              <RadarIcon class="w-4 h-4" />
              Scan All Channels
            </button>
            <button
              type="button"
              @click="scanAllVod"
              class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg transition flex items-center gap-2"
            >
              <FilmIcon class="w-4 h-4" />
              Scan All VOD
            </button>
          </div>
          <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50">
            {{ form.processing ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { RadarIcon, FilmIcon } from 'lucide-vue-next'

const props = defineProps({
  settings: { type: Object, required: true },
  stats: { type: Object, required: true },
})

const activeTab = ref('channels')

const tabs = [
  { id: 'channels', label: 'Channel Detection' },
  { id: 'vod', label: 'VOD Detection' },
]

const detectionMethods = [
  { value: 'resolution', label: 'Resolution Based', description: 'Detect quality based on video resolution' },
  { value: 'bitrate', label: 'Bitrate Based', description: 'Detect quality based on stream bitrate' },
  { value: 'combined', label: 'Combined', description: 'Use both resolution and bitrate for detection' },
  { value: 'ai', label: 'AI Based', description: 'Use machine learning for quality detection' },
]

const badgeStyles = [
  { value: 'classic', label: 'Classic' },
  { value: 'modern', label: 'Modern' },
  { value: 'minimal', label: 'Minimal' },
  { value: 'fluent', label: 'Fluent' },
]

const detectionSources = [
  { value: 'metadata', label: 'File Metadata', description: 'Read quality from file headers' },
  { value: 'stream', label: 'Stream Analysis', description: 'Analyze the live stream directly' },
  { value: 'ffprobe', label: 'FFProbe', description: 'Use FFProbe for detailed analysis' },
  { value: 'ai', label: 'AI-Based', description: 'Use ML models for quality detection' },
]

const detectForOptions = [
  { value: 'new_uploads', label: 'New Uploads' },
  { value: 'existing_files', label: 'Existing Files' },
  { value: 'series', label: 'Series' },
  { value: 'imported', label: 'Imported Content' },
]

const qualityVersions = [
  { value: '4k', label: '4K' },
  { value: 'fhd', label: 'FHD' },
  { value: 'hd', label: 'HD' },
  { value: 'sd', label: 'SD' },
  { value: 'mobile', label: 'Mobile' },
  { value: 'audio', label: 'Audio Only' },
]

const form = useForm({
  detection_method: props.settings.detection_method || 'resolution',
  resolution_4k_min: props.settings.resolution_4k_min ?? 3840,
  resolution_fhd_min: props.settings.resolution_fhd_min ?? 1920,
  resolution_hd_min: props.settings.resolution_hd_min ?? 1280,
  resolution_sd_min: props.settings.resolution_sd_min ?? 640,
  bitrate_4k_min: props.settings.bitrate_4k_min ?? 20000,
  bitrate_fhd_min: props.settings.bitrate_fhd_min ?? 8000,
  bitrate_hd_min: props.settings.bitrate_hd_min ?? 4500,
  bitrate_sd_min: props.settings.bitrate_sd_min ?? 1000,
  show_badge_channels: props.settings.show_badge_channels ?? true,
  show_badge_epg: props.settings.show_badge_epg ?? true,
  show_badge_player: props.settings.show_badge_player ?? true,
  show_badge_channel_list: props.settings.show_badge_channel_list ?? true,
  badge_style: props.settings.badge_style || 'classic',
  auto_update_new: props.settings.auto_update_new ?? true,
  auto_update_existing: props.settings.auto_update_existing ?? false,
  update_interval: props.settings.update_interval || 'daily',
  max_concurrent_scans: props.settings.max_concurrent_scans ?? 5,
  scan_timeout: props.settings.scan_timeout ?? 30,
  vod_detection_enabled: props.settings.vod_detection_enabled ?? true,
  vod_detection_sources: props.settings.vod_detection_sources || ['metadata', 'ffprobe'],
  vod_detect_for: props.settings.vod_detect_for || ['new_uploads', 'existing_files'],
  detect_multi_quality: props.settings.detect_multi_quality ?? true,
  auto_select_best: props.settings.auto_select_best ?? true,
  allow_manual_override: props.settings.allow_manual_override ?? true,
  transcode_lower_qualities: props.settings.transcode_lower_qualities ?? false,
  quality_versions: props.settings.quality_versions || ['4k', 'fhd', 'hd', 'sd'],
  show_vod_badge_thumbnail: props.settings.show_vod_badge_thumbnail ?? true,
  show_vod_badge_details: props.settings.show_vod_badge_details ?? true,
  show_vod_badge_player: props.settings.show_vod_badge_player ?? true,
  show_vod_quality_options: props.settings.show_vod_quality_options ?? true,
  auto_select_best_device: props.settings.auto_select_best_device ?? true,
  vod_badge_position: props.settings.vod_badge_position || 'top-left',
})

const scanAllChannels = () => {
  if (confirm('Start quality scan for all channels? This may take a while.')) {
    router.post(route('admin.quality.scan.all.channels'))
  }
}

const scanAllVod = () => {
  if (confirm('Start quality scan for all VOD content? This may take a while.')) {
    router.post(route('admin.quality.scan.all.vod'))
  }
}
</script>
