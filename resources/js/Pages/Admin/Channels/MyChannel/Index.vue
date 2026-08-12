<template>
  <AdminLayout>
    <div class="p-6 space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-white">My Channels</h1>
          <p class="text-gray-400 mt-1">Admin-owned playout channels with custom branding, playlists & overlays</p>
        </div>
        <Link :href="route('admin.admin.channels.create')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition flex items-center gap-2">
          <Plus class="w-4 h-4" /> Create Channel
        </Link>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div v-for="stat in stats" :key="stat.label" class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="text-2xl font-bold" :class="stat.color">{{ stat.value }}</div>
          <div class="text-gray-400 text-sm mt-1">{{ stat.label }}</div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-gray-800 rounded-xl p-4 border border-gray-700 flex flex-wrap gap-3 items-center">
        <div class="relative flex-1 min-w-[200px]">
          <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
          <input v-model="search" type="text" placeholder="Search channels..." @input="applyFilters"
            class="w-full pl-10 pr-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500" />
        </div>
        <select v-model="filterStatus" @change="applyFilters" class="px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
          <option value="">All Status</option>
          <option value="live">Live</option>
          <option value="offline">Offline</option>
          <option value="scheduled">Scheduled</option>
          <option value="ready">Ready</option>
        </select>
        <select v-model="filterGenre" @change="applyFilters" class="px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
          <option value="">All Genres</option>
          <option v-for="g in genres" :key="g" :value="g">{{ g }}</option>
        </select>
        <select v-model="filterFeatured" @change="applyFilters" class="px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
          <option value="">All</option>
          <option value="1">Featured</option>
          <option value="0">Not Featured</option>
        </select>
        <button @click="resetFilters" class="px-3 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded-lg text-sm transition">Reset</button>
      </div>

      <!-- Channel Grid -->
      <div v-if="channels?.data?.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <div v-for="channel in channels.data" :key="channel.id"
          class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden hover:border-gray-600 transition group">
          <!-- Channel Header / Branding -->
          <div class="h-28 relative flex items-center justify-center"
            :style="{ background: channel.background_color || '#1e293b' }">
            <img v-if="channel.logo_url" :src="channel.logo_url" :alt="channel.channel_name"
              class="h-16 w-auto object-contain" />
            <Tv v-else class="w-10 h-10 text-gray-500" />
            <!-- Status badge -->
            <span class="absolute top-2 left-2 px-2 py-0.5 text-xs font-semibold rounded-full uppercase"
              :class="statusClass(channel.broadcast_status)">
              <span v-if="channel.broadcast_status === 'live'" class="inline-block w-1.5 h-1.5 rounded-full bg-current mr-1 animate-pulse"></span>
              {{ channel.broadcast_status }}
            </span>
            <span v-if="channel.is_featured" class="absolute top-2 right-2 text-yellow-400 text-sm">⭐</span>
          </div>

          <!-- Body -->
          <div class="p-4">
            <div class="flex items-start justify-between mb-1">
              <h3 class="text-white font-semibold truncate">{{ channel.channel_name }}</h3>
              <span class="text-gray-500 text-xs ml-2 shrink-0">#{{ channel.channel_number }}</span>
            </div>
            <p class="text-gray-400 text-xs mb-3 line-clamp-2">{{ channel.description || 'No description' }}</p>

            <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs mb-3">
              <div class="text-gray-500">Genre: <span class="text-gray-300">{{ channel.genre || '—' }}</span></div>
              <div class="text-gray-500">Mode: <span class="text-gray-300 capitalize">{{ channel.playout_mode }}</span></div>
              <div class="text-gray-500">Resolution: <span class="text-gray-300">{{ channel.output_resolution || '—' }}</span></div>
              <div class="text-gray-500">Views: <span class="text-gray-300">{{ formatNum(channel.total_views) }}</span></div>
            </div>

            <!-- Overlay indicators -->
            <div class="flex gap-1.5 mb-3">
              <span v-if="channel.enable_ticker" title="Ticker enabled"
                class="px-1.5 py-0.5 bg-blue-500/20 text-blue-400 rounded text-xs">Ticker</span>
              <span v-if="channel.enable_overlay_logo" title="Logo overlay"
                class="px-1.5 py-0.5 bg-purple-500/20 text-purple-400 rounded text-xs">Logo</span>
              <span v-if="channel.enable_watermark" title="Watermark"
                class="px-1.5 py-0.5 bg-gray-500/20 text-gray-400 rounded text-xs">Watermark</span>
              <span v-if="channel.loop_playlist" title="Loop"
                class="px-1.5 py-0.5 bg-green-500/20 text-green-400 rounded text-xs">Loop</span>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-1.5">
              <button v-if="channel.broadcast_status !== 'live'" @click="startBroadcast(channel)"
                class="flex-1 px-2 py-1.5 bg-green-600/20 hover:bg-green-600 text-green-400 hover:text-white rounded-lg text-xs transition flex items-center justify-center gap-1">
                <Radio class="w-3 h-3" /> Go Live
              </button>
              <button v-else @click="endBroadcast(channel)"
                class="flex-1 px-2 py-1.5 bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white rounded-lg text-xs transition flex items-center justify-center gap-1">
                <StopCircle class="w-3 h-3" /> End
              </button>
              <Link :href="route('admin.admin.channels.show', channel.channel_slug)"
                class="px-2 py-1.5 bg-gray-700 hover:bg-gray-600 text-white rounded-lg text-xs transition flex items-center gap-1">
                <Settings class="w-3 h-3" /> Manage
              </Link>
              <button @click="toggleFeatured(channel)"
                class="p-1.5 rounded-lg transition"
                :class="channel.is_featured ? 'bg-yellow-500/20 text-yellow-400 hover:bg-yellow-500/30' : 'bg-gray-700 text-gray-400 hover:bg-gray-600'">
                <Star class="w-3.5 h-3.5" />
              </button>
              <button @click="confirmDelete(channel)"
                class="p-1.5 bg-gray-700 hover:bg-red-600/20 text-gray-400 hover:text-red-400 rounded-lg transition">
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-else class="text-center py-20">
        <Tv class="w-16 h-16 text-gray-600 mx-auto mb-4" />
        <h3 class="text-white text-lg font-semibold mb-2">No channels yet</h3>
        <p class="text-gray-400 mb-6">Create your first admin playout channel to start broadcasting</p>
        <Link :href="route('admin.admin.channels.create')" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition inline-flex items-center gap-2">
          <Plus class="w-4 h-4" /> Create Channel
        </Link>
      </div>

      <!-- Pagination -->
      <div v-if="channels?.links?.length > 3" class="flex items-center justify-between">
        <p class="text-gray-400 text-sm">Showing {{ channels.from }}–{{ channels.to }} of {{ channels.total }}</p>
        <div class="flex gap-1">
          <Link v-for="link in channels.links" :key="link.label" :href="link.url || '#'"
            class="px-3 py-1 rounded-lg text-sm"
            :class="link.active ? 'bg-indigo-600 text-white' : 'bg-gray-700 text-gray-400 hover:bg-gray-600'"
            preserve-scroll v-html="link.label" />
        </div>
      </div>
    </div>

    <!-- Delete confirm -->
    <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 w-full max-w-md">
        <h3 class="text-lg font-semibold text-white mb-2">Delete Channel</h3>
        <p class="text-gray-400">Delete "<strong class="text-white">{{ deleteTarget.channel_name }}</strong>"? This cannot be undone.</p>
        <div class="flex justify-end gap-3 mt-6">
          <button @click="deleteTarget = null" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">Cancel</button>
          <button @click="performDelete" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded-lg transition">Delete</button>
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
import { Plus, Search, Tv, Trash2, Star, Settings, Radio, StopCircle } from 'lucide-vue-next'
import { useApiFetch } from '@/Composables/useApiFetch'

