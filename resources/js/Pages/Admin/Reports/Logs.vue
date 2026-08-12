<template>
  <AdminLayout>
    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-white">System Logs</h1>
          <p class="text-gray-400 mt-1">View and filter platform log entries</p>
        </div>
        <button @click="confirmClear" class="px-4 py-2 bg-red-600/20 hover:bg-red-600/30 text-red-400 rounded-lg transition flex items-center gap-2">
          <Trash2 class="w-4 h-4" />
          Clear Logs
        </button>
      </div>

      <!-- Filters -->
      <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
        <div class="flex flex-wrap gap-4 items-center">
          <div class="flex-1 min-w-[200px]">
            <div class="relative">
              <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
              <input v-model="search" type="text" placeholder="Search logs..." class="w-full pl-10 pr-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500" />
            </div>
          </div>
          <select v-model="filterType" class="px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            <option value="">All Types</option>
            <option value="system">System</option>
            <option value="streaming">Streaming</option>
            <option value="error">Error</option>
          </select>
          <select v-model="filterLevel" class="px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            <option value="">All Levels</option>
            <option value="debug">Debug</option>
            <option value="info">Info</option>
            <option value="warning">Warning</option>
            <option value="error">Error</option>
            <option value="critical">Critical</option>
          </select>
          <div class="flex gap-2 items-center">
            <input v-model="dateFrom" type="date" class="px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm focus:outline-none focus:border-indigo-500" />
            <span class="text-gray-400">to</span>
            <input v-model="dateTo" type="date" class="px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm focus:outline-none focus:border-indigo-500" />
          </div>
        </div>
      </div>

      <!-- Log Table -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
        <table class="w-full">
          <thead>
            <tr class="border-b border-gray-700">
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Timestamp</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Type</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Level</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Message</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">User</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-700">
            <tr v-for="log in logs?.data || []" :key="log.id" class="hover:bg-gray-700/50">
              <td class="px-6 py-4 whitespace-nowrap text-gray-400 text-sm font-mono">{{ formatTimestamp(log.created_at) }}</td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 text-xs rounded-full" :class="typeClass(log.type)">
                  {{ log.type }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 text-xs rounded-full" :class="levelClass(log.level)">
                  {{ log.level }}
                </span>
              </td>
              <td class="px-6 py-4 text-gray-300 text-sm max-w-md truncate">{{ log.message }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-400 text-sm">{{ log.user?.username || 'System' }}</td>
            </tr>
          </tbody>
        </table>
        <div v-if="!logs?.data?.length" class="p-8 text-center text-gray-500">
          No log entries found
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="logs?.links" class="flex items-center justify-between">
        <p class="text-gray-400 text-sm">Showing {{ logs.from }} to {{ logs.to }} of {{ logs.total }} entries</p>
        <div class="flex gap-2">
          <Link v-for="page in logs.links" :key="page.label" :href="page.url || '#'" class="px-3 py-1 rounded-lg text-sm"
            :class="page.active ? 'bg-indigo-600 text-white' : 'bg-gray-700 text-gray-400 hover:bg-gray-600'" preserve-scroll>
            {{ page.label }}
          </Link>
        </div>
      </div>

      <!-- Clear Confirmation Modal -->
      <div v-if="showClearModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 w-full max-w-md">
          <h3 class="text-lg font-semibold text-white mb-2">Clear Logs</h3>
          <p class="text-gray-400">Are you sure you want to clear all log entries? This action cannot be undone.</p>
          <div class="flex justify-end gap-3 mt-6">
            <button @click="showClearModal = false" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">Cancel</button>
            <button @click="clearLogs" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded-lg transition">Clear All Logs</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Search, Trash2 } from 'lucide-vue-next'

const props = defineProps({
  logs: { type: Object, default: () => ({ data: [], links: [], from: 0, to: 0, total: 0 }) },
})

const search = ref('')
const filterType = ref('')
const filterLevel = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const showClearModal = ref(false)

const typeClass = (type) => {
  const map = {
    system: 'bg-blue-500/20 text-blue-400',
    streaming: 'bg-purple-500/20 text-purple-400',
    error: 'bg-red-500/20 text-red-400',
  }
  return map[type] || 'bg-gray-500/20 text-gray-400'
}

const levelClass = (level) => {
  const map = {
    debug: 'bg-gray-500/20 text-gray-400',
    info: 'bg-blue-500/20 text-blue-400',
    warning: 'bg-yellow-500/20 text-yellow-400',
    error: 'bg-red-500/20 text-red-400',
    critical: 'bg-red-600/30 text-red-300',
  }
  return map[level] || 'bg-gray-500/20 text-gray-400'
}

const formatTimestamp = (d) => {
  if (!d) return ''
  return new Date(d).toLocaleString()
}

const confirmClear = () => {
  showClearModal.value = true
}

const clearLogs = () => {
  router.post(route('admin.logs.clear'), {}, {
    preserveScroll: true,
    onSuccess: () => { showClearModal.value = false },
  })
}
</script>
