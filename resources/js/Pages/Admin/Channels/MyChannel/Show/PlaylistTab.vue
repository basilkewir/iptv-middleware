<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h3 class="text-lg font-semibold text-white">Playlist</h3>
      <button @click="showAddModal = true"
        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition flex items-center gap-2">
        <Plus class="w-4 h-4" /> Add Content
      </button>
    </div>

    <div v-if="error" class="p-3 bg-red-900/30 border border-red-700/50 rounded-lg text-red-400 text-sm flex items-center justify-between">
      {{ error }}
      <button @click="error = ''" class="ml-3 text-red-500 hover:text-red-300">✕</button>
    </div>

    <div v-if="playlist.length" class="space-y-2">
      <div v-for="(item, index) in playlist" :key="item.id"
        draggable="true"
        @dragstart="dragStart(index)"
        @dragover.prevent="dragOver(index)"
        @drop="drop"
        class="flex items-center gap-3 p-3 bg-gray-800 rounded-lg border border-gray-700 cursor-grab active:cursor-grabbing hover:border-gray-600 transition"
        :class="{ 'opacity-40': dragIndex === index }">
        <span class="text-gray-500 text-xs w-6 text-center shrink-0">{{ index + 1 }}</span>
        <GripVertical class="w-4 h-4 text-gray-500 shrink-0" />
        <div class="w-12 h-8 bg-gray-700 rounded flex items-center justify-center shrink-0 overflow-hidden">
          <img v-if="item.content?.thumbnail_url" :src="'/storage/' + item.content.thumbnail_url" class="w-full h-full object-cover" />
          <Play v-else class="w-4 h-4 text-gray-400" />
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-white font-medium truncate">{{ item.content?.title || item.content?.file_name || 'Unknown' }}</div>
          <div class="text-xs text-gray-400 flex gap-2 mt-0.5">
            <span :class="qualityColor(item.content?.quality_level)">{{ item.content?.quality_level?.toUpperCase() }}</span>
            <span>{{ formatDuration(item.content?.duration) }}</span>
            <span v-if="item.transition_type !== 'cut'" class="text-gray-500">{{ item.transition_type }}</span>
          </div>
        </div>
        <button @click="removeFromPlaylist(item)" class="p-1 text-gray-400 hover:text-red-400 rounded transition shrink-0">
          <Trash2 class="w-4 h-4" />
        </button>
      </div>
    </div>

    <div v-else-if="!loading" class="text-center py-12 text-gray-500">
      <ListVideo class="w-12 h-12 mx-auto mb-3 opacity-50" />
      <p>No items in playlist.</p>
      <p class="text-sm mt-1">Upload content in the Content Library tab, then add it here.</p>
    </div>

    <div v-if="loading" class="text-center py-8">
      <Loader2 class="w-8 h-8 animate-spin text-gray-500 mx-auto" />
    </div>

    <!-- Add content modal -->
    <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
      <div class="bg-gray-800 rounded-xl border border-gray-700 w-full max-w-lg max-h-[80vh] flex flex-col">
        <div class="flex items-center justify-between p-4 border-b border-gray-700">
          <h4 class="text-white font-semibold">Add to Playlist</h4>
          <button @click="showAddModal = false" class="text-gray-400 hover:text-white text-lg leading-none">✕</button>
        </div>
        <div class="overflow-y-auto flex-1 p-4 space-y-2">
          <div v-if="loadingLibrary" class="text-center py-8">
            <Loader2 class="w-6 h-6 animate-spin text-gray-500 mx-auto" />
          </div>
          <div v-else-if="!contentLibrary.length" class="text-center py-8 text-gray-500 text-sm">
            No content available. Upload videos in the Content Library tab first.
          </div>
          <div v-for="item in contentLibrary" :key="item.id"
            class="flex items-center gap-3 p-3 bg-gray-700/50 rounded-lg hover:bg-gray-700 transition cursor-pointer"
            @click="addToPlaylist(item)">
            <div class="w-12 h-8 bg-gray-600 rounded flex items-center justify-center shrink-0 overflow-hidden">
              <img v-if="item.thumbnail_url" :src="'/storage/' + item.thumbnail_url" class="w-full h-full object-cover" />
              <Play v-else class="w-4 h-4 text-gray-400" />
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-white text-sm truncate">{{ item.title || item.file_name }}</div>
              <div class="text-xs text-gray-400">{{ item.quality_level?.toUpperCase() }} • {{ formatDuration(item.duration) }}</div>
            </div>
            <Plus class="w-4 h-4 text-indigo-400 shrink-0" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { route } from '@/Composables/useRoute'
import { useApiFetch } from '@/Composables/useApiFetch'
import { GripVertical, Play, Trash2, ListVideo, Plus, Loader2 } from 'lucide-vue-next'

const props = defineProps({
  channel: { type: Object, required: true },
})

const { apiFetch } = useApiFetch()
const playlist = ref([])
const contentLibrary = ref([])
const loading = ref(false)
const loadingLibrary = ref(false)
const showAddModal = ref(false)
const error = ref('')
const dragIndex = ref(null)
const dragOverIndex = ref(null)

const fetchPlaylist = async () => {
  loading.value = true
  try {
    const res = await apiFetch(route('admin.channels.my-channel.playlist', props.channel.channel_slug))
    const json = await res.json()
    playlist.value = json.playlist || []
  } finally {
    loading.value = false
  }
}

const fetchLibrary = async () => {
  loadingLibrary.value = true
  try {
    const res = await apiFetch(route('admin.channels.my-channel.content', props.channel.channel_slug) + '?per_page=100')
    const json = await res.json()
    contentLibrary.value = json.content?.data || json.data || []
  } finally {
    loadingLibrary.value = false
  }
}

watch(showAddModal, (val) => { if (val) fetchLibrary() })

const addToPlaylist = async (item) => {
  const res = await apiFetch(route('admin.channels.my-channel.playlist.store', props.channel.channel_slug), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ content_id: item.id }),
  })
  if (res.ok) {
    await fetchPlaylist()
    showAddModal.value = false
  } else {
    const json = await res.json()
    error.value = json?.message || 'Failed to add to playlist'
  }
}

const removeFromPlaylist = async (item) => {
  if (!confirm('Remove from playlist?')) return
  const res = await apiFetch(
    route('admin.channels.my-channel.playlist.destroy', [props.channel.channel_slug, item.id]),
    { method: 'DELETE' }
  )
  if (res.ok) await fetchPlaylist()
  else error.value = 'Failed to remove item'
}

const dragStart = (index) => { dragIndex.value = index }

const dragOver = (index) => {
  if (dragIndex.value === null || dragIndex.value === index) return
  const items = [...playlist.value]
  const moved = items.splice(dragIndex.value, 1)[0]
  items.splice(index, 0, moved)
  playlist.value = items
  dragIndex.value = index
}

const drop = async () => {
  dragIndex.value = null
  const items = playlist.value.map((item, i) => ({ id: item.id, order_index: i }))
  await apiFetch(route('admin.channels.my-channel.playlist.reorder', props.channel.channel_slug), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ items }),
  })
}

const qualityColor = (q) => ({
  '4k': 'text-purple-400', fhd: 'text-blue-400', hd: 'text-green-400', sd: 'text-yellow-400', low: 'text-gray-400',
}[q] || 'text-gray-400')

function formatDuration(s) {
  if (!s) return '0s'
  if (s < 60) return `${s}s`
  return `${Math.floor(s / 60)}m ${s % 60}s`
}

onMounted(fetchPlaylist)
</script>
