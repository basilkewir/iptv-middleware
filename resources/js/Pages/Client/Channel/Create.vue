<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
      <div class="mb-6">
        <Link :href="route('client.channels.index')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
          Back to Channels
        </Link>
        <h1 class="text-2xl font-bold text-white">Create Channel</h1>
      </div>

      <form @submit.prevent="form.post(route('client.channels.store'))" class="space-y-6">
        <!-- Basic Information -->
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
          <h2 class="text-lg font-semibold text-white mb-4">Basic Information</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-300 mb-2">Channel Name <span class="text-red-500">*</span></label>
              <input v-model="form.channel_name" type="text" placeholder="Enter channel name..."
                class="w-full px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent" />
              <p v-if="form.errors.channel_name" class="text-red-400 text-sm mt-1">{{ form.errors.channel_name }}</p>
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
              <textarea v-model="form.description" rows="3" placeholder="Channel description..."
                class="w-full px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent resize-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Channel Number</label>
              <input v-model="form.channel_number" type="text" placeholder="Auto-assigned"
                class="w-full px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Category</label>
              <input v-model="form.category" type="text" placeholder="e.g. Entertainment"
                class="w-full px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Genre</label>
              <input v-model="form.genre" type="text" placeholder="e.g. Sports"
                class="w-full px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Language</label>
              <input v-model="form.language" type="text" placeholder="en"
                class="w-full px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent" />
            </div>
          </div>
        </div>

        <!-- Stream Settings -->
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
          <h2 class="text-lg font-semibold text-white mb-4">Stream Settings</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Stream URL</label>
              <input v-model="form.stream_url" type="url" placeholder="https://..."
                class="w-full px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Stream Type</label>
              <select v-model="form.stream_type"
                class="w-full px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                <option value="hls">HLS</option>
                <option value="rtmp">RTMP</option>
                <option value="mpegts">MPEG-TS</option>
                <option value="http">HTTP</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Stream Key</label>
              <input v-model="form.stream_key" type="text" placeholder="Optional"
                class="w-full px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Output Resolution</label>
              <input v-model="form.output_resolution" type="text" placeholder="e.g. 1080p"
                class="w-full px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Output Bitrate (kbps)</label>
              <input v-model.number="form.output_bitrate" type="number" placeholder="Optional"
                class="w-full px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent" />
            </div>
          </div>
        </div>

        <!-- Branding -->
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
          <h2 class="text-lg font-semibold text-white mb-4">Branding</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Logo URL</label>
              <input v-model="form.logo_url" type="url" placeholder="https://..."
                class="w-full px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Banner URL</label>
              <input v-model="form.banner_url" type="url" placeholder="https://..."
                class="w-full px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Background Color</label>
              <input v-model="form.background_color" type="color"
                class="w-full px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent h-10" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Accent Color</label>
              <input v-model="form.accent_color" type="color"
                class="w-full px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent h-10" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Text Color</label>
              <input v-model="form.text_color" type="color"
                class="w-full px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent h-10" />
            </div>
          </div>
        </div>

        <!-- Playlist Settings -->
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
          <h2 class="text-lg font-semibold text-white mb-4">Playlist Settings</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Playlist Mode</label>
              <select v-model="form.playlist_mode"
                class="w-full px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                <option value="auto">Auto</option>
                <option value="manual">Manual</option>
                <option value="scheduled">Scheduled</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Default Duration (sec)</label>
              <input v-model.number="form.default_duration" type="number"
                class="w-full px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent" />
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.loop_playlist" type="checkbox" id="loop_playlist"
                class="w-4 h-4 rounded border-gray-600 bg-gray-800 text-purple-600 focus:ring-purple-500" />
              <label for="loop_playlist" class="text-sm text-gray-300">Loop Playlist</label>
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.shuffle_mode" type="checkbox" id="shuffle_mode"
                class="w-4 h-4 rounded border-gray-600 bg-gray-800 text-purple-600 focus:ring-purple-500" />
              <label for="shuffle_mode" class="text-sm text-gray-300">Shuffle Mode</label>
            </div>
          </div>
        </div>

        <!-- Broadcast Settings -->
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
          <h2 class="text-lg font-semibold text-white mb-4">Broadcast Settings</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Broadcast Status</label>
              <select v-model="form.broadcast_status"
                class="w-full px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                <option value="offline">Offline</option>
                <option value="scheduled">Scheduled</option>
                <option value="live">Live</option>
                <option value="ended">Ended</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Timezone</label>
              <input v-model="form.timezone" type="text" placeholder="UTC"
                class="w-full px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Scheduled Start</label>
              <input v-model="form.scheduled_start" type="datetime-local"
                class="w-full px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Scheduled End</label>
              <input v-model="form.scheduled_end" type="datetime-local"
                class="w-full px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent" />
            </div>
          </div>
        </div>

        <!-- Ticker & Overlays -->
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
          <h2 class="text-lg font-semibold text-white mb-4">Ticker & Overlays</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex items-center gap-3">
              <input v-model="form.enable_ticker" type="checkbox" id="enable_ticker"
                class="w-4 h-4 rounded border-gray-600 bg-gray-800 text-purple-600 focus:ring-purple-500" />
              <label for="enable_ticker" class="text-sm text-gray-300">Enable Ticker</label>
            </div>
            <div v-if="form.enable_ticker">
              <input v-model="form.ticker_text" type="text" placeholder="Ticker text..."
                class="w-full px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent mt-2" />
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.enable_overlay_logo" type="checkbox" id="enable_overlay_logo"
                class="w-4 h-4 rounded border-gray-600 bg-gray-800 text-purple-600 focus:ring-purple-500" />
              <label for="enable_overlay_logo" class="text-sm text-gray-300">Enable Overlay Logo</label>
            </div>
          </div>
        </div>

        <!-- Visibility -->
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
          <h2 class="text-lg font-semibold text-white mb-4">Visibility</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex items-center gap-3">
              <input v-model="form.is_public" type="checkbox" id="is_public"
                class="w-4 h-4 rounded border-gray-600 bg-gray-800 text-purple-600 focus:ring-purple-500" />
              <label for="is_public" class="text-sm text-gray-300">Public Channel</label>
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.is_active" type="checkbox" id="is_active"
                class="w-4 h-4 rounded border-gray-600 bg-gray-800 text-purple-600 focus:ring-purple-500" />
              <label for="is_active" class="text-sm text-gray-300">Active</label>
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.is_featured" type="checkbox" id="is_featured"
                class="w-4 h-4 rounded border-gray-600 bg-gray-800 text-purple-600 focus:ring-purple-500" />
              <label for="is_featured" class="text-sm text-gray-300">Featured</label>
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.is_adult" type="checkbox" id="is_adult"
                class="w-4 h-4 rounded border-gray-600 bg-gray-800 text-purple-600 focus:ring-purple-500" />
              <label for="is_adult" class="text-sm text-gray-300">Adult Content</label>
            </div>
          </div>
        </div>

        <!-- Submit -->
        <div class="flex items-center gap-4">
          <button type="submit" :disabled="form.processing"
            class="px-6 py-3 bg-purple-600 hover:bg-purple-700 disabled:bg-gray-600 text-white font-medium rounded-lg transition-colors">
            Create Channel
          </button>
          <Link :href="route('client.channels.index')"
            class="px-6 py-3 bg-gray-800 hover:bg-gray-700 text-white font-medium rounded-lg transition-colors">
            Cancel
          </Link>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AppLayout from '@/Layouts/AppLayout.vue'

const form = useForm({
  channel_name: '',
  description: '',
  channel_number: '',
  logo_url: '',
  banner_url: '',
  background_color: '',
  accent_color: '',
  text_color: '',
  stream_url: '',
  stream_type: 'hls',
  stream_key: '',
  output_resolution: '',
  output_bitrate: null,
  playlist_mode: 'auto',
  default_duration: 0,
  loop_playlist: true,
  shuffle_mode: false,
  is_live: false,
  broadcast_status: 'offline',
  scheduled_start: '',
  scheduled_end: '',
  timezone: 'UTC',
  enable_ticker: false,
  ticker_text: '',
  ticker_speed: 30,
  ticker_color: '',
  ticker_background: '',
  enable_overlay_logo: false,
  overlay_logo_position: 'top-left',
  overlay_logo_size: 100,
  language: 'en',
  genre: '',
  category: '',
  is_adult: false,
  is_featured: false,
  is_active: true,
  is_public: true,
})
</script>