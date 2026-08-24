<template>
  <AdminLayout>
    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-white">Channels</h1>
          <p class="text-gray-400 mt-1">Manage live TV channels</p>
        </div>
        <div class="flex gap-3">
          <button @click="scanAllQualities" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-500 text-white rounded-lg transition flex items-center gap-2">
            <ScanLine class="w-4 h-4" /> Scan All Qualities
          </button>
          <Link :href="route('admin.channels.import')" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition flex items-center gap-2">
            <Upload class="w-4 h-4" /> Bulk Import
          </Link>
          <Link :href="route('admin.channels.create')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition flex items-center gap-2">
            <Plus class="w-4 h-4" /> Add Channel
          </Link>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
        <div class="flex flex-wrap gap-4 items-center">
          <div class="flex-1 min-w-[200px]">
            <div class="relative">
              <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
              <input v-model="search" type="text" placeholder="Search channels..." class="w-full pl-10 pr-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500" />
            </div>
          </div>
          <select v-model="filterType" class="px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            <option value="">All Types</option>
            <option value="hls">HLS</option>
            <option value="rtmp">RTMP</option>
            <option value="rtsp">RTSP</option>
            <option value="udp">UDP</option>
            <option value="dash">DASH</option>
          </select>
          <select v-model="filterCategory" class="px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            <option value="">All Categories</option>
            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
          </select>
          <select v-model="filterStatus" class="px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            <option value="">All Status</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
          <select v-model="filterQuality" class="px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            <option value="">All Qualities</option>
            <option value="4k">4K</option>
            <option value="fhd">FHD</option>
            <option value="hd">HD</option>
            <option value="sd">SD</option>
            <option value="low">Low</option>
            <option value="unknown">Unknown</option>
          </select>
          <div class="flex gap-1 bg-gray-700 rounded-lg p-1">
            <button @click="viewMode = 'grid'" class="p-2 rounded transition" :class="viewMode === 'grid' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white'">
              <LayoutGrid class="w-4 h-4" />
            </button>
            <button @click="viewMode = 'list'" class="p-2 rounded transition" :class="viewMode === 'list' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white'">
              <List class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      <!-- Bulk Actions -->
      <div v-if="selectedChannels.length > 0" class="bg-indigo-600/20 border border-indigo-500/30 rounded-xl p-4 flex items-center justify-between">
        <div class="flex items-center gap-4">
          <span class="text-indigo-400 text-sm font-medium">{{ selectedChannels.length }} channel(s) selected</span>
          <button @click="bulkToggleStatus(true)" class="px-3 py-1.5 bg-green-600/20 text-green-400 rounded-lg text-sm hover:bg-green-600/30">Activate</button>
          <button @click="bulkToggleStatus(false)" class="px-3 py-1.5 bg-yellow-600/20 text-yellow-400 rounded-lg text-sm hover:bg-yellow-600/30">Deactivate</button>
          <button @click="bulkDelete" class="px-3 py-1.5 bg-red-600/20 text-red-400 rounded-lg text-sm hover:bg-red-600/30">Delete</button>
        </div>
        <button @click="selectedChannels = []" class="text-gray-400 hover:text-white text-sm">Clear</button>
      </div>

      <!-- Grid View -->
      <div v-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="channel in filteredChannels" :key="channel.id" class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden hover:border-gray-600 transition">
          <div class="h-32 bg-gray-700 flex items-center justify-center relative">
            <img v-if="channel.logo_url" :src="channel.logo_url" :alt="channel.name" class="h-full object-contain" />
            <Tv v-else class="w-12 h-12 text-gray-500" />
            <input type="checkbox" :value="channel.id" v-model="selectedChannels" class="absolute top-2 left-2 w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600" />
            <span class="absolute top-2 right-2 px-2 py-0.5 text-xs rounded bg-black/60 text-white uppercase">{{ channel.stream_type }}</span>
          </div>
          <div class="p-4">
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2 min-w-0">
                <h3 class="text-white font-semibold truncate">{{ channel.name }}</h3>
                <QualityBadge v-if="channel.quality_level" :quality="channel.quality_level" :showLabel="true" size="sm" />
              </div>
              <div class="flex items-center gap-2">
                <span class="px-2 py-1 text-xs rounded-full shrink-0" :class="getSourceStatusClass(channel)">
                  {{ getSourceStatusText(channel) }}
                </span>
                <select
                  v-if="channel.backup_url_1 || channel.backup_url_2"
                  :value="channel.active_source_index ?? 0"
                  @change="switchSource(channel, parseInt($event.target.value))"
                  :disabled="switchingSource[channel.id]"
                  class="px-1 py-0.5 text-xs bg-gray-700 border border-gray-600 rounded text-gray-300 focus:outline-none focus:border-indigo-500 max-w-[110px]"
                  title="Switch source"
                >
                  <option :value="0">Primary</option>
                  <option v-if="channel.backup_url_1" :value="1">Backup 1</option>
                  <option v-if="channel.backup_url_2" :value="2">Backup 2</option>
                </select>
                <span class="px-2 py-1 text-xs rounded-full shrink-0" :class="channel.is_active ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'">
                  {{ channel.is_active ? 'Active' : 'Inactive' }}
                </span>
              </div>
            </div>
            <p class="text-gray-400 text-sm mb-1">{{ channel.categories?.[0]?.name || 'Uncategorized' }}</p>
            <div class="flex flex-wrap gap-1 mb-3">
              <span v-for="b in channel.bouquets?.slice(0, 2)" :key="b.id" class="px-1.5 py-0.5 text-xs bg-indigo-600/20 text-indigo-400 rounded">{{ b.name }}</span>
            </div>
            <div class="flex items-center gap-2">
              <button @click="checkSource(channel)" :disabled="checkingChannels[channel.id]" class="px-3 py-2 bg-cyan-600/20 hover:bg-cyan-600 text-cyan-400 hover:text-white rounded-lg text-sm transition disabled:opacity-50" :title="'Check source health'">
                <Activity class="w-4 h-4" :class="checkingChannels[channel.id] ? 'animate-spin' : ''" />
              </button>
              <button @click="refreshSource(channel)" :disabled="refreshingChannels[channel.id]" class="px-3 py-2 bg-blue-600/20 hover:bg-blue-600 text-blue-400 hover:text-white rounded-lg text-sm transition disabled:opacity-50" :title="'Refresh source'">
                <RefreshCw class="w-4 h-4" :class="refreshingChannels[channel.id] ? 'animate-spin' : ''" />
              </button>
              <button @click="stopSource(channel)" :disabled="stoppingChannels[channel.id]" class="px-3 py-2 bg-orange-600/20 hover:bg-orange-600 text-orange-400 hover:text-white rounded-lg text-sm transition disabled:opacity-50" :title="'Stop channel'">
                <Square class="w-4 h-4" />
              </button>
              <button @click="testChannel(channel)" class="px-3 py-2 bg-blue-600/20 hover:bg-blue-600 text-blue-400 hover:text-white rounded-lg text-sm transition">Test</button>
              <Link :href="route('admin.channels.edit', channel.id)" class="flex-1 px-3 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg text-sm transition text-center">Edit</Link>
              <button @click="confirmDelete(channel)" class="px-3 py-2 bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white rounded-lg text-sm transition">Delete</button>
            </div>
          </div>
        </div>
      </div>

      <!-- List View -->
      <div v-else class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
        <table class="w-full">
          <thead>
            <tr class="border-b border-gray-700">
              <th class="px-4 py-3 text-left"><input type="checkbox" @change="toggleSelectAll" :checked="isAllSelected" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600" /></th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Channel</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Type</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Quality</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Category</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Source</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Status</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-700">
            <tr v-for="channel in filteredChannels" :key="channel.id" class="hover:bg-gray-700/50">
              <td class="px-4 py-3"><input type="checkbox" :value="channel.id" v-model="selectedChannels" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600" /></td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <img v-if="channel.logo_url" :src="channel.logo_url" class="w-10 h-10 rounded object-cover bg-gray-700" />
                  <div v-else class="w-10 h-10 rounded bg-gray-700 flex items-center justify-center"><Tv class="w-5 h-5 text-gray-500" /></div>
                  <span class="text-white font-medium">{{ channel.name }}</span>
                </div>
              </td>
              <td class="px-4 py-3"><span class="px-2 py-0.5 text-xs rounded bg-gray-700 text-gray-300 uppercase">{{ channel.stream_type }}</span></td>
              <td class="px-4 py-3">
                <QualityBadge v-if="channel.quality_level" :quality="channel.quality_level" :showLabel="true" size="sm" />
                <span v-else class="text-gray-500 text-xs">-</span>
              </td>
              <td class="px-4 py-3 text-gray-400 text-sm">{{ channel.categories?.[0]?.name || '-' }}</td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <span class="px-2 py-1 text-xs rounded-full" :class="getSourceStatusClass(channel)">
                    {{ getSourceStatusText(channel) }}
                  </span>
                  <select
                    v-if="channel.backup_url_1 || channel.backup_url_2"
                    :value="channel.active_source_index ?? 0"
                    @change="switchSource(channel, parseInt($event.target.value))"
                    :disabled="switchingSource[channel.id]"
                    class="px-1 py-0.5 text-xs bg-gray-700 border border-gray-600 rounded text-gray-300 focus:outline-none focus:border-indigo-500"
                    title="Switch source"
                  >
                    <option :value="0">Primary</option>
                    <option v-if="channel.backup_url_1" :value="1">Backup 1</option>
                    <option v-if="channel.backup_url_2" :value="2">Backup 2</option>
                  </select>
                </div>
              </td>
              <td class="px-4 py-3">
                <span class="px-2 py-1 text-xs rounded-full" :class="channel.is_active ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'">
                  {{ channel.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-1">
                  <button @click="checkSource(channel)" :disabled="checkingChannels[channel.id]" class="p-1.5 hover:bg-gray-600 rounded transition text-gray-400 hover:text-cyan-400 disabled:opacity-50" title="Check source">
                    <Activity class="w-4 h-4" :class="checkingChannels[channel.id] ? 'animate-spin' : ''" />
                  </button>
                  <button @click="refreshSource(channel)" :disabled="refreshingChannels[channel.id]" class="p-1.5 hover:bg-gray-600 rounded transition text-gray-400 hover:text-blue-400 disabled:opacity-50" title="Refresh source">
                    <RefreshCw class="w-4 h-4" :class="refreshingChannels[channel.id] ? 'animate-spin' : ''" />
                  </button>
                  <button @click="stopSource(channel)" :disabled="stoppingChannels[channel.id]" class="p-1.5 hover:bg-gray-600 rounded transition text-gray-400 hover:text-orange-400 disabled:opacity-50" title="Stop channel">
                    <Square class="w-4 h-4" />
                  </button>
                  <button @click="toggleStatus(channel)" class="p-1.5 hover:bg-gray-600 rounded transition text-gray-400 hover:text-white" :title="channel.is_active ? 'Deactivate' : 'Activate'">
                    <Power class="w-4 h-4" :class="channel.is_active ? 'text-green-400' : 'text-red-400'" />
                  </button>
                  <Link :href="route('admin.channels.edit', channel.id)" class="p-1.5 hover:bg-gray-600 rounded transition text-gray-400 hover:text-white"><Pencil class="w-4 h-4" /></Link>
                  <button @click="confirmDelete(channel)" class="p-1.5 hover:bg-red-600/20 rounded transition text-gray-400 hover:text-red-400"><Trash2 class="w-4 h-4" /></button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="channels?.links" class="flex items-center justify-between">
        <p class="text-gray-400 text-sm">Showing {{ channels.from }} to {{ channels.to }} of {{ channels.total }} channels</p>
        <div class="flex gap-2">
          <Link v-for="page in channels.links" :key="page.label" :href="page.url || '#'" class="px-3 py-1 rounded-lg text-sm"
            :class="page.active ? 'bg-indigo-600 text-white' : 'bg-gray-700 text-gray-400 hover:bg-gray-600'" preserve-scroll>
            {{ page.label }}
          </Link>
        </div>
      </div>

      <!-- Test Stream Modal -->
      <div v-if="testModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 w-full max-w-lg">
          <h3 class="text-lg font-semibold text-white mb-4">Test Stream: {{ testModal.name }}</h3>
          <div class="space-y-3 text-sm">
            <div class="flex justify-between"><span class="text-gray-400">URL:</span><span class="text-white truncate ml-2">{{ testModal.stream_url }}</span></div>
            <div class="flex justify-between"><span class="text-gray-400">Type:</span><span class="text-white uppercase">{{ testModal.stream_type }}</span></div>
            <div class="flex justify-between"><span class="text-gray-400">Quality:</span><QualityBadge v-if="testModal.quality_level" :quality="testModal.quality_level" :showLabel="true" size="md" /><span v-else class="text-gray-400">Unknown</span></div>
            <div class="flex justify-between"><span class="text-gray-400">Status:</span><span class="text-green-400">Online</span></div>
          </div>
          <div class="mt-4 p-4 bg-gray-700/50 rounded-lg">
            <HLSPlayer v-if="testModal.stream_type === 'hls'" :src="testModal.stream_url" :autoplay="true" :muted="true" />
            <p v-else class="text-gray-400 text-center">Stream preview available for HLS streams</p>
          </div>
          <div class="flex justify-end mt-4">
            <button @click="testModal = null" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">Close</button>
          </div>
        </div>
      </div>

      <!-- Delete Modal -->
      <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 w-full max-w-md">
          <h3 class="text-lg font-semibold text-white mb-2">Delete Channel</h3>
          <p class="text-gray-400">Delete "<strong class="text-white">{{ deleteTarget.name }}</strong>"? This will also remove all stream assignments.</p>
          <div class="flex justify-end gap-3 mt-6">
            <button @click="deleteTarget = null" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">Cancel</button>
            <button @click="performDelete" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded-lg transition">Delete</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import QualityBadge from '@/Components/QualityBadge.vue'
import HLSPlayer from '@/Components/Player/HLSPlayer.vue'
import { Search, Plus, Upload, Tv, Pencil, Trash2, Power, LayoutGrid, List, ScanLine, RefreshCw, Square, Activity } from 'lucide-vue-next'

const props = defineProps({ channels: Object, categories: Array })

const search = ref('')
const filterType = ref('')
const filterCategory = ref('')
const filterStatus = ref('')
const filterQuality = ref('')
const viewMode = ref('grid')
const selectedChannels = ref([])
const deleteTarget = ref(null)
const testModal = ref(null)
const checkingChannels = ref({})
const refreshingChannels = ref({})
const stoppingChannels = ref({})
const sourceStatuses = ref({})

const isAllSelected = computed(() => {
  const data = filteredChannels.value
  return data.length > 0 && data.every(c => selectedChannels.value.includes(c.id))
})

const toggleSelectAll = () => {
  if (isAllSelected.value) selectedChannels.value = []
  else selectedChannels.value = filteredChannels.value.map(c => c.id)
}

const filteredChannels = computed(() => {
  const data = props.channels?.data || []
  return data.filter(channel => {
    if (search.value) {
      const q = search.value.toLowerCase()
      if (!channel.name.toLowerCase().includes(q)) return false
    }
    if (filterType.value && channel.stream_type !== filterType.value) return false
    if (filterCategory.value && !channel.categories?.some(c => c.id == filterCategory.value)) return false
    if (filterStatus.value !== '' && String(channel.is_active) !== filterStatus.value) return false
    if (filterQuality.value) {
      if (filterQuality.value === 'unknown') {
        if (channel.quality_level) return false
      } else {
        if (channel.quality_level !== filterQuality.value) return false
      }
    }
    return true
  })
})

const toggleStatus = (channel) => {
  router.post(route('admin.channels.toggle-status', channel.id), {}, { preserveScroll: true })
}

const testChannel = (channel) => { testModal.value = channel }

const confirmDelete = (channel) => { deleteTarget.value = channel }

const performDelete = () => {
  if (deleteTarget.value) {
    router.delete(route('admin.channels.destroy', deleteTarget.value.id))
    deleteTarget.value = null
  }
}

const bulkToggleStatus = (active) => {
  selectedChannels.value.forEach(id => {
    router.post(route('admin.channels.toggle-status', id), { is_active: active }, { preserveScroll: true })
  })
  selectedChannels.value = []
}

const bulkDelete = () => {
  if (confirm(`Delete ${selectedChannels.value.length} channels?`)) {
    selectedChannels.value.forEach(id => {
      router.delete(route('admin.channels.destroy', id), { preserveScroll: true })
    })
    selectedChannels.value = []
  }
}

const scanAllQualities = () => {
  if (confirm('Scan quality for all channels? This may take a while.')) {
    router.post(route('admin.quality.scan.all.channels'))
  }
}

const checkSource = async (channel) => {
  checkingChannels.value[channel.id] = true
  try {
    const response = await fetch(route('admin.channels.check-source', channel.id), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
      },
    })
    const data = await response.json()
    if (data.success) {
      sourceStatuses.value[channel.id] = data.data.status
      channel.source_status = data.data.status
      channel.source_last_checked_at = data.data.last_checked_at
      channel.source_check_attempts = data.data.check_attempts
    }
  } catch (e) {
    console.error('Health check failed:', e)
  } finally {
    checkingChannels.value[channel.id] = false
  }
}

