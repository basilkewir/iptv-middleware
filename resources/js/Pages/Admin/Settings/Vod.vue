<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto space-y-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">VOD Settings</h1>
        <p class="text-gray-400 mt-1">Configure video on demand content, metadata, and transcoding</p>
      </div>
      <form @submit.prevent="form.put(route('admin.settings.vod.update'))" class="space-y-6">
        <!-- VOD Content -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">VOD Content</h3>
          <div class="space-y-4">
            <div class="flex items-center gap-3">
              <input v-model="form.enable_vod" type="checkbox" id="enable_vod" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="enable_vod" class="text-gray-300">Enable VOD</label>
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.auto_import" type="checkbox" id="auto_import" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="auto_import" class="text-gray-300">Enable Auto Import</label>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Import Directory</label>
                <input v-model="form.import_directory" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white font-mono text-sm focus:outline-none focus:border-indigo-500" placeholder="/mnt/media/import" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Storage Path</label>
                <input v-model="form.storage_path" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white font-mono text-sm focus:outline-none focus:border-indigo-500" placeholder="/mnt/media/vod" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Max File Size (GB)</label>
              <input v-model="form.max_file_size" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-3">Supported Formats</label>
              <div class="flex flex-wrap gap-2">
                <label v-for="fmt in formats" :key="fmt" class="flex items-center gap-2 text-gray-300 bg-gray-700/50 px-3 py-2 rounded-lg text-sm cursor-pointer hover:bg-gray-700 transition">
                  <input v-model="form.supported_formats" type="checkbox" :value="fmt" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                  {{ fmt }}
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- Metadata -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Metadata</h3>
          <div class="space-y-4">
            <div class="flex items-center gap-3">
              <input v-model="form.enable_auto_metadata" type="checkbox" id="enable_auto_metadata" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="enable_auto_metadata" class="text-gray-300">Enable Auto Metadata</label>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Metadata Source</label>
              <select v-model="form.metadata_source" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="tmdb">TMDB (The Movie Database)</option>
                <option value="imdb">IMDB</option>
                <option value="thetvdb">TheTVDB</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">API Key</label>
              <input v-model="form.metadata_api_key" type="password" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" placeholder="Enter API key" />
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.auto_fetch_cast" type="checkbox" id="auto_fetch_cast" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="auto_fetch_cast" class="text-gray-300">Auto-fetch Cast</label>
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.auto_fetch_trailers" type="checkbox" id="auto_fetch_trailers" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="auto_fetch_trailers" class="text-gray-300">Auto-fetch Trailers</label>
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.auto_fetch_poster" type="checkbox" id="auto_fetch_poster" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="auto_fetch_poster" class="text-gray-300">Auto-fetch Poster</label>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Poster Resolution</label>
              <select v-model="form.poster_resolution" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="w342">342px (w342)</option>
                <option value="w500">500px (w500)</option>
                <option value="w780">780px (w780)</option>
                <option value="original">Original</option>
              </select>
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.auto_fetch_backdrop" type="checkbox" id="auto_fetch_backdrop" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="auto_fetch_backdrop" class="text-gray-300">Auto-fetch Backdrop</label>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Backdrop Resolution</label>
              <select v-model="form.backdrop_resolution" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="w780">780px (w780)</option>
                <option value="w1280">1280px (w1280)</option>
                <option value="original">Original</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Series -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Series</h3>
          <div class="space-y-4">
            <div class="flex items-center gap-3">
              <input v-model="form.enable_series" type="checkbox" id="enable_series" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="enable_series" class="text-gray-300">Enable Series</label>
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.auto_detect_series" type="checkbox" id="auto_detect_series" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="auto_detect_series" class="text-gray-300">Auto-detect Series</label>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Series Naming Convention</label>
              <select v-model="form.series_naming" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="show_name">Show Name</option>
                <option value="show_name_year">Show Name (Year)</option>
                <option value="show_name_sXXeXX">Show Name S01E01</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Season Folder Format</label>
              <select v-model="form.season_folder_format" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="season_01">Season 01</option>
                <option value="season_1">Season 1</option>
                <option value="s01">S01</option>
                <option value="s1">S1</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Episode Naming Convention</label>
              <select v-model="form.episode_naming" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="sXXeXX">S01E01</option>
                <option value="sXeX">S1E1</option>
                <option value="season_episode">1x01</option>
              </select>
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.auto_group_episodes" type="checkbox" id="auto_group_episodes" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="auto_group_episodes" class="text-gray-300">Auto-group Episodes</label>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Max Seasons</label>
                <input v-model="form.max_seasons" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Max Episodes per Season</label>
                <input v-model="form.max_episodes_per_season" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
          </div>
        </div>

        <!-- Transcoding for VOD -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Transcoding for VOD</h3>
          <div class="space-y-4">
            <div class="flex items-center gap-3">
              <input v-model="form.enable_vod_transcoding" type="checkbox" id="enable_vod_transcoding" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="enable_vod_transcoding" class="text-gray-300">Enable VOD Transcoding</label>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Default Quality Profile</label>
              <select v-model="form.default_quality_profile" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="ultra">Ultra (Best quality, slowest)</option>
                <option value="high">High</option>
                <option value="medium">Medium (Balanced)</option>
                <option value="low">Low</option>
                <option value="potato">Potato (Fastest, lowest quality)</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-3">Adaptive Bitrate Profiles</label>
              <div class="flex flex-wrap gap-2">
                <label v-for="profile in abrProfiles" :key="profile" class="flex items-center gap-2 text-gray-300 bg-gray-700/50 px-3 py-2 rounded-lg text-sm cursor-pointer hover:bg-gray-700 transition">
                  <input v-model="form.adaptive_bitrate_profiles" type="checkbox" :value="profile" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                  {{ profile }}
                </label>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Transcoding Priority</label>
              <select v-model="form.transcoding_priority" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="highest">Highest Priority</option>
                <option value="high">High Priority</option>
                <option value="normal">Normal</option>
                <option value="low">Low Priority</option>
                <option value="lowest">Lowest Priority</option>
              </select>
            </div>
          </div>
        </div>

        <div class="flex justify-end">
          <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50">
            {{ form.processing ? 'Saving...' : 'Save Settings' }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ settings: Object })

