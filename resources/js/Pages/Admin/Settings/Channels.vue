<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto space-y-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">Channel Settings</h1>
        <p class="text-gray-400 mt-1">Configure channel management, stream, and fallback settings</p>
      </div>
      <form @submit.prevent="form.put(route('admin.settings.channels.update'))" class="space-y-6">
        <!-- Channel Management -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Channel Management</h3>
          <div class="space-y-4">
            <div class="flex items-center gap-3">
              <input v-model="form.auto_assign_numbers" type="checkbox" id="auto_assign_numbers" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="auto_assign_numbers" class="text-gray-300">Auto-assign Channel Numbers</label>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Channel Number Start</label>
                <input v-model="form.channel_number_start" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Channel Number Increment</label>
                <input v-model="form.channel_number_increment" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.allow_duplicate_names" type="checkbox" id="allow_duplicate_names" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="allow_duplicate_names" class="text-gray-300">Allow Duplicate Channel Names</label>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Max Channels per Server</label>
              <input v-model="form.max_channels_per_server" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Default Channel Status</label>
              <div class="flex items-center gap-4">
                <label class="flex items-center gap-2 text-gray-300">
                  <input v-model="form.default_channel_status" type="radio" value="active" class="w-4 h-4 bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                  Active
                </label>
                <label class="flex items-center gap-2 text-gray-300">
                  <input v-model="form.default_channel_status" type="radio" value="inactive" class="w-4 h-4 bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                  Inactive
                </label>
                <label class="flex items-center gap-2 text-gray-300">
                  <input v-model="form.default_channel_status" type="radio" value="draft" class="w-4 h-4 bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                  Draft
                </label>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Default Channel Quality</label>
              <select v-model="form.default_channel_quality" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="sd">SD (480p)</option>
                <option value="hd">HD (720p)</option>
                <option value="fhd">Full HD (1080p)</option>
                <option value="uhd">4K UHD (2160p)</option>
              </select>
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.enable_preview" type="checkbox" id="enable_preview" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="enable_preview" class="text-gray-300">Enable Channel Preview</label>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Preview Duration (seconds)</label>
              <input v-model="form.preview_duration" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
          </div>
        </div>

        <!-- Stream Settings -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Stream Settings</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-3">Supported Stream Types</label>
              <div class="flex flex-wrap gap-3">
                <label v-for="type in streamTypes" :key="type" class="flex items-center gap-2 text-gray-300 bg-gray-700/50 px-3 py-2 rounded-lg cursor-pointer hover:bg-gray-700 transition">
                  <input v-model="form.supported_stream_types" type="checkbox" :value="type" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                  {{ type }}
                </label>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Default Stream Type</label>
              <select v-model="form.default_stream_type" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="hls">HLS</option>
                <option value="rtmp">RTMP</option>
                <option value="mpeg-ts">MPEG-TS</option>
                <option value="http">HTTP</option>
                <option value="udp">UDP</option>
                <option value="srt">SRT</option>
              </select>
            </div>
            <div class="grid grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Stream Timeout (s)</label>
                <input v-model="form.stream_timeout" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Buffer Size (KB)</label>
                <input v-model="form.buffer_size" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Max Bitrate (Mbps)</label>
                <input v-model="form.max_bitrate" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.allow_restart" type="checkbox" id="allow_restart" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="allow_restart" class="text-gray-300">Allow Stream Restart</label>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Restart Delay (seconds)</label>
              <input v-model="form.restart_delay" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
          </div>
        </div>

        <!-- Fallback Settings -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Fallback Settings</h3>
          <div class="space-y-4">
            <div class="flex items-center gap-3">
              <input v-model="form.enable_auto_failover" type="checkbox" id="enable_auto_failover" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="enable_auto_failover" class="text-gray-300">Enable Auto Failover</label>
            </div>
            <div class="grid grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Failover Interval (s)</label>
                <input v-model="form.failover_interval" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Failover Retries</label>
                <input v-model="form.failover_retries" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Failover Timeout (s)</label>
                <input v-model="form.failover_timeout" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.use_backup_streams" type="checkbox" id="use_backup_streams" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="use_backup_streams" class="text-gray-300">Use Backup Streams</label>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Max Fallback Servers</label>
              <input v-model="form.max_fallback_servers" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
          </div>
        </div>

        <!-- Stream Health Check -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Stream Health Check</h3>
          <div class="space-y-4">
            <div class="flex items-center gap-3">
              <input v-model="form.enable_health_checks" type="checkbox" id="enable_health_checks" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="enable_health_checks" class="text-gray-300">Enable Health Checks</label>
            </div>
            <div class="grid grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Health Check Interval (s)</label>
                <input v-model="form.health_check_interval" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Failure Threshold</label>
                <input v-model="form.failure_threshold" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Success Threshold</label>
                <input v-model="form.success_threshold" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Health Check Timeout (s)</label>
              <input v-model="form.health_check_timeout" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.notify_on_failure" type="checkbox" id="notify_on_failure" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="notify_on_failure" class="text-gray-300">Notify on Failure</label>
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.auto_restart_on_failure" type="checkbox" id="auto_restart_on_failure" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="auto_restart_on_failure" class="text-gray-300">Auto-restart on Failure</label>
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

const streamTypes = ['HLS', 'RTMP', 'MPEG-TS', 'HTTP', 'UDP', 'SRT']

const form = useForm({
  auto_assign_numbers: props.settings.auto_assign_numbers ?? true,
  channel_number_start: props.settings.channel_number_start || 1,
  channel_number_increment: props.settings.channel_number_increment || 1,
  allow_duplicate_names: props.settings.allow_duplicate_names ?? false,
  max_channels_per_server: props.settings.max_channels_per_server || 500,
  default_channel_status: props.settings.default_channel_status || 'active',
  default_channel_quality: props.settings.default_channel_quality || 'hd',
  enable_preview: props.settings.enable_preview ?? true,
  preview_duration: props.settings.preview_duration || 30,
  supported_stream_types: props.settings.supported_stream_types || ['HLS', 'RTMP', 'HTTP'],
  default_stream_type: props.settings.default_stream_type || 'hls',
  stream_timeout: props.settings.stream_timeout || 30,
  buffer_size: props.settings.buffer_size || 1024,
  max_bitrate: props.settings.max_bitrate || 10,
  allow_restart: props.settings.allow_restart ?? true,
  restart_delay: props.settings.restart_delay || 5,
  enable_auto_failover: props.settings.enable_auto_failover ?? true,
  failover_interval: props.settings.failover_interval || 10,
  failover_retries: props.settings.failover_retries || 3,
  failover_timeout: props.settings.failover_timeout || 15,
  use_backup_streams: props.settings.use_backup_streams ?? true,
  max_fallback_servers: props.settings.max_fallback_servers || 3,
  enable_health_checks: props.settings.enable_health_checks ?? true,
  health_check_interval: props.settings.health_check_interval || 30,
  failure_threshold: props.settings.failure_threshold || 3,
  success_threshold: props.settings.success_threshold || 2,
  health_check_timeout: props.settings.health_check_timeout || 10,
  notify_on_failure: props.settings.notify_on_failure ?? true,
  auto_restart_on_failure: props.settings.auto_restart_on_failure ?? false,
})
</script>
