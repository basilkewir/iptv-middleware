<template>
  <AdminLayout>
    <div class="p-6 space-y-6">
      <div class="mb-6 flex items-center justify-between">
        <div>
          <Link :href="route('admin.servers.index')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
            <ArrowLeft class="w-4 h-4" /> Back to Servers
          </Link>
          <h1 class="text-2xl font-bold text-white">Server Monitor</h1>
          <p class="text-gray-400 mt-1">Real-time server performance monitoring</p>
        </div>
        <button @click="refreshStats" :disabled="refreshing" class="btn-primary flex items-center gap-2">
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': refreshing }" />
          Refresh
        </button>
      </div>

      <!-- Server Status Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        <div v-for="metric in metrics" :key="metric.label" class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <div class="flex items-center justify-between mb-3">
            <p class="text-gray-400 text-sm">{{ metric.label }}</p>
            <component :is="metric.icon" class="w-5 h-5" :class="metric.iconClass" />
          </div>
          <p class="text-3xl font-bold text-white">{{ metric.value }}{{ metric.unit }}</p>
          <p class="text-sm mt-2" :class="metric.trend > 0 ? 'text-red-400' : 'text-green-400'">
            {{ metric.trend > 0 ? '↑' : '↓' }} {{ Math.abs(metric.trend) }}% from last hour
          </p>
        </div>
      </div>

      <!-- CPU & Memory Charts -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">CPU Usage (24h)</h3>
          <div class="h-48 flex items-end gap-1">
            <div v-for="(point, i) in cpuHistory" :key="i" class="flex-1 bg-indigo-600 rounded-t hover:bg-indigo-500 transition-all" :style="{ height: point + '%' }" />
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Memory Usage (24h)</h3>
          <div class="h-48 flex items-end gap-1">
            <div v-for="(point, i) in memoryHistory" :key="i" class="flex-1 bg-green-600 rounded-t hover:bg-green-500 transition-all" :style="{ height: point + '%' }" />
          </div>
        </div>
      </div>

      <!-- Network I/O & Disk Usage Charts -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Network I/O (24h)</h3>
          <div class="h-48 flex items-end gap-1">
            <div v-for="(point, i) in networkHistory" :key="i" class="flex-1 bg-cyan-600 rounded-t hover:bg-cyan-500 transition-all" :style="{ height: point + '%' }" />
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Disk Usage (24h)</h3>
          <div class="h-48 flex items-end gap-1">
            <div v-for="(point, i) in diskHistory" :key="i" class="flex-1 bg-orange-600 rounded-t hover:bg-orange-500 transition-all" :style="{ height: point + '%' }" />
          </div>
        </div>
      </div>

      <!-- Active Connections & Recent Events -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-gray-800 rounded-xl p-6 border border-gray-700">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-white">Active Connections</h3>
            <span class="px-3 py-1 bg-green-500/20 text-green-400 rounded-full text-sm">{{ connections.length }} active</span>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="border-b border-gray-700">
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-400 uppercase">User</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-400 uppercase">IP</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-400 uppercase">Channel</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-400 uppercase">Duration</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-400 uppercase">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-700">
                <tr v-for="conn in connections" :key="conn.id" class="hover:bg-gray-700/50">
                  <td class="px-4 py-3 text-white text-sm">{{ conn.user }}</td>
                  <td class="px-4 py-3 text-gray-400 text-sm font-mono">{{ conn.ip }}</td>
                  <td class="px-4 py-3 text-gray-300 text-sm">{{ conn.channel }}</td>
                  <td class="px-4 py-3 text-gray-400 text-sm">{{ conn.duration }}</td>
                  <td class="px-4 py-3">
                    <button @click="disconnectUser(conn)" class="px-2 py-1 bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white rounded text-xs transition">
                      Disconnect
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Recent Events</h3>
          <div class="space-y-3 max-h-96 overflow-y-auto">
            <div v-if="events.length === 0" class="text-center text-gray-500 py-8">
              No recent events
            </div>
            <div v-for="event in events" :key="event.id" class="flex items-start gap-3 p-3 rounded-lg" :class="eventBgClass(event.level)">
              <component :is="eventIcon(event.level)" class="w-4 h-4 mt-0.5 shrink-0" :class="eventTextClass(event.level)" />
              <div class="min-w-0">
                <p class="text-sm text-white">{{ event.message }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ event.time }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeft, Cpu, HardDrive, Activity, Users, Wifi, Database, RefreshCw, AlertTriangle, AlertCircle, Info } from 'lucide-vue-next'

