<template>
  <AdminLayout>
    <div class="p-6">
      <div class="mb-6">
        <Link :href="route('admin.admin.channels.index')"
          class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
          <ArrowLeft class="w-4 h-4" /> Back to My Channels
        </Link>
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-2xl font-bold text-white">{{ channel?.channel_name }}</h1>
            <p class="text-gray-400 mt-1">Channel #{{ channel?.channel_number }} • {{ channel?.genre || 'General' }}</p>
          </div>
          <div class="flex gap-2">
            <Link :href="route('admin.admin.channels.edit', channel?.channel_slug)"
              class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition flex items-center gap-2">
              <Settings class="w-4 h-4" /> Edit Channel
            </Link>
        <Link :href="route('admin.admin.channels.index')"
              class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">
              Refresh
            </Link>
          </div>
        </div>
      </div>

      <!-- Broadcast Status Bar -->
      <div class="bg-gray-800 rounded-xl p-4 border border-gray-700 mb-6 flex items-center justify-between">
        <div class="flex items-center gap-4">
          <span class="text-sm text-gray-400">Status:</span>
          <span :class="statusClass(channel?.broadcast_status)">
            {{ channel?.broadcast_status || 'offline' }}
          </span>
          <span v-if="broadcast?.total_viewers" class="text-sm text-gray-400">
            Viewers: <span class="text-white">{{ broadcast?.total_viewers }}</span>
          </span>
          <span v-if="broadcast?.duration" class="text-sm text-gray-400">
            Duration: {{ formatDuration(broadcast?.duration) }}
          </span>
        </div>
        <div class="flex gap-2">
          <button v-if="channel?.broadcast_status !== 'live'"
            @click="startBroadcast"
            :disabled="startingBroadcast"
            class="px-4 py-2 bg-green-600 hover:bg-green-500 text-white rounded-lg transition flex items-center gap-2 disabled:opacity-50">
            <Radio class="w-4 h-4" v-if="!startingBroadcast" />
            <Loader2 class="w-4 h-4 animate-spin" v-else />
            Go Live
          </button>
          <button v-else
            @click="stopBroadcast"
            :disabled="stoppingBroadcast"
            class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded-lg transition flex items-center gap-2 disabled:opacity-50">
            <StopCircle class="w-4 h-4" v-if="!stoppingBroadcast" />
            <Loader2 class="w-4 h-4 animate-spin" v-else />
            End Broadcast
          </button>
        </div>
      </div>

      <!-- Tabs -->
      <div class="border-b border-gray-700 mb-6">
        <nav class="flex gap-6">
          <button v-for="tab in tabs" :key="tab.id"
            @click="activeTab = tab.id"
            :class="activeTab === tab.id
              ? 'border-indigo-500 text-indigo-400'
              : 'border-transparent text-gray-500 hover:text-gray-400 hover:border-gray-600'"
            class="pb-3 px-1 border-b-2 font-medium text-sm transition">
            {{ tab.label }}
          </button>
        </nav>
      </div>

      <!-- Content Tab -->
      <ContentTab v-if="activeTab === 'content'" :channel="channel" />
      <PlaylistTab v-else-if="activeTab === 'playlist'" :channel="channel" />
      <BroadcastTab v-else-if="activeTab === 'broadcast'" :channel="channel" :broadcast="broadcast" />
      <OverlaysTab v-else-if="activeTab === 'overlays'" :channel="channel" />
      <StatisticsTab v-else-if="activeTab === 'statistics'" :channel="channel" />

      <!-- Overview tab (default) -->
      <div v-if="activeTab === 'overview'" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <StatCard label="Total Views" :value="channel?.total_views" />
          <StatCard label="Current Viewers" :value="broadcast?.total_viewers" />
          <StatCard label="Content Items" :value="channel?.playlistItems?.length" />
          <StatCard label="Overlays" :value="channel?.overlays?.length" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
            <h3 class="text-lg font-semibold text-white mb-4">Stream Configuration</h3>
            <dl class="space-y-2 text-sm">
              <div class="flex justify-between"><dt class="text-gray-400">Stream Type</dt><dd class="text-white">{{ channel?.stream_type }}</dd></div>
              <div class="flex justify-between"><dt class="text-gray-400">Stream URL</dt><dd class="text-white font-mono text-xs">{{ channel?.stream_url || '—' }}</dd></div>
              <div class="flex justify-between"><dt class="text-gray-400">Resolution</dt><dd class="text-white">{{ channel?.output_resolution || 'Auto' }}</dd></div>
              <div class="flex justify-between"><dt class="text-gray-400">Playout Mode</dt><dd class="text-white capitalize">{{ channel?.playout_mode }}</dd></div>
              <div class="flex justify-between"><dt class="text-gray-400">Playlist Type</dt><dd class="text-white capitalize">{{ channel?.playlist_type }}</dd></div>
              <div class="flex justify-between"><dt class="text-gray-400">Loop</dt><dd class="text-white">{{ channel?.loop_playlist ? 'Yes' : 'No' }}</dd></div>
              <div class="flex justify-between"><dt class="text-gray-400">Shuffle</dt><dd class="text-white">{{ channel?.shuffle_mode ? 'Yes' : 'No' }}</dd></div>
              <div class="flex justify-between"><dt class="text-gray-400">Transition</dt><dd class="text-white">{{ channel?.transition_type }} ({{ channel?.transition_duration }}s)</dd></div>
            </dl>
          </div>

          <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
            <h3 class="text-lg font-semibold text-white mb-4">Access Control</h3>
            <dl class="space-y-2 text-sm">
              <div class="flex justify-between"><dt class="text-gray-400">Public</dt><dd class="text-white">{{ channel?.is_public ? 'Yes' : 'No' }}</dd></div>
              <div class="flex justify-between"><dt class="text-gray-400">Requires Subscription</dt><dd class="text-white">{{ channel?.require_subscription ? 'Yes' : 'No' }}</dd></div>
              <div class="flex justify-between"><dt class="text-gray-400">Featured</dt><dd class="text-white">{{ channel?.is_featured ? 'Yes' : 'No' }}</dd></div>
              <div class="flex justify-between"><dt class="text-gray-400">Adult Content</dt><dd class="text-white">{{ channel?.is_adult ? 'Yes' : 'No' }}</dd></div>
              <div class="flex justify-between"><dt class="text-gray-400">License Type</dt><dd class="text-white">{{ channel?.license_type }}</dd></div>
              <div class="flex justify-between"><dt class="text-gray-400">Language</dt><dd class="text-white">{{ channel?.language }}</dd></div>
              <div class="flex justify-between"><dt class="text-gray-400">Country</dt><dd class="text-white">{{ channel?.country || '—' }}</dd></div>
              <div class="flex justify-between"><dt class="text-gray-400">Genre</dt><dd class="text-white">{{ channel?.genre || '—' }}</dd></div>
            </dl>
          </div>
        </div>

        <div v-if="channel?.overlays?.length" class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Active Overlays</h3>
          <div class="flex flex-wrap gap-3">
            <div v-for="overlay in channel.overlays" :key="overlay.id"
              class="px-3 py-1.5 bg-gray-700/50 rounded-lg text-sm flex items-center gap-2">
              <span class="w-2 h-2 rounded-full" :class="overlay.z_index > 0 ? 'bg-purple-400' : 'bg-blue-400'"></span>
              {{ overlay.overlay_name || overlay.overlay_type }}
              <span class="text-gray-500">({{ overlay.position }})</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeft, Settings, Radio, StopCircle, Loader2, RefreshCw } from 'lucide-vue-next'
