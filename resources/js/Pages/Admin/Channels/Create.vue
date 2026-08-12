<template>
  <AdminLayout>
    <div class="p-6 max-w-5xl mx-auto">
      <div class="mb-6">
        <Link :href="route('admin.channels.index')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
          <ArrowLeft class="w-4 h-4" /> Back to Channels
        </Link>
        <h1 class="text-2xl font-bold text-white">Add Channel</h1>
      </div>

      <form @submit.prevent="form.post(route('admin.channels.store'))" class="space-y-6">
        <!-- Basic Information -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h2 class="text-lg font-semibold text-white mb-4">Basic Information</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-300 mb-2">Channel Name <span class="text-red-500">*</span></label>
              <input v-model="form.name" type="text" placeholder="Enter channel name..."
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              <p v-if="form.errors.name" class="text-red-400 text-sm mt-1">{{ form.errors.name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Channel Number</label>
              <input v-model.number="form.channel_number" type="number" min="0" placeholder="Auto-assigned"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
              <textarea v-model="form.description" rows="2" placeholder="Channel description..."
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500 resize-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Logo</label>
              <input v-model="form.logo_url" type="url" placeholder="https://..."
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              <p class="text-xs text-gray-500 mt-1">200x200 recommended</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Genre</label>
              <select v-model="form.genre"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="">Select genre...</option>
                <option value="Sports">Sports</option>
                <option value="Entertainment">Entertainment</option>
                <option value="Movies">Movies</option>
                <option value="News">News</option>
                <option value="Documentary">Documentary</option>
                <option value="Kids">Kids</option>
                <option value="Music">Music</option>
                <option value="Education">Education</option>
                <option value="Religious">Religious</option>
                <option value="General">General</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Country</label>
              <select v-model="form.country"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="">Select country...</option>
                <option value="US">United States</option>
                <option value="GB">United Kingdom</option>
                <option value="FR">France</option>
                <option value="DE">Germany</option>
                <option value="ES">Spain</option>
                <option value="IT">Italy</option>
                <option value="BR">Brazil</option>
                <option value="AR">Argentina</option>
                <option value="CA">Canada</option>
                <option value="AU">Australia</option>
                <option value="IN">India</option>
                <option value="JP">Japan</option>
                <option value="CN">China</option>
                <option value="RU">Russia</option>
                <option value="ZA">South Africa</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Language</label>
              <select v-model="form.language"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="">Select language...</option>
                <option value="en">English</option>
                <option value="es">Spanish</option>
                <option value="fr">French</option>
                <option value="de">German</option>
                <option value="it">Italian</option>
                <option value="pt">Portuguese</option>
                <option value="ru">Russian</option>
                <option value="ar">Arabic</option>
                <option value="hi">Hindi</option>
                <option value="zh">Chinese</option>
                <option value="ja">Japanese</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Stream Configuration -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h2 class="text-lg font-semibold text-white mb-4">Stream Configuration</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-300 mb-2">Stream URL <span class="text-red-500">*</span></label>
              <input v-model="form.stream_url" type="url" placeholder="https://..."
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              <p v-if="form.errors.stream_url" class="text-red-400 text-sm mt-1">{{ form.errors.stream_url }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Stream Type</label>
              <select v-model="form.stream_type"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
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
              <select v-model="form.quality"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="4k">4K</option>
                <option value="1080p">FHD (1080p)</option>
                <option value="720p">HD (720p)</option>
                <option value="480p">SD (480p)</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Backup URL 1</label>
              <input v-model="form.backup_url_1" type="url" placeholder="https://..."
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Backup URL 2</label>
              <input v-model="form.backup_url_2" type="url" placeholder="https://..."
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Bitrate (kbps)</label>
              <input v-model.number="form.bitrate" type="number" min="0" placeholder="e.g. 5000"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
          </div>
        </div>

        <!-- EPG Configuration -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h2 class="text-lg font-semibold text-white mb-4">EPG Configuration</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">EPG Source</label>
              <select v-model="form.epg_source_id"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option :value="null">None</option>
                <option v-for="src in epgSources" :key="src.id" :value="src.id">{{ src.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">EPG ID</label>
              <input v-model="form.epg_id" type="text" placeholder="Channel ID from EPG source"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">EPG Language</label>
              <select v-model="form.epg_language"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="">Default</option>
                <option value="en">English</option>
                <option value="es">Spanish</option>
                <option value="fr">French</option>
                <option value="de">German</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Timezone Offset</label>
              <select v-model="form.timezone_offset"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="">UTC+0</option>
                <option value="-12:00">UTC-12</option>
                <option value="-11:00">UTC-11</option>
                <option value="-10:00">UTC-10</option>
                <option value="-09:00">UTC-9</option>
                <option value="-08:00">UTC-8</option>
                <option value="-07:00">UTC-7</option>
                <option value="-06:00">UTC-6</option>
                <option value="-05:00">UTC-5</option>
                <option value="-04:00">UTC-4</option>
                <option value="-03:00">UTC-3</option>
                <option value="-02:00">UTC-2</option>
                <option value="-01:00">UTC-1</option>
                <option value="+01:00">UTC+1</option>
                <option value="+02:00">UTC+2</option>
                <option value="+03:00">UTC+3</option>
                <option value="+04:00">UTC+4</option>
                <option value="+05:00">UTC+5</option>
                <option value="+05:30">UTC+5:30</option>
                <option value="+06:00">UTC+6</option>
                <option value="+07:00">UTC+7</option>
                <option value="+08:00">UTC+8</option>
                <option value="+09:00">UTC+9</option>
                <option value="+10:00">UTC+10</option>
                <option value="+11:00">UTC+11</option>
                <option value="+12:00">UTC+12</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Categorization -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h2 class="text-lg font-semibold text-white mb-4">Categorization</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Categories <span class="text-red-500">*</span></label>
              <div class="space-y-2 max-h-40 overflow-y-auto bg-gray-700 rounded-lg p-3">
                <label v-for="cat in categories" :key="cat.id" class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
                  <input type="checkbox" :value="cat.id" v-model="form.category_ids"
                    class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600 focus:ring-indigo-500" />
                  {{ cat.name }}
                </label>
              </div>
              <p v-if="form.errors.category_ids" class="text-red-400 text-sm mt-1">{{ form.errors.category_ids }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Bouquets</label>
              <div class="space-y-2 max-h-40 overflow-y-auto bg-gray-700 rounded-lg p-3">
                <label v-for="b in bouquets" :key="b.id" class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
                  <input type="checkbox" :value="b.id" v-model="form.bouquet_ids"
                    class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600 focus:ring-indigo-500" />
                  {{ b.name }}
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- Transcoding Settings -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h2 class="text-lg font-semibold text-white mb-4">Transcoding Settings</h2>
          <div class="space-y-4">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="form.transcoding_enabled"
                class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <span class="text-gray-300 text-sm">Enable Transcoding</span>
            </label>
            <div v-if="form.transcoding_enabled" class="grid grid-cols-1 md:grid-cols-4 gap-4 ml-6">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Profile</label>
                <select v-model="form.transcoding_profile"
                  class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option value="">Auto</option>
                  <option value="low_latency">Low Latency</option>
                  <option value="high_quality">High Quality</option>
                  <option value="balanced">Balanced</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Resolution</label>
                <select v-model="form.transcoding_resolution"
                  class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option value="">Default</option>
                  <option value="1080p">1080p</option>
                  <option value="720p">720p</option>
                  <option value="480p">480p</option>
                  <option value="360p">360p</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Video Codec</label>
                <select v-model="form.transcoding_video_codec"
                  class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option value="">Default</option>
                  <option value="h264">H.264</option>
                  <option value="h265">H.265 / HEVC</option>
                  <option value="vp9">VP9</option>
                  <option value="av1">AV1</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Audio Codec</label>
                <select v-model="form.transcoding_audio_codec"
                  class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option value="">Default</option>
                  <option value="aac">AAC</option>
                  <option value="mp3">MP3</option>
                  <option value="ac3">AC3 / Dolby Digital</option>
                  <option value="eac3">E-AC3 / Dolby Digital Plus</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Access Control -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h2 class="text-lg font-semibold text-white mb-4">Access Control</h2>
          <div class="space-y-4">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="form.is_free"
                class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <span class="text-gray-300 text-sm">Available to all users</span>
            </label>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Restricted Packages</label>
              <div class="space-y-2 max-h-32 overflow-y-auto bg-gray-700 rounded-lg p-3">
                <label v-for="pkg in packages" :key="pkg.id" class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
                  <input type="checkbox" :value="pkg.id" v-model="form.restricted_package_ids"
                    class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600 focus:ring-indigo-500" />
                  {{ pkg.name }}
                </label>
              </div>
              <p class="text-xs text-gray-500 mt-1">Only selected packages will have access when "Available to all" is unchecked.</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">IP Restriction</label>
              <input v-model="form.ip_restriction" type="text" placeholder="Comma-separated IPs or CIDR ranges"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="form.is_adult"
                class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <span class="text-gray-300 text-sm">Adult Content (requires PIN)</span>
            </label>
          </div>
        </div>

        <!-- Form Actions -->
        <div class="flex justify-between gap-3">
          <button type="button" @click="testStream"
            :disabled="!form.stream_url || testLoading"
            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-lg transition flex items-center gap-2">
            <span v-if="testLoading" class="animate-spin">⟳</span>
            <span v-else>Test Stream</span>
          </button>
          <div class="flex gap-3">
            <Link :href="route('admin.channels.index')"
              class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">
              Cancel
            </Link>
            <button type="submit" :disabled="form.processing"
              class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50 flex items-center gap-2">
              <span>{{ form.processing ? 'Adding...' : 'Add Channel' }}</span>
            </button>
          </div>
        </div>
        <div v-if="testResult" class="text-sm" :class="testResult.success ? 'text-green-400' : 'text-red-400'">
          {{ testResult.message }}
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useForm, Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeft } from 'lucide-vue-next'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  bouquets: { type: Array, default: () => [] },
  epgSources: { type: Array, default: () => [] },
  packages: { type: Array, default: () => [] },
  transcodingProfiles: { type: Array, default: () => [] },
})

const testLoading = ref(false)
const testResult = ref(null)

const form = useForm({
  name: '',
  channel_number: null,
  description: '',
  logo_url: '',
  genre: '',
  country: '',
  language: '',
  stream_url: '',
  stream_type: 'hls',
  backup_url_1: '',
  backup_url_2: '',
  quality: '1080p',
  bitrate: null,
  epg_id: '',
  epg_source_id: null,
  epg_language: '',
  timezone_offset: '',
  category_ids: [],
  bouquet_ids: [],
  transcoding_enabled: false,
  transcoding_profile: '',
  transcoding_resolution: '',
  transcoding_video_codec: '',
  transcoding_audio_codec: '',
  is_active: true,
  is_free: false,
  is_adult: false,
  ip_restriction: '',
  restricted_package_ids: [],
})

const testStream = () => {
  if (!form.stream_url) return
  testLoading.value = true
  testResult.value = null

  router.post(route('admin.channels.test-stream', { channel: 0 }), {
    stream_url: form.stream_url,
  }, {
    preserveState: true,
    preserveScroll: true,
    onSuccess: (page) => {
      testResult.value = {
        success: true,
        message: 'Stream URL is reachable and responding.',
      }
      testLoading.value = false
    },
    onError: (errors) => {
      testResult.value = {
        success: false,
        message: 'Stream test failed. Check the URL and try again.',
      }
      testLoading.value = false
    },
  })
}
</script>