const refreshSource = async (channel) => {
  refreshingChannels.value[channel.id] = true
  try {
    const response = await fetch(route('admin.channels.refresh-source', channel.id), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
      },
    })
    const data = await response.json()
    if (data.success) {
      sourceStatuses.value[channel.id] = data.data.status
      channel.source_status = data.data.status
      channel.source_last_checked_at = data.data.last_checked_at
      channel.source_check_attempts = data.data.check_attempts
    }
  } catch (e) {
    console.error('Refresh failed:', e)
  } finally {
    refreshingChannels.value[channel.id] = false
  }
}

const switchingSource = ref({})

const switchSource = async (channel, sourceIndex) => {
  switchingSource.value[channel.id] = true
  try {
    const response = await fetch(route('admin.channels.switch-source', channel.id), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
      },
      body: JSON.stringify({ source_index: sourceIndex }),
    })
    const data = await response.json()
    if (data.success) {
      channel.active_source_index = data.data.source_index
      channel.source_status = data.data.probe?.status || 'unknown'
      sourceStatuses.value[channel.id] = data.data.probe?.status || 'unknown'
    }
  } catch (e) {
    console.error('Source switch failed:', e)
  } finally {
    switchingSource.value[channel.id] = false
  }
}

const stopSource = async (channel) => {
  if (!confirm(`Stop channel "${channel.name}"?`)) return
  stoppingChannels.value[channel.id] = true
  try {
    const response = await fetch(route('admin.channels.stop-source', channel.id), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
      },
    })
    const data = await response.json()
    if (data.success) {
      sourceStatuses.value[channel.id] = 'offline'
      channel.source_status = 'offline'
    }
  } catch (e) {
    console.error('Stop failed:', e)
  } finally {
    stoppingChannels.value[channel.id] = false
  }
}