import { useApiFetch } from '@/Composables/useApiFetch'
import StatCard from '@/Components/StatCard.vue'
import ContentTab from './Show/ContentTab.vue'
import PlaylistTab from './Show/PlaylistTab.vue'
import BroadcastTab from './Show/BroadcastTab.vue'
import OverlaysTab from './Show/OverlaysTab.vue'
import StatisticsTab from './Show/StatisticsTab.vue'

const props = defineProps({
  channel: { type: Object, required: true },
})

const activeTab = ref('overview')
const broadcast = ref(null)
const startingBroadcast = ref(false)
const stoppingBroadcast = ref(false)

const tabs = [
  { id: 'overview', label: 'Overview' },
  { id: 'content', label: 'Content Library' },
  { id: 'playlist', label: 'Playlist' },
  { id: 'broadcast', label: 'Broadcast' },
  { id: 'overlays', label: 'Overlays' },
  { id: 'statistics', label: 'Statistics' },
]

const statusClass = (status) => ({
  live: 'text-red-400',
  offline: 'text-gray-400',
  scheduled: 'text-yellow-400',
  ready: 'text-green-400',
  ended: 'text-gray-500',
  starting: 'text-blue-400',
  error: 'text-red-500',
}[status] || 'text-gray-400')

const formatDuration = (seconds) => {
  if (!seconds) return '—'
  const h = Math.floor(seconds / 3600)
  const m = Math.floor((seconds % 3600) / 60)
  const s = seconds % 60
  if (h > 0) return `${h}h ${m}m`
  if (m > 0) return `${m}m ${s}s`
  return `${s}s`
}

const { apiFetch } = useApiFetch()

const fetchBroadcastStatus = async () => {
  try {
    const res = await apiFetch(route('admin.channels.my-channel.broadcast', props.channel.channel_slug))
    if (res.ok) {
      const json = await res.json()
      broadcast.value = json.broadcast || null
    }
  } catch (e) { /* silent */ }
}

const startBroadcast = async () => {
  startingBroadcast.value = true
  try {
    await apiFetch(route('admin.channels.my-channel.broadcast.start', props.channel.channel_slug), { method: 'POST' })
    await fetchBroadcastStatus()
    router.reload()
  } finally {
    startingBroadcast.value = false
  }
}

const stopBroadcast = async () => {
  stoppingBroadcast.value = true
  try {
    await apiFetch(route('admin.channels.my-channel.broadcast.stop', props.channel.channel_slug), { method: 'POST' })
    await fetchBroadcastStatus()
    router.reload()
  } finally {
    stoppingBroadcast.value = false
  }
}

onMounted(() => {
  fetchBroadcastStatus()
})
</script>
