<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto space-y-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">Logging Settings</h1>
        <p class="text-gray-400 mt-1">Configure logging, categories, retention, and exports</p>
      </div>

      <form @submit.prevent="form.put(route('admin.settings.logging.update'))" class="space-y-6">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Log Configuration</h3>
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Log Level</label>
                <select v-model="form.log_level" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option value="debug">Debug</option>
                  <option value="info">Info</option>
                  <option value="warning">Warning</option>
                  <option value="error">Error</option>
                  <option value="critical">Critical</option>
                  <option value="none">None</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Log Driver</label>
                <select v-model="form.log_driver" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option value="daily">Daily</option>
                  <option value="single">Single</option>
                  <option value="stack">Stack</option>
                  <option value="slack">Slack</option>
                  <option value="syslog">Syslog</option>
                  <option value="errorlog">ErrorLog</option>
                </select>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Log Path</label>
              <input v-model="form.log_path" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.log_rotation" type="checkbox" id="log_rotation" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="log_rotation" class="text-gray-300">Enable Log Rotation</label>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Max Log Files</label>
                <input v-model="form.max_log_files" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Max File Size (MB)</label>
                <input v-model="form.max_file_size" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Log Format</label>
              <select v-model="form.log_format" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="json">JSON</option>
                <option value="default">Default</option>
              </select>
            </div>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Log Categories</h3>
          <div class="space-y-4">
            <div class="grid grid-cols-3 gap-3">
              <div v-for="cat in logCategories" :key="cat.key" class="flex items-center gap-3">
                <input v-model="form.log_categories" :value="cat.key" type="checkbox" :id="cat.key" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                <label :for="cat.key" class="text-gray-300">{{ cat.label }}</label>
              </div>
            </div>
            <h4 class="text-md font-semibold text-white pt-2">Retention Periods (days)</h4>
            <div class="grid grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">System</label>
                <input v-model="form.retention_system" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Access</label>
                <input v-model="form.retention_access" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Error</label>
                <input v-model="form.retention_error" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Security</label>
                <input v-model="form.retention_security" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Activity</label>
                <input v-model="form.retention_activity" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Log Exports</h3>
          <div class="space-y-4">
            <div class="flex items-center gap-3">
              <input v-model="form.enable_export" type="checkbox" id="enable_export" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="enable_export" class="text-gray-300">Enable Log Exports</label>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Export Schedule</label>
                <select v-model="form.export_schedule" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option value="daily">Daily</option>
                  <option value="weekly">Weekly</option>
                  <option value="monthly">Monthly</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Export Destination</label>
                <select v-model="form.export_destination" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option value="local">Local</option>
                  <option value="s3">Amazon S3</option>
                  <option value="ftp">FTP</option>
                  <option value="email">Email</option>
                </select>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Export Path</label>
              <input v-model="form.export_path" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Export Format</label>
                <select v-model="form.export_format" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option value="json">JSON</option>
                  <option value="csv">CSV</option>
                  <option value="plain">Plain Text</option>
                </select>
              </div>
              <div class="flex items-end">
                <div class="flex items-center gap-3">
                  <input v-model="form.export_compression" type="checkbox" id="export_compression" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                  <label for="export_compression" class="text-gray-300">Compress Exports</label>
                </div>
              </div>
            </div>
            <div class="flex gap-3 pt-2">
              <Link :href="route('admin.settings.logging.view')" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">View Logs</Link>
              <button type="button" @click="clearLogs" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded-lg transition">Clear Logs</button>
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
import { useForm, Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ settings: Object })

const logCategories = [
  { key: 'system', label: 'System' },
  { key: 'access', label: 'Access' },
  { key: 'error', label: 'Error' },
  { key: 'security', label: 'Security' },
  { key: 'database', label: 'Database' },
  { key: 'api', label: 'API' },
  { key: 'streaming', label: 'Streaming' },
  { key: 'user_activity', label: 'User Activity' },
  { key: 'payment', label: 'Payment' },
  { key: 'transcoding', label: 'Transcoding' },
  { key: 'epg', label: 'EPG' },
  { key: 'server', label: 'Server Logs' },
]

const form = useForm({
  log_level: props.settings.log_level || 'info',
  log_driver: props.settings.log_driver || 'daily',
  log_path: props.settings.log_path || 'storage/logs',
  log_rotation: props.settings.log_rotation ?? true,
  max_log_files: props.settings.max_log_files || 30,
  max_file_size: props.settings.max_file_size || 100,
  log_format: props.settings.log_format || 'default',
  log_categories: props.settings.log_categories || ['system', 'access', 'error'],
  retention_system: props.settings.retention_system || 90,
  retention_access: props.settings.retention_access || 30,
  retention_error: props.settings.retention_error || 180,
  retention_security: props.settings.retention_security || 365,
  retention_activity: props.settings.retention_activity || 60,
  enable_export: props.settings.enable_export ?? false,
  export_schedule: props.settings.export_schedule || 'daily',
  export_destination: props.settings.export_destination || 'local',
  export_path: props.settings.export_path || 'storage/exports',
  export_format: props.settings.export_format || 'json',
  export_compression: props.settings.export_compression ?? true,
})

function clearLogs() {
  if (confirm('Are you sure you want to clear all logs?')) {
    router.post(route('admin.settings.logging.clear'))
  }
}
</script>
