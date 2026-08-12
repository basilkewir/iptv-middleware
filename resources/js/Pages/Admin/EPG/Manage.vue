<template>
  <AdminLayout>
    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <Link :href="route('admin.epg.index')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
            <ArrowLeft class="w-4 h-4" /> Back to EPG Sources
          </Link>
          <h1 class="text-2xl font-bold text-white">EPG Management</h1>
          <p class="text-gray-400 mt-1">Monitor, update and manage Electronic Program Guide data</p>
        </div>
        <div class="flex gap-3">
          <button @click="showSchedule = !showSchedule" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition flex items-center gap-2">
            <Clock class="w-4 h-4" />
            Schedule
          </button>
          <button @click="updateEpg('all')" :disabled="updating" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition flex items-center gap-2 disabled:opacity-50">
            <RefreshCw :class="{ 'animate-spin': updating }" class="w-4 h-4" />
            {{ updating ? 'Updating...' : 'Update All' }}
          </button>
        </div>
      </div>

      <!-- Status Overview -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-indigo-500/20 rounded-lg">
              <Tv class="w-5 h-5 text-indigo-400" />
            </div>
            <div>
              <p class="text-gray-400 text-sm">Total Programs</p>
              <p class="text-2xl font-bold text-white">{{ stats?.total_programs || 0 }}</p>
            </div>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-green-500/20 rounded-lg">
              <Globe class="w-5 h-5 text-green-400" />
            </div>
            <div>
              <p class="text-gray-400 text-sm">Active Sources</p>
              <p class="text-2xl font-bold text-green-400">{{ stats?.active_sources || 0 }}</p>
            </div>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-yellow-500/20 rounded-lg">
              <Clock class="w-5 h-5 text-yellow-400" />
            </div>
            <div>
              <p class="text-gray-400 text-sm">Last Update</p>
              <p class="text-lg font-bold text-white">{{ stats?.last_update || 'Never' }}</p>
            </div>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-purple-500/20 rounded-lg">
              <CalendarDays class="w-5 h-5 text-purple-400" />
            </div>
            <div>
              <p class="text-gray-400 text-sm">Next Scheduled</p>
              <p class="text-lg font-bold text-white">{{ nextScheduled }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Update Actions -->
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        <h3 class="text-lg font-semibold text-white mb-4">Update EPG Data</h3>
        <div class="flex flex-wrap gap-4">
          <button @click="updateEpg('all')" :disabled="updating" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition flex items-center gap-2 disabled:opacity-50">
            <RefreshCw :class="{ 'animate-spin': updating }" class="w-4 h-4" />
            {{ updating ? 'Updating...' : 'Update All Sources' }}
          </button>
          <button @click="forceUpdate" :disabled="updating" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-500 text-white rounded-lg transition flex items-center gap-2 disabled:opacity-50">
            <Zap class="w-4 h-4" />
            Force Update
          </button>
          <button @click="clearExpired" class="px-4 py-2 bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white rounded-lg transition flex items-center gap-2">
            <Trash2 class="w-4 h-4" />
            Clear Expired
          </button>
        </div>
        <div v-if="updateStatus" class="mt-4 p-4 rounded-lg flex items-center gap-2" :class="updateStatus.success ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'">
          <CheckCircle v-if="updateStatus.success" class="w-4 h-4" />
          <AlertCircle v-else class="w-4 h-4" />
          {{ updateStatus.message }}
        </div>
      </div>

      <!-- Source List with Individual Update -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-700 flex items-center justify-between">
          <h3 class="text-white font-medium">EPG Sources</h3>
          <div class="flex gap-2">
            <input v-model="searchQuery" type="text" placeholder="Search sources..." class="px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm placeholder-gray-400 focus:outline-none focus:border-indigo-500 w-48" />
            <select v-model="filterStatus" class="px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm focus:outline-none focus:border-indigo-500">
              <option value="">All</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="border-b border-gray-700">
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Source</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Programs</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Last Updated</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Interval</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
              <tr v-for="source in filteredSources" :key="source.id" class="hover:bg-gray-700/50 transition-colors">
                <td class="px-6 py-4">
                  <div>
                    <p class="text-white font-medium">{{ source.name }}</p>
                    <p class="text-gray-400 text-xs truncate max-w-xs">{{ source.url }}</p>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <span class="px-2 py-1 text-xs rounded-full" :class="sourceTypeBadge(source)">
                    {{ source.source_type || 'XMLTV' }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full" :class="source.is_active ? 'bg-green-400' : 'bg-gray-500'" />
                    <span class="text-sm" :class="source.is_active ? 'text-green-400' : 'text-gray-400'">
                      {{ source.is_active ? 'Active' : 'Inactive' }}
                    </span>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <span class="text-white font-medium">{{ source.program_count || 0 }}</span>
                </td>
                <td class="px-6 py-4">
                  <span class="text-gray-400 text-sm">{{ source.last_fetched_at ? formatRelativeTime(source.last_fetched_at) : 'Never' }}</span>
                </td>
                <td class="px-6 py-4">
                  <span class="text-gray-400 text-sm">Every {{ source.fetch_interval || 24 }}h</span>
                </td>
                <td class="px-6 py-4">
                  <div class="flex items-center gap-2">
                    <button @click="updateSource(source)" :disabled="updatingSource === source.id" class="px-3 py-1.5 bg-indigo-600/20 hover:bg-indigo-600 text-indigo-400 hover:text-white text-sm rounded-lg transition flex items-center gap-1.5 disabled:opacity-50">
                      <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': updatingSource === source.id }" />
                      {{ updatingSource === source.id ? 'Updating...' : 'Update' }}
                    </button>
                    <button @click="toggleSource(source)" class="p-1.5 rounded-lg transition" :class="source.is_active ? 'bg-yellow-600/20 hover:bg-yellow-600 text-yellow-400 hover:text-white' : 'bg-green-600/20 hover:bg-green-600 text-green-400 hover:text-white'">
                      <Power v-if="source.is_active" class="w-4 h-4" />
                      <PowerOff v-else class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredSources.length === 0">
                <td colspan="7" class="px-6 py-8 text-center text-gray-400">
                  No sources found
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Schedule Panel -->
      <div v-if="showSchedule" class="bg-gray-800 rounded-xl border border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-white mb-4">Update Schedule</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <h4 class="text-sm font-medium text-gray-300 mb-3">Upcoming Updates</h4>
            <div class="space-y-2">
              <div v-for="(item, i) in scheduleItems" :key="i" class="flex items-center justify-between bg-gray-700/50 rounded-lg p-3">
                <div class="flex items-center gap-3">
                  <div class="p-1.5 bg-indigo-500/20 rounded">
                    <Clock class="w-4 h-4 text-indigo-400" />
                  </div>
                  <div>
                    <p class="text-white text-sm">{{ item.source }}</p>
                    <p class="text-gray-400 text-xs">{{ item.time }}</p>
                  </div>
                </div>
                <span class="text-xs px-2 py-1 rounded-full" :class="item.urgent ? 'bg-yellow-500/20 text-yellow-400' : 'bg-gray-600/50 text-gray-400'">
                  {{ item.urgent ? 'Due Soon' : 'Scheduled' }}
                </span>
              </div>
            </div>
          </div>
          <div>
            <h4 class="text-sm font-medium text-gray-300 mb-3">Statistics</h4>
            <div class="space-y-3">
              <div class="bg-gray-700/50 rounded-lg p-3">
                <div class="flex justify-between text-sm mb-1">
                  <span class="text-gray-400">Data Freshness</span>
                  <span class="text-green-400">{{ freshness }}%</span>
                </div>
                <div class="w-full bg-gray-700 rounded-full h-2">
                  <div class="h-2 rounded-full bg-green-500" :style="{ width: freshness + '%' }" />
                </div>
              </div>
              <div class="bg-gray-700/50 rounded-lg p-3">
                <div class="flex justify-between text-sm">
                  <span class="text-gray-400">Total Channels Covered</span>
                  <span class="text-white">{{ stats?.channels_covered || 0 }}</span>
                </div>
              </div>
              <div class="bg-gray-700/50 rounded-lg p-3">
                <div class="flex justify-between text-sm">
                  <span class="text-gray-400">Days of Data Available</span>
                  <span class="text-white">{{ stats?.days_available || 0 }}</span>
                </div>
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
import {
  ArrowLeft, RefreshCw, Zap, Tv, Globe, Clock, Trash2, CheckCircle,
  AlertCircle, CalendarDays, Search, Power, PowerOff
} from 'lucide-vue-next'

const props = defineProps({
  sources: { type: Array, default: () => [] },
  stats: { type: Object, default: () => ({}) }
})

const updating = ref(false)
const updatingSource = ref(null)
const updateStatus = ref(null)
const showSchedule = ref(false)
const searchQuery = ref('')
const filterStatus = ref('')

const filteredSources = computed(() => {
  let result = props.sources || []
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(s => s.name.toLowerCase().includes(q) || s.url?.toLowerCase().includes(q))
  }
  if (filterStatus.value) {
    result = result.filter(s => filterStatus.value === 'active' ? s.is_active : !s.is_active)
  }
  return result
})

const nextScheduled = computed(() => {
  const activeSources = (props.sources || []).filter(s => s.is_active && s.fetch_interval)
  if (!activeSources.length) return 'N/A'
  const next = activeSources.reduce((closest, s) => {
    const interval = (s.fetch_interval || 24) * 3600000
    const lastFetch = s.last_fetched_at ? new Date(s.last_fetched_at).getTime() : 0
    const nextFetch = lastFetch + interval
    return nextFetch < closest ? nextFetch : closest
  }, Infinity)
  if (next === Infinity) return 'N/A'
  const diff = next - Date.now()
  if (diff <= 0) return 'Now'
  const hours = Math.floor(diff / 3600000)
  if (hours < 1) return 'Soon'
  if (hours < 24) return `In ${hours}h`
  return `In ${Math.floor(hours / 24)}d`
})

const freshness = computed(() => {
  const sources = (props.sources || []).filter(s => s.is_active)
  if (!sources.length) return 0
  const fresh = sources.filter(s => {
    if (!s.last_fetched_at) return false
    const hoursSince = (Date.now() - new Date(s.last_fetched_at).getTime()) / 3600000
    return hoursSince < (s.fetch_interval || 24) * 2
  })
  return Math.round((fresh.length / sources.length) * 100)
})

const scheduleItems = computed(() => {
  return (props.sources || []).filter(s => s.is_active).slice(0, 5).map(s => ({
    source: s.name,
    time: s.fetch_interval ? `Every ${s.fetch_interval}h` : 'Manual',
    urgent: s.last_fetched_at ? (Date.now() - new Date(s.last_fetched_at).getTime()) / 3600000 > (s.fetch_interval || 24) : false
  }))
})

const sourceTypeBadge = (source) => ({
  'bg-indigo-500/20 text-indigo-400': source.source_type === 'XMLTV' || !source.source_type,
  'bg-green-500/20 text-green-400': source.source_type === 'XTREAM',
  'bg-purple-500/20 text-purple-400': source.source_type === 'M3U',
  'bg-yellow-500/20 text-yellow-400': source.source_type === 'JSON',
})

const formatRelativeTime = (date) => {
  if (!date) return 'Never'
  const diff = Date.now() - new Date(date).getTime()
  const hours = Math.floor(diff / 3600000)
  if (hours < 1) return 'Just now'
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  return `${days}d ago`
}

const updateEpg = (sourceId) => {
  updating.value = true
  updateStatus.value = null
  router.post(route('admin.epg.update.trigger'), { source_id: sourceId }, {
    onFinish: () => { updating.value = false },
    onSuccess: () => { updateStatus.value = { success: true, message: 'EPG update triggered successfully!' } },
    onError: () => { updateStatus.value = { success: false, message: 'Failed to trigger EPG update.' } },
  })
}

const updateSource = (source) => {
  updatingSource.value = source.id
  router.post(route('admin.epg.update.trigger'), { source_id: source.id }, {
    onFinish: () => { updatingSource.value = null }
  })
}

const toggleSource = (source) => {
  router.put(route('admin.epg.update', source.id), { ...source, is_active: !source.is_active })
}

const forceUpdate = () => {
  if (confirm('Force update all EPG sources? This may take a while.')) {
    updateEpg('all')
  }
}

const clearExpired = () => {
  if (confirm('Clear all expired EPG data? This cannot be undone.')) {
    router.post(route('admin.epg.clear-expired'))
  }
}
</script>
