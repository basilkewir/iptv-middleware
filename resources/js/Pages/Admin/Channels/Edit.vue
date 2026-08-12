<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto">
      <div class="mb-6">
        <Link :href="route('admin.channels.index')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
          <ArrowLeft class="w-4 h-4" /> Back to Channels
        </Link>
        <h1 class="text-2xl font-bold text-white">Edit Channel: {{ channel?.name }}</h1>
      </div>

      <form @submit.prevent="form.put(route('admin.channels.update', { channel: props.channel.id }))" class="space-y-6">
        <!-- Basic Information -->
        <div class="card">
          <h2 class="text-lg font-semibold text-white mb-4">Basic Information</h2>
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Channel Name *</label>
                <input v-model="form.name" type="text" class="input-field" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Channel Number</label>
                <input v-model.number="form.channel_number" type="number" class="input-field" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
              <textarea v-model="form.description" rows="2" class="input-field" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Logo URL</label>
                <input v-model="form.logo_url" type="url" class="input-field" placeholder="https://example.com/logo.png (200x200 recommended)" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Genre</label>
                <select v-model="form.genre" class="input-field">
                  <option value="">Select Genre</option>
                  <option value="sports">Sports</option>
                  <option value="entertainment">Entertainment</option>
                  <option value="news">News</option>
                  <option value="movies">Movies</option>
                  <option value="kids">Kids</option>
                  <option value="music">Music</option>
                  <option value="documentary">Documentary</option>
                  <option value="lifestyle">Lifestyle</option>
                  <option value="religious">Religious</option>
                  <option value="international">International</option>
                </select>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Country</label>
                <select v-model="form.country" class="input-field">
                  <option value="">Select Country</option>
                  <option value="US">United States</option>
                  <option value="UK">United Kingdom</option>
                  <option value="CA">Canada</option>
                  <option value="AU">Australia</option>
                  <option value="DE">Germany</option>
                  <option value="FR">France</option>
                  <option value="IN">India</option>
                  <option value="BR">Brazil</option>
                  <option value="JP">Japan</option>
                  <option value="CN">China</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Language</label>
                <select v-model="form.language" class="input-field">
                  <option value="">Select Language</option>
                  <option value="en">English</option>
                  <option value="es">Spanish</option>
                  <option value="fr">French</option>
                  <option value="de">German</option>
                  <option value="pt">Portuguese</option>
                  <option value="ar">Arabic</option>
                  <option value="hi">Hindi</option>
                  <option value="ja">Japanese</option>
                  <option value="zh">Chinese</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Stream Configuration -->
        <div class="card">
          <h2 class="text-lg font-semibold text-white mb-4">Stream Configuration</h2>
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-300 mb-2">Stream URL *</label>
                <input v-model="form.stream_url" type="url" class="input-field" placeholder="http://..." />
                <p v-if="form.errors.stream_url" class="text-red-400 text-sm mt-1">{{ form.errors.stream_url }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Stream Type</label>
                <select v-model="form.stream_type" class="input-field">
                  <option value="hls">HLS</option>
                  <option value="rtmp">RTMP</option>
                  <option value="rtsp">RTSP</option>
                  <option value="udp">UDP</option>
                  <option value="dash">DASH</option>
                  <option value="m3u8">M3U8</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Quality</label>
                <select v-model="form.quality" class="input-field">
                  <option value="4k">4K</option>
                  <option value="1080p">FHD</option>
                  <option value="720p">HD</option>
                  <option value="480p">SD</option>
                </select>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Backup URL 1</label>
                <input v-model="form.backup_url_1" type="url" class="input-field" placeholder="http://..." />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Backup URL 2</label>
                <input v-model="form.backup_url_2" type="url" class="input-field" placeholder="http://..." />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Bitrate (kbps)</label>
              <input v-model.number="form.bitrate" type="number" class="input-field" placeholder="e.g., 5000" />
            </div>
          </div>
        </div>

        <!-- EPG Configuration -->
        <div class="card">
          <h2 class="text-lg font-semibold text-white mb-4">EPG Configuration</h2>
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">EPG Source</label>
                <select v-model="form.epg_source_id" class="input-field">
                  <option value="">Select EPG Source</option>
                  <option v-for="source in epgSources" :key="source.id" :value="source.id">{{ source.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">EPG ID</label>
                <input v-model="form.epg_id" type="text" class="input-field" placeholder="EPG Channel ID" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">EPG Language</label>
                <select v-model="form.epg_language" class="input-field">
                  <option value="en">English</option>
                  <option value="es">Spanish</option>
                  <option value="fr">French</option>
                  <option value="de">German</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Timezone Offset</label>
                <select v-model="form.timezone_offset" class="input-field">
                  <option value="UTC+0">UTC+0</option>
                  <option value="UTC-5">UTC-5 (EST)</option>
                  <option value="UTC-6">UTC-6 (CST)</option>
                  <option value="UTC-7">UTC-7 (MST)</option>
                  <option value="UTC-8">UTC-8 (PST)</option>
                  <option value="UTC+1">UTC+1 (CET)</option>
                  <option value="UTC+5:30">UTC+5:30 (IST)</option>
                  <option value="UTC+9">UTC+9 (JST)</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Categorization -->
        <div class="card">
          <h2 class="text-lg font-semibold text-white mb-4">Categorization</h2>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Categories *</label>
              <div class="space-y-2 max-h-40 overflow-y-auto bg-gray-700 rounded-lg p-3">
                <label v-for="cat in categories" :key="cat.id" class="flex items-center gap-2 text-gray-300 text-sm">
                  <input type="checkbox" :value="cat.id" v-model="form.category_ids" class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-purple-600 focus:ring-purple-500" />
                  {{ cat.name }}
                </label>
              </div>
              <p v-if="form.errors.category_ids" class="text-red-400 text-sm mt-1">{{ form.errors.category_ids }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Bouquets</label>
              <div class="space-y-2 max-h-40 overflow-y-auto bg-gray-700 rounded-lg p-3">
                <label v-for="b in bouquets" :key="b.id" class="flex items-center gap-2 text-gray-300 text-sm">
                  <input type="checkbox" :value="b.id" v-model="form.bouquet_ids" class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-purple-600 focus:ring-purple-500" />
                  {{ b.name }}
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- Transcoding Settings -->
        <div class="card">
          <h2 class="text-lg font-semibold text-white mb-4">Transcoding Settings</h2>
          <div class="space-y-4">
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="form.transcoding_enabled" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-purple-600 focus:ring-purple-500" />
              <span class="text-gray-300">Enable Transcoding</span>
            </label>
            <div v-if="form.transcoding_enabled" class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Profile</label>
                <select v-model="form.transcoding_profile" class="input-field">
                  <option value="auto">Auto</option>
                  <option value="low">Low</option>
                  <option value="medium">Medium</option>
                  <option value="high">High</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Resolution</label>
                <select v-model="form.transcoding_resolution" class="input-field">
                  <option value="1080p">1080p</option>
                  <option value="720p">720p</option>
                  <option value="480p">480p</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Video Codec</label>
                <select v-model="form.transcoding_video_codec" class="input-field">
                  <option value="h264">H264</option>
                  <option value="h265">H265</option>
                  <option value="vp9">VP9</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Audio Codec</label>
                <select v-model="form.transcoding_audio_codec" class="input-field">
                  <option value="aac">AAC</option>
                  <option value="mp3">MP3</option>
                  <option value="opus">Opus</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Access Control -->
        <div class="card">
          <h2 class="text-lg font-semibold text-white mb-4">Access Control</h2>
          <div class="space-y-4">
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="form.is_available_to_all" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-purple-600 focus:ring-purple-500" />
              <span class="text-gray-300">Available to all users</span>
            </label>
            <div v-if="!form.is_available_to_all">
              <label class="block text-sm font-medium text-gray-300 mb-2">Restricted Packages</label>
              <div class="space-y-2 max-h-40 overflow-y-auto bg-gray-700 rounded-lg p-3">
                <label v-for="pkg in packages" :key="pkg.id" class="flex items-center gap-2 text-gray-300 text-sm">
                  <input type="checkbox" :value="pkg.id" v-model="form.restricted_package_ids" class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-purple-600 focus:ring-purple-500" />
                  {{ pkg.name }}
                </label>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">IP Restriction (comma-separated)</label>
              <input v-model="form.ip_restriction" type="text" class="input-field" placeholder="192.168.1.1, 10.0.0.0/24" />
            </div>
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="form.is_adult" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-purple-600 focus:ring-purple-500" />
              <span class="text-gray-300">Adult Content (requires PIN)</span>
            </label>
          </div>
        </div>

        <!-- Status -->
        <div class="card">
          <h2 class="text-lg font-semibold text-white mb-4">Status</h2>
          <div class="grid grid-cols-2 gap-4">
            <label class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
              <input v-model="form.is_active" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-purple-600 focus:ring-purple-500" /> Active
            </label>
            <label class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
              <input v-model="form.is_free" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-purple-600 focus:ring-purple-500" /> Free Channel
            </label>
          </div>
        </div>

        <div class="flex justify-between pt-4 border-t border-gray-700">
          <button
            type="button"
            @click="testStream"
            :disabled="!form.stream_url || testing"
            class="btn-secondary"
          >
            {{ testing ? 'Testing...' : 'Test Stream' }}
          </button>
          <div class="flex gap-3">
            <Link :href="route('admin.channels.index')" class="btn-secondary">Cancel</Link>
            <button type="submit" :disabled="form.processing" class="btn-primary">
              {{ form.processing ? 'Updating...' : 'Update Channel' }}
            </button>
          </div>
        </div>
      </form>

      <!-- Test Stream Result Modal -->
      <Modal :show="showTestResult" @close="showTestResult = false" max-width="md">
        <div class="p-6">
          <h3 class="text-lg font-semibold text-white mb-4">Stream Test Result</h3>
          <div v-if="testResult" class="space-y-4">
            <div class="bg-gray-700 rounded-lg p-4 space-y-2">
              <div class="flex items-center justify-between">
                <span class="text-gray-400 text-sm">Status</span>
                <span class="text-sm font-medium px-2 py-0.5 rounded-full"
                  :class="testResult.status === 'online' ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'">
                  {{ testResult.status === 'online' ? 'Online' : 'Offline' }}
                </span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-400 text-sm">URL</span>
                <span class="text-white text-sm truncate max-w-[200px]">{{ channel?.stream_url }}</span>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="bg-gray-700 rounded-lg p-3">
                <span class="text-gray-500 text-xs block mb-1">Type</span>
                <span class="text-white text-sm font-medium">{{ testResult.detected_type || channel?.stream_type || 'N/A' }}</span>
              </div>
              <div class="bg-gray-700 rounded-lg p-3">
                <span class="text-gray-500 text-xs block mb-1">Quality</span>
                <span class="text-white text-sm font-medium">{{ testResult.quality || 'Unknown' }}</span>
              </div>
              <div class="bg-gray-700 rounded-lg p-3">
                <span class="text-gray-500 text-xs block mb-1">Resolution</span>
                <span class="text-white text-sm font-medium">{{ testResult.resolution || 'N/A' }}</span>
              </div>
              <div class="bg-gray-700 rounded-lg p-3">
                <span class="text-gray-500 text-xs block mb-1">Codec</span>
                <span class="text-white text-sm font-medium">{{ testResult.codec || 'N/A' }}</span>
              </div>
              <div class="bg-gray-700 rounded-lg p-3">
                <span class="text-gray-500 text-xs block mb-1">Bitrate</span>
                <span class="text-white text-sm font-medium">{{ testResult.bitrate ? Math.round(testResult.bitrate / 1000) + ' kbps' : 'N/A' }}</span>
              </div>
              <div class="bg-gray-700 rounded-lg p-3">
                <span class="text-gray-500 text-xs block mb-1">FPS</span>
                <span class="text-white text-sm font-medium">{{ testResult.fps || 'N/A' }}</span>
              </div>
              <div class="bg-gray-700 rounded-lg p-3">
                <span class="text-gray-500 text-xs block mb-1">HTTP Code</span>
                <span class="text-white text-sm font-medium">{{ testResult.http_code || 'N/A' }}</span>
              </div>
              <div class="bg-gray-700 rounded-lg p-3">
                <span class="text-gray-500 text-xs block mb-1">Response Time</span>
                <span class="text-white text-sm font-medium">{{ testResult.response_time }}ms</span>
              </div>
            </div>

            <div v-if="testResult.error" class="bg-red-500/10 rounded-lg p-3">
              <span class="text-red-400 text-sm">{{ testResult.error }}</span>
            </div>
          </div>
          <div class="mt-6 flex justify-end">
            <button @click="showTestResult = false" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg text-sm transition">
              Close
            </button>
          </div>
        </div>
      </Modal>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Modal from '@/Components/Common/Modal.vue'
import { ArrowLeft } from 'lucide-vue-next'

const props = defineProps({
  channel: { type: Object, required: true },
  categories: { type: Array, default: () => [] },
  bouquets: { type: Array, default: () => [] },
  epgSources: { type: Array, default: () => [] },
  packages: { type: Array, default: () => [] },
})

const form = useForm({
  name: props.channel?.name || '',
  channel_number: props.channel?.channel_number || null,
  description: props.channel?.description || '',
  logo_url: props.channel?.logo_url || '',
  genre: props.channel?.genre || '',
  country: props.channel?.country || '',
  language: props.channel?.language || '',
  stream_url: props.channel?.stream_url || '',
  stream_type: props.channel?.stream_type || 'hls',
  backup_url_1: props.channel?.backup_url_1 || '',
  backup_url_2: props.channel?.backup_url_2 || '',
  quality: props.channel?.quality || '1080p',
  bitrate: props.channel?.bitrate || null,
  epg_id: props.channel?.epg_id || '',
  epg_source_id: props.channel?.epg_source_id || null,
  epg_language: props.channel?.epg_language || 'en',
  timezone_offset: props.channel?.timezone_offset || 'UTC+0',
  category_ids: (props.channel?.categories || []).map(c => c.id),
  bouquet_ids: (props.channel?.bouquets || []).map(b => b.id),
  transcoding_enabled: props.channel?.transcoding_enabled ?? false,
  transcoding_profile: props.channel?.transcoding_profile || 'auto',
  transcoding_resolution: props.channel?.transcoding_resolution || '1080p',
  transcoding_video_codec: props.channel?.transcoding_video_codec || 'h264',
  transcoding_audio_codec: props.channel?.transcoding_audio_codec || 'aac',
  is_active: props.channel?.is_active ?? true,
  is_free: props.channel?.is_free ?? false,
  is_adult: props.channel?.is_adult ?? false,
  is_available_to_all: props.channel?.is_available_to_all ?? true,
  ip_restriction: props.channel?.ip_restriction || '',
  restricted_package_ids: (props.channel?.restricted_packages || []).map(p => p.id),
  sort_order: props.channel?.sort_order || 0,
})

const testing = ref(false)
const showTestResult = ref(false)
const testResult = ref(null)

const testStream = async () => {
  if (!form.stream_url) return

  testing.value = true
  try {
    const response = await fetch(route('admin.channels.test-stream', { channel: props.channel.id }), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
      },
      body: JSON.stringify({ url: form.stream_url }),
    })
    const data = await response.json()
    testResult.value = data.data || data
    showTestResult.value = true
  } catch (error) {
    testResult.value = {
      status: 'error',
      error: error.message,
    }
    showTestResult.value = true
  } finally {
    testing.value = false
  }
}
</script>