const props = defineProps({
  channels: Object,
})

const search = ref('')
const filterStatus = ref('')
const filterGenre = ref('')
const filterFeatured = ref('')
const deleteTarget = ref(null)

const genres = computed(() => {
  const all = (props.channels?.data || []).map(c => c.genre).filter(Boolean)
  return [...new Set(all)]
})

const stats = computed(() => {
  const data = props.channels?.data || []
  return [
    { label: 'Total', value: props.channels?.total || 0, color: 'text-white' },
    { label: 'Live Now', value: data.filter(c => c.broadcast_status === 'live').length, color: 'text-red-400' },
    { label: 'Ready', value: data.filter(c => c.broadcast_status === 'ready').length, color: 'text-green-400' },
    { label: 'Scheduled', value: data.filter(c => c.broadcast_status === 'scheduled').length, color: 'text-yellow-400' },
    { label: 'Featured', value: data.filter(c => c.is_featured).length, color: 'text-yellow-300' },
  ]
})

const statusClass = (status) => ({
  live: 'bg-red-500/20 text-red-400',
  offline: 'bg-gray-500/20 text-gray-400',
  scheduled: 'bg-yellow-500/20 text-yellow-400',
  ready: 'bg-green-500/20 text-green-400',
  ended: 'bg-gray-600/20 text-gray-500',
  error: 'bg-red-700/20 text-red-500',
}[status] || 'bg-gray-500/20 text-gray-400')

const formatNum = (n) => {
  if (!n) return '0'
  if (n >= 1e6) return (n / 1e6).toFixed(1) + 'M'
  if (n >= 1e3) return (n / 1e3).toFixed(1) + 'K'
  return String(n)
}

const applyFilters = () => {
  const params = {}
  if (search.value) params.search = search.value
  if (filterStatus.value) params.broadcast_status = filterStatus.value
  if (filterGenre.value) params.genre = filterGenre.value
  if (filterFeatured.value !== '') params.is_featured = filterFeatured.value
  router.get(route('admin.admin.channels.index'), params, { preserveState: true, replace: true })
}

const resetFilters = () => {
  search.value = ''
  filterStatus.value = ''
  filterGenre.value = ''
  filterFeatured.value = ''
  router.get(route('admin.admin.channels.index'), {}, { preserveState: true, replace: true })
}

const { apiFetch } = useApiFetch()

const startBroadcast = async (channel) => {
  await apiFetch(route('admin.channels.my-channel.broadcast.start', channel.channel_slug), { method: 'POST' })
  router.reload({ preserveScroll: true })
}

const endBroadcast = async (channel) => {
  if (!confirm('End broadcast for ' + channel.channel_name + '?')) return
  await apiFetch(route('admin.channels.my-channel.broadcast.stop', channel.channel_slug), { method: 'POST' })
  router.reload({ preserveScroll: true })
}

const toggleFeatured = (channel) => {
  router.post(route('admin.admin.channels.toggle-featured', channel.channel_slug), {
    is_featured: !channel.is_featured,
  }, { preserveScroll: true })
}

const confirmDelete = (channel) => { deleteTarget.value = channel }

const performDelete = () => {
  if (deleteTarget.value) {
    router.delete(route('admin.admin.channels.destroy', deleteTarget.value.channel_slug))
    deleteTarget.value = null
  }
}
</script>