const getSourceStatusClass = (channel) => {
  const status = sourceStatuses.value[channel.id] || channel.source_status
  if (status === 'online') return 'bg-green-500/20 text-green-400'
  if (status === 'offline') return 'bg-red-500/20 text-red-400'
  return 'bg-gray-500/20 text-gray-400'
}

const getSourceStatusText = (channel) => {
  const status = sourceStatuses.value[channel.id] || channel.source_status
  const idx = channel.active_source_index ?? 0
  const labels = ['Primary', 'Backup 1', 'Backup 2']
  const label = labels[idx] || 'Primary'
  if (status === 'online') return label
  if (status === 'offline') return 'Offline'
  return 'Unknown'
}

const getSourceLabel = (channel) => {
  const idx = channel.active_source_index ?? 0
  return ['Primary', 'Backup 1', 'Backup 2'][idx] || 'Primary'
}

const fetchSourceStatuses = async () => {
  const ids = (props.channels?.data || []).map(c => c.id)
  if (!ids.length) return
  try {
    const qs = ids.map(id => `ids[]=${id}`).join('&')
    const resp = await fetch(route('admin.channels.source-statuses') + '?' + qs, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
    const json = await resp.json()
    if (json.data) {
      json.data.forEach(s => {
        sourceStatuses.value[s.id] = s.source_status
        const ch = (props.channels?.data || []).find(c => c.id === s.id)
        if (ch) {
          ch.source_status = s.source_status
          ch.source_last_checked_at = s.source_last_checked_at
          ch.source_check_attempts = s.source_check_attempts
          ch.active_source_index = s.active_source_index
        }
      })
    }
  } catch (e) {
    console.error('Source status poll failed:', e)
  }
}

let pollInterval = null
onMounted(() => {
  fetchSourceStatuses()
  pollInterval = setInterval(fetchSourceStatuses, 30000)
})
onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval)
})
</script>
