<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto space-y-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">EPG Settings</h1>
        <p class="text-gray-400 mt-1">Configure Electronic Program Guide settings</p>
      </div>
      <form @submit.prevent="form.put(route('admin.settings.epg.update'))" class="space-y-6">
        <!-- EPG Configuration -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">EPG Configuration</h3>
          <div class="space-y-4">
            <div class="flex items-center gap-3">
              <input v-model="form.enable_epg" type="checkbox" id="enable_epg" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="enable_epg" class="text-gray-300">Enable EPG</label>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Update Interval</label>
              <select v-model="form.update_interval" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="hourly">Hourly</option>
                <option value="3_hours">Every 3 Hours</option>
                <option value="6_hours">Every 6 Hours</option>
                <option value="12_hours">Every 12 Hours</option>
                <option value="daily">Daily</option>
              </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Data Retention (days)</label>
                <input v-model="form.data_retention" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Cache Duration (minutes)</label>
                <input v-model="form.cache_duration" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Max EPG Size (MB)</label>
              <input v-model="form.max_epg_size" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.enable_compression" type="checkbox" id="enable_compression" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="enable_compression" class="text-gray-300">Enable Compression</label>
            </div>
          </div>
        </div>

        <!-- EPG Sources -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">EPG Sources</h3>
          <div class="space-y-4">
            <div class="flex items-center gap-3">
              <input v-model="form.auto_update_sources" type="checkbox" id="auto_update_sources" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="auto_update_sources" class="text-gray-300">Auto-update Sources</label>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Source Update Time</label>
                <input v-model="form.source_update_time" type="time" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Max Sources</label>
                <input v-model="form.max_sources" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Source Timeout (seconds)</label>
              <input v-model="form.source_timeout" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.retry_on_failure" type="checkbox" id="retry_on_failure" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="retry_on_failure" class="text-gray-300">Retry on Failure</label>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Max Retries</label>
              <input v-model="form.max_retries" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Fallback Source URL</label>
              <input v-model="form.fallback_source" type="url" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white font-mono text-sm focus:outline-none focus:border-indigo-500" placeholder="https://example.com/epg.xml.gz" />
            </div>
          </div>
        </div>

        <!-- Program Mapping -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Program Mapping</h3>
          <div class="space-y-4">
            <div class="flex items-center gap-3">
              <input v-model="form.auto_mapping_enabled" type="checkbox" id="auto_mapping_enabled" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="auto_mapping_enabled" class="text-gray-300">Enable Auto Mapping</label>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Mapping Strategy</label>
              <select v-model="form.mapping_strategy" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="name">Channel Name</option>
                <option value="id">Channel ID</option>
                <option value="tvg_id">TVG ID</option>
                <option value="logo">Logo Match</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Mapping Accuracy</label>
              <select v-model="form.mapping_accuracy" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="strict">Strict (100% match required)</option>
                <option value="high">High (95%+ match)</option>
                <option value="medium">Medium (80%+ match)</option>
                <option value="low">Low (60%+ match)</option>
              </select>
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.manual_override" type="checkbox" id="manual_override" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="manual_override" class="text-gray-300">Allow Manual Override</label>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-3">Matching Methods</label>
              <div class="flex flex-wrap gap-3">
                <label class="flex items-center gap-2 text-gray-300 bg-gray-700/50 px-3 py-2 rounded-lg cursor-pointer hover:bg-gray-700 transition">
                  <input v-model="form.match_exact" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                  Exact Match
                </label>
                <label class="flex items-center gap-2 text-gray-300 bg-gray-700/50 px-3 py-2 rounded-lg cursor-pointer hover:bg-gray-700 transition">
                  <input v-model="form.match_partial" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                  Partial Match
                </label>
                <label class="flex items-center gap-2 text-gray-300 bg-gray-700/50 px-3 py-2 rounded-lg cursor-pointer hover:bg-gray-700 transition">
                  <input v-model="form.match_similarity" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                  Similarity Match
                </label>
                <label class="flex items-center gap-2 text-gray-300 bg-gray-700/50 px-3 py-2 rounded-lg cursor-pointer hover:bg-gray-700 transition">
                  <input v-model="form.match_remove_numbers" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                  Remove Numbers
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- EPG Output -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">EPG Output</h3>
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Output Format</label>
                <select v-model="form.output_format" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option value="xmltv">XMLTV</option>
                  <option value="xtream">Xtream Codes</option>
                  <option value="json">JSON</option>
                  <option value="jellyfin">Jellyfin</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Output Encoding</label>
                <select v-model="form.output_encoding" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option value="utf-8">UTF-8</option>
                  <option value="iso-8859-1">ISO-8859-1</option>
                  <option value="windows-1252">Windows-1252</option>
                </select>
              </div>
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.include_logos" type="checkbox" id="include_logos" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="include_logos" class="text-gray-300">Include Channel Logos</label>
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.program_images" type="checkbox" id="program_images" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="program_images" class="text-gray-300">Include Program Images</label>
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.include_ratings" type="checkbox" id="include_ratings" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="include_ratings" class="text-gray-300">Include Ratings</label>
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.include_subtitles" type="checkbox" id="include_subtitles" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="include_subtitles" class="text-gray-300">Include Subtitle Info</label>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">EPG Filtering</label>
              <select v-model="form.epg_filtering" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="none">No Filtering</option>
                <option value="adult">Exclude Adult Content</option>
                <option value="kids">Kids Only</option>
                <option value="custom">Custom Filter</option>
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

const form = useForm({
  enable_epg: props.settings.enable_epg ?? true,
  update_interval: props.settings.update_interval || '6_hours',
  data_retention: props.settings.data_retention || 7,
  cache_duration: props.settings.cache_duration || 30,
  max_epg_size: props.settings.max_epg_size || 50,
  enable_compression: props.settings.enable_compression ?? true,
  auto_update_sources: props.settings.auto_update_sources ?? true,
  source_update_time: props.settings.source_update_time || '03:00',
  max_sources: props.settings.max_sources || 10,
  source_timeout: props.settings.source_timeout || 30,
  retry_on_failure: props.settings.retry_on_failure ?? true,
  max_retries: props.settings.max_retries || 3,
  fallback_source: props.settings.fallback_source || '',
  auto_mapping_enabled: props.settings.auto_mapping_enabled ?? true,
  mapping_strategy: props.settings.mapping_strategy || 'name',
  mapping_accuracy: props.settings.mapping_accuracy || 'high',
  manual_override: props.settings.manual_override ?? true,
  match_exact: props.settings.match_exact ?? true,
  match_partial: props.settings.match_partial ?? true,
  match_similarity: props.settings.match_similarity ?? false,
  match_remove_numbers: props.settings.match_remove_numbers ?? false,
  output_format: props.settings.output_format || 'xmltv',
  output_encoding: props.settings.output_encoding || 'utf-8',
  include_logos: props.settings.include_logos ?? true,
  program_images: props.settings.program_images ?? false,
  include_ratings: props.settings.include_ratings ?? false,
  include_subtitles: props.settings.include_subtitles ?? false,
  epg_filtering: props.settings.epg_filtering || 'none',
})
</script>
