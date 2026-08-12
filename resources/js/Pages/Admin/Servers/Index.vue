<template>
  <AdminLayout>
    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-white">Server Management</h1>
          <p class="text-gray-400 mt-1">Monitor and manage streaming servers</p>
        </div>
        <div class="flex gap-3">
          <button @click="refreshStats" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition flex items-center gap-2">
            <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': refreshing }" />
            Refresh
          </button>
          <button @click="openAddModal" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition flex items-center gap-2">
            <Plus class="w-4 h-4" />
            Add Server
          </button>
        </div>
      </div>

      <!-- Overview Stats -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-green-500/20 rounded-lg">
              <CheckCircle class="w-5 h-5 text-green-400" />
            </div>
            <div>
              <p class="text-gray-400 text-sm">Online</p>
              <p class="text-2xl font-bold text-green-400">{{ onlineCount }}</p>
            </div>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-yellow-500/20 rounded-lg">
              <AlertTriangle class="w-5 h-5 text-yellow-400" />
            </div>
            <div>
              <p class="text-gray-400 text-sm">Maintenance</p>
              <p class="text-2xl font-bold text-yellow-400">{{ maintenanceCount }}</p>
            </div>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-red-500/20 rounded-lg">
              <XCircle class="w-5 h-5 text-red-400" />
            </div>
            <div>
              <p class="text-gray-400 text-sm">Offline</p>
              <p class="text-2xl font-bold text-red-400">{{ offlineCount }}</p>
            </div>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-indigo-500/20 rounded-lg">
              <Activity class="w-5 h-5 text-indigo-400" />
            </div>
            <div>
              <p class="text-gray-400 text-sm">Total Connections</p>
              <p class="text-2xl font-bold text-white">{{ totalConnections }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Server Cards -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div v-for="server in servers || []" :key="server.id" class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden hover:border-gray-600 transition">
          <!-- Server Header -->
          <div class="p-6 border-b border-gray-700">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-4">
                <div class="p-3 rounded-xl" :class="serverStatusBg(server)">
                  <Server class="w-7 h-7" :class="serverStatusColor(server)" />
                </div>
                <div>
                  <h3 class="text-white font-semibold text-lg">{{ server.name }}</h3>
                  <p class="text-gray-400 text-sm">{{ server.ip_address }}:{{ server.port }}</p>
                  <p v-if="server.location" class="text-gray-500 text-xs mt-0.5 flex items-center gap-1">
                    <MapPin class="w-3 h-3" /> {{ server.location }}
                  </p>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <span class="px-3 py-1 text-xs rounded-full font-medium" :class="serverStatusClass(server)">
                  {{ server.status }}
                </span>
                <button @click="toggleServerStatus(server)" class="p-1.5 text-gray-400 hover:text-white transition rounded-lg hover:bg-gray-700" title="Toggle Status">
                  <RefreshCw class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>

          <!-- Resource Usage -->
          <div class="p-6 space-y-4">
            <div>
              <div class="flex justify-between text-sm mb-1.5">
                <span class="text-gray-400 flex items-center gap-1.5">
                  <Cpu class="w-3.5 h-3.5" /> CPU
                </span>
                <span class="font-medium" :class="usageTextColor(server.cpu_usage)">{{ server.cpu_usage || 0 }}%</span>
              </div>
              <div class="w-full bg-gray-700 rounded-full h-2">
                <div class="h-2 rounded-full transition-all" :class="usageBarColor(server.cpu_usage)" :style="{ width: (server.cpu_usage || 0) + '%' }" />
              </div>
            </div>
            <div>
              <div class="flex justify-between text-sm mb-1.5">
                <span class="text-gray-400 flex items-center gap-1.5">
                  <HardDrive class="w-3.5 h-3.5" /> Memory
                </span>
                <span class="font-medium" :class="usageTextColor(server.memory_usage)">{{ server.memory_usage || 0 }}%</span>
              </div>
              <div class="w-full bg-gray-700 rounded-full h-2">
                <div class="h-2 rounded-full transition-all" :class="usageBarColor(server.memory_usage)" :style="{ width: (server.memory_usage || 0) + '%' }" />
              </div>
            </div>
            <div>
              <div class="flex justify-between text-sm mb-1.5">
                <span class="text-gray-400 flex items-center gap-1.5">
                  <Wifi class="w-3.5 h-3.5" /> Network
                </span>
                <span class="font-medium" :class="usageTextColor(server.network_usage || 0)">{{ server.network_usage || 0 }}%</span>
              </div>
              <div class="w-full bg-gray-700 rounded-full h-2">
                <div class="h-2 rounded-full transition-all" :class="usageBarColor(server.network_usage || 0)" :style="{ width: (server.network_usage || 0) + '%' }" />
              </div>
            </div>

            <!-- Connection Bar -->
            <div class="bg-gray-700/50 rounded-lg p-3">
              <div class="flex justify-between text-sm mb-2">
                <span class="text-gray-400">Connections</span>
                <span class="text-white font-medium">{{ server.current_connections || 0 }} / {{ server.max_connections || 0 }}</span>
              </div>
              <div class="w-full bg-gray-700 rounded-full h-2">
                <div
                  class="h-2 rounded-full transition-all"
                  :class="connectionBarColor(server)"
                  :style="{ width: connectionPercentage(server) + '%' }"
                />
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="px-6 py-4 border-t border-gray-700 flex items-center justify-between">
            <div class="flex gap-2">
              <button @click="testConnection(server)" class="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded-lg transition flex items-center gap-1.5">
                <Zap class="w-3.5 h-3.5" /> Test
              </button>
              <Link :href="route('admin.servers.monitor', server.id)" class="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded-lg transition flex items-center gap-1.5">
                <BarChart3 class="w-3.5 h-3.5" /> Monitor
              </Link>
            </div>
            <div class="flex gap-2">
              <button @click="editServer(server)" class="p-2 bg-blue-600/20 hover:bg-blue-600 text-blue-400 hover:text-white rounded-lg transition" title="Edit">
                <Edit2 class="w-4 h-4" />
              </button>
              <button @click="deleteServer(server)" class="p-2 bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white rounded-lg transition" title="Delete">
                <Trash2 class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="!servers?.length" class="bg-gray-800 rounded-xl border border-gray-700 p-12 text-center">
        <Server class="w-16 h-16 mx-auto mb-4 text-gray-600" />
        <h3 class="text-lg font-medium text-white mb-2">No servers configured</h3>
        <p class="text-gray-400 mb-6">Add your first streaming server to get started</p>
        <button @click="openAddModal" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition">
          Add Server
        </button>
      </div>

      <!-- Add/Edit Modal -->
      <div v-if="showModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
        <div class="bg-gray-800 rounded-xl p-6 w-full max-w-lg border border-gray-700 shadow-2xl">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-semibold text-white">{{ editingServer ? 'Edit' : 'Add' }} Server</h2>
            <button @click="showModal = false" class="text-gray-400 hover:text-white transition">
              <X class="w-5 h-5" />
            </button>
          </div>
          <form @submit.prevent="saveServer" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Server Name</label>
              <input v-model="serverForm.name" type="text" placeholder="e.g. US-East-01" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">IP Address</label>
                <input v-model="serverForm.ip_address" type="text" placeholder="192.168.1.1" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Port</label>
                <input v-model="serverForm.port" type="number" placeholder="8080" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Location</label>
                <select v-model="serverForm.location" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option value="">Select Location</option>
                  <option value="US East">US East</option>
                  <option value="US West">US West</option>
                  <option value="Europe">Europe</option>
                  <option value="Asia">Asia</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                <select v-model="serverForm.status" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option value="active">Active</option>
                  <option value="maintenance">Maintenance</option>
                  <option value="offline">Offline</option>
                </select>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Max Connections</label>
                <input v-model="serverForm.max_connections" type="number" placeholder="1000" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Bandwidth (Mbps)</label>
                <input v-model="serverForm.bandwidth" type="number" placeholder="1000" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Notes</label>
              <textarea v-model="serverForm.notes" rows="2" placeholder="Optional notes about this server" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500 resize-none" />
            </div>
            <div class="flex justify-end gap-3 mt-6">
              <button type="button" @click="showModal = false" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">Cancel</button>
              <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition flex items-center gap-2">
                <Save class="w-4 h-4" />
                {{ editingServer ? 'Update' : 'Add' }} Server
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  Plus, Server, CheckCircle, XCircle, AlertTriangle, Activity, RefreshCw,
  Cpu, HardDrive, Wifi, Zap, BarChart3, Edit2, Trash2, X, Save, MapPin
} from 'lucide-vue-next'

