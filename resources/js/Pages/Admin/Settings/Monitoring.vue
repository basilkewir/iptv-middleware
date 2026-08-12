<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto space-y-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">Monitoring Settings</h1>
        <p class="text-gray-400 mt-1">Configure system monitoring, alerts, and health checks</p>
      </div>

      <form @submit.prevent="form.put(route('admin.settings.monitoring.update'))" class="space-y-6">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">System Monitoring</h3>
          <div class="space-y-4">
            <div class="flex items-center gap-3">
              <input v-model="form.enable_monitoring" type="checkbox" id="enable_monitoring" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="enable_monitoring" class="text-gray-300">Enable System Monitoring</label>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Monitoring Interval (seconds)</label>
              <input v-model="form.monitoring_interval" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Metrics to Collect</label>
              <div class="grid grid-cols-4 gap-3 mt-2">
                <div v-for="m in metricsOptions" :key="m.key" class="flex items-center gap-3">
                  <input v-model="form.metrics" :value="m.key" type="checkbox" :id="m.key" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                  <label :for="m.key" class="text-gray-300">{{ m.label }}</label>
                </div>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Metrics Retention (days)</label>
              <input v-model="form.metrics_retention" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Alert Configuration</h3>
          <div class="space-y-4">
            <div class="grid grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">CPU Threshold (%)</label>
                <input v-model="form.cpu_threshold" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Memory Threshold (%)</label>
                <input v-model="form.memory_threshold" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Disk Threshold (%)</label>
                <input v-model="form.disk_threshold" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
            <div class="grid grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Load Threshold</label>
                <input v-model="form.load_threshold" type="number" step="0.1" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Response Time Threshold (ms)</label>
                <input v-model="form.response_time_threshold" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Error Rate Threshold (%)</label>
                <input v-model="form.error_rate_threshold" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Notification Channels</label>
              <div class="flex gap-4 mt-2">
                <div v-for="ch in channelOptions" :key="ch.key" class="flex items-center gap-3">
                  <input v-model="form.notification_channels" :value="ch.key" type="checkbox" :id="ch.key" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                  <label :for="ch.key" class="text-gray-300">{{ ch.label }}</label>
                </div>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Alert Cooldown (seconds)</label>
              <input v-model="form.alert_cooldown" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Health Checks</h3>
          <div class="space-y-4">
            <div class="flex items-center gap-3">
              <input v-model="form.enable_health_checks" type="checkbox" id="enable_health_checks" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="enable_health_checks" class="text-gray-300">Enable Health Checks</label>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Health Check Interval (seconds)</label>
                <input v-model="form.health_check_interval" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Health Timeout (seconds)</label>
                <input v-model="form.health_timeout" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Failure Threshold</label>
                <input v-model="form.health_failure_threshold" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Success Threshold</label>
                <input v-model="form.health_success_threshold" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.auto_restart" type="checkbox" id="auto_restart" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="auto_restart" class="text-gray-300">Auto Restart on Failure</label>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Components to Check</label>
              <div class="grid grid-cols-3 gap-3 mt-2">
                <div v-for="comp in componentOptions" :key="comp.key" class="flex items-center gap-3">
                  <input v-model="form.health_check_components" :value="comp.key" type="checkbox" :id="comp.key" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                  <label :for="comp.key" class="text-gray-300">{{ comp.label }}</label>
                </div>
              </div>
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

const metricsOptions = [
  { key: 'cpu', label: 'CPU' },
  { key: 'memory', label: 'Memory' },
  { key: 'disk', label: 'Disk' },
  { key: 'network', label: 'Network' },
  { key: 'processes', label: 'Processes' },
  { key: 'load', label: 'Load' },
  { key: 'io_wait', label: 'IO Wait' },
]

const channelOptions = [
  { key: 'email', label: 'Email' },
  { key: 'sms', label: 'SMS' },
  { key: 'dashboard', label: 'Dashboard' },
  { key: 'webhook', label: 'Webhook' },
]

const componentOptions = [
  { key: 'database', label: 'Database' },
  { key: 'cache', label: 'Cache' },
  { key: 'queue', label: 'Queue' },
  { key: 'streaming', label: 'Streaming' },
  { key: 'api', label: 'API' },
  { key: 'webserver', label: 'Web Server' },
]

const form = useForm({
  enable_monitoring: props.settings.enable_monitoring ?? true,
  monitoring_interval: props.settings.monitoring_interval || 60,
  metrics: props.settings.metrics || ['cpu', 'memory', 'disk', 'network', 'processes', 'load'],
  metrics_retention: props.settings.metrics_retention || 30,
  cpu_threshold: props.settings.cpu_threshold || 80,
  memory_threshold: props.settings.memory_threshold || 85,
  disk_threshold: props.settings.disk_threshold || 90,
  load_threshold: props.settings.load_threshold || 5.0,
  response_time_threshold: props.settings.response_time_threshold || 5000,
  error_rate_threshold: props.settings.error_rate_threshold || 5,
  notification_channels: props.settings.notification_channels || ['email', 'dashboard'],
  alert_cooldown: props.settings.alert_cooldown || 300,
  enable_health_checks: props.settings.enable_health_checks ?? true,
  health_check_interval: props.settings.health_check_interval || 300,
  health_failure_threshold: props.settings.health_failure_threshold || 3,
  health_success_threshold: props.settings.health_success_threshold || 2,
  health_timeout: props.settings.health_timeout || 10,
  auto_restart: props.settings.auto_restart ?? false,
  health_check_components: props.settings.health_check_components || ['database', 'cache', 'queue', 'streaming'],
})
</script>