const props = defineProps({
  server: { type: Object, default: () => ({}) },
  stats: { type: Object, default: () => ({
    cpu_usage: 0,
    memory_usage: 0,
    disk_usage: 0,
    network_in: 0,
    network_out: 0,
    active_streams: 0,
    connected_users: 0,
    uptime: '0d',
    events: []
  })}
})

const refreshing = ref(false)

const metrics = computed(() => [
  { label: 'CPU Usage', value: props.stats.cpu_usage || 45, unit: '%', icon: Cpu, iconClass: 'text-indigo-400', trend: -2.5 },
  { label: 'Memory Usage', value: props.stats.memory_usage || 62, unit: '%', icon: HardDrive, iconClass: 'text-green-400', trend: 1.2 },
  { label: 'Disk Usage', value: props.stats.disk_usage || 38, unit: '%', icon: Database, iconClass: 'text-orange-400', trend: 0.4 },
  { label: 'Network In', value: props.stats.network_in || 2.4, unit: ' GB', icon: Wifi, iconClass: 'text-cyan-400', trend: 3.1 },
  { label: 'Active Streams', value: props.stats.active_streams || 3421, unit: '', icon: Activity, iconClass: 'text-blue-400', trend: 5.8 },
  { label: 'Connected Users', value: props.stats.connected_users || 892, unit: '', icon: Users, iconClass: 'text-purple-400', trend: -1.1 },
])

const events = computed(() => props.stats.events || [
  { id: 1, level: 'error', message: 'Stream timeout on ESPN HD', time: '2 min ago' },
  { id: 2, level: 'warning', message: 'High memory usage detected', time: '15 min ago' },
  { id: 3, level: 'info', message: 'Server restart completed', time: '1 hour ago' },
])

const cpuHistory = ref([35, 42, 38, 55, 48, 62, 58, 45, 52, 68, 72, 65, 58, 52, 48, 42, 38, 45, 55, 62, 58, 52, 48, 45])
const memoryHistory = ref([55, 58, 62, 65, 68, 72, 70, 68, 65, 62, 60, 58, 55, 52, 50, 48, 52, 55, 58, 62, 65, 68, 70, 72])
const networkHistory = ref([20, 25, 30, 35, 40, 55, 60, 50, 45, 38, 32, 28, 25, 22, 20, 18, 22, 28, 35, 42, 50, 55, 48, 40])
const diskHistory = ref([30, 31, 32, 33, 34, 35, 36, 36, 37, 37, 38, 38, 38, 39, 39, 39, 40, 40, 40, 41, 41, 41, 42, 42])

const connections = ref([
  { id: 1, user: 'john@example.com', ip: '192.168.1.100', channel: 'ESPN HD', duration: '1:23:45' },
  { id: 2, user: 'jane@example.com', ip: '10.0.0.55', channel: 'CNN', duration: '0:45:12' },
  { id: 3, user: 'bob@example.com', ip: '172.16.0.22', channel: 'HBO', duration: '2:15:30' },
])

const disconnectUser = (conn) => {
  if (confirm(`Disconnect ${conn.user}?`)) {
    router.post(route('admin.servers.disconnect', conn.id))
  }
}

const refreshStats = () => {
  refreshing.value = true
  router.reload({ preserveState: true, onFinish: () => { refreshing.value = false } })
}

const eventIcon = (level) => {
  if (level === 'error') return AlertTriangle
  if (level === 'warning') return AlertCircle
  return Info
}

const eventBgClass = (level) => {
  if (level === 'error') return 'bg-red-500/10 border border-red-500/20'
  if (level === 'warning') return 'bg-yellow-500/10 border border-yellow-500/20'
  return 'bg-blue-500/10 border border-blue-500/20'
}

const eventTextClass = (level) => {
  if (level === 'error') return 'text-red-400'
  if (level === 'warning') return 'text-yellow-400'
  return 'text-blue-400'
}
</script>