const props = defineProps({ servers: { type: Array, default: () => [] } })

const showModal = ref(false)
const editingServer = ref(null)
const refreshing = ref(false)
const serverForm = ref({
  name: '', ip_address: '', port: 8080, max_connections: 1000,
  location: '', status: 'active', bandwidth: 1000, notes: ''
})

const onlineCount = computed(() => (props.servers || []).filter(s => s.status === 'active').length)
const maintenanceCount = computed(() => (props.servers || []).filter(s => s.status === 'maintenance').length)
const offlineCount = computed(() => (props.servers || []).filter(s => s.status === 'offline').length)
const totalConnections = computed(() => (props.servers || []).reduce((sum, s) => sum + (s.current_connections || 0), 0))

const serverStatusBg = (server) => ({
  'bg-green-500/20': server.status === 'active',
  'bg-yellow-500/20': server.status === 'maintenance',
  'bg-red-500/20': server.status === 'offline',
})

const serverStatusColor = (server) => ({
  'text-green-400': server.status === 'active',
  'text-yellow-400': server.status === 'maintenance',
  'text-red-400': server.status === 'offline',
})

const serverStatusClass = (server) => ({
  'bg-green-500/20 text-green-400': server.status === 'active',
  'bg-yellow-500/20 text-yellow-400': server.status === 'maintenance',
  'bg-red-500/20 text-red-400': server.status === 'offline',
})