const formats = ['MP4', 'MKV', 'AVI', 'MOV', 'MPEG', 'FLV', 'WEBM', '3GP', 'WMV', 'ASF', 'M4V', 'OGV', 'TS']
const abrProfiles = ['1080p', '720p', '480p', '360p', '240p']

const form = useForm({
  enable_vod: props.settings.enable_vod ?? true,
  auto_import: props.settings.auto_import ?? false,
  import_directory: props.settings.import_directory || '/mnt/media/import',
  storage_path: props.settings.storage_path || '/mnt/media/vod',
  max_file_size: props.settings.max_file_size || 10,
  supported_formats: props.settings.supported_formats || ['MP4', 'MKV', 'AVI', 'WEBM'],
  enable_auto_metadata: props.settings.enable_auto_metadata ?? true,
  metadata_source: props.settings.metadata_source || 'tmdb',
  metadata_api_key: props.settings.metadata_api_key || '',
  auto_fetch_cast: props.settings.auto_fetch_cast ?? true,
  auto_fetch_trailers: props.settings.auto_fetch_trailers ?? false,
  auto_fetch_poster: props.settings.auto_fetch_poster ?? true,
  poster_resolution: props.settings.poster_resolution || 'w500',
  auto_fetch_backdrop: props.settings.auto_fetch_backdrop ?? false,
  backdrop_resolution: props.settings.backdrop_resolution || 'w1280',
  enable_series: props.settings.enable_series ?? true,
  auto_detect_series: props.settings.auto_detect_series ?? true,
  series_naming: props.settings.series_naming || 'show_name',
  season_folder_format: props.settings.season_folder_format || 'season_01',
  episode_naming: props.settings.episode_naming || 'sXXeXX',
  auto_group_episodes: props.settings.auto_group_episodes ?? true,
  max_seasons: props.settings.max_seasons || 50,
  max_episodes_per_season: props.settings.max_episodes_per_season || 30,
  enable_vod_transcoding: props.settings.enable_vod_transcoding ?? false,
  default_quality_profile: props.settings.default_quality_profile || 'medium',
  adaptive_bitrate_profiles: props.settings.adaptive_bitrate_profiles || ['1080p', '720p', '480p'],
  transcoding_priority: props.settings.transcoding_priority || 'normal',
})
</script>