const usageBarColor = (usage) => {
  if (usage > 80) return 'bg-red-500'
  if (usage > 60) return 'bg-yellow-500'
  return 'bg-green-500'
}

const usageTextColor = (usage) => {
  if (usage > 80) return 'text-red-400'
  if (usage > 60) return 'text-yellow-400'
  return 'text-green-400'
}

const connectionPercentage = (server) => {
  if (!server.max_connections) return 0
  return Math.min(((server.current_connections || 0) / server.max_connections) * 100, 100)
}

const connectionBarColor = (server) => {
  const pct = connectionPercentage(server)
  if (pct > 80) return 'bg-red-500'
  if (pct > 60) return 'bg-yellow-500'
  return 'bg-green-500'
}

const openAddModal = () => {
  editingServer.value = null
  serverForm.value = { name: '', ip_address: '', port: 8080, max_connections: 1000, location: '', status: 'active', bandwidth: 1000, notes: '' }
  showModal.value = true
}

const editServer = (server) => {
  editingServer.value = server
  serverForm.value = {
    name: server.name, ip_address: server.ip_address, port: server.port,
    max_connections: server.max_connections, location: server.location || '',
    status: server.status, bandwidth: server.bandwidth || 1000, notes: server.notes || ''
  }
  showModal.value = true
}

const saveServer = () => {
  if (editingServer.value) {
    router.put(route('admin.servers.update', editingServer.value.id), serverForm.value)
  } else {
    router.post(route('admin.servers.store'), serverForm.value)
  }
  showModal.value = false
}

const deleteServer = (server) => {
  if (confirm(`Delete server "${server.name}"? This will remove all associated data.`)) {
    router.delete(route('admin.servers.destroy', server.id))
  }
}

const toggleServerStatus = (server) => {
  const newStatus = server.status === 'active' ? 'maintenance' : 'active'
  router.post(route('admin.servers.update', server.id), { ...server, status: newStatus })
}

const testConnection = (server) => {
  router.post(route('admin.servers.test', server.id))
}

const refreshStats = () => {
  refreshing.value = true
  router.reload({ only: ['servers'], onFinish: () => { refreshing.value = false } })
}
</script>
