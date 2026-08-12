<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h3 class="text-lg font-semibold text-white">Content Library</h3>
      <label class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition flex items-center gap-2 cursor-pointer">
        <Upload class="w-4 h-4" /> Upload Content
        <input type="file" accept=".mp4,.mkv,.avi,.mov,.webm" class="hidden" ref="fileInputRef" @change="handleFileUpload" />
      </label>
    </div>

    <!-- Upload progress -->
    <div v-if="uploading" class="flex items-center gap-3 p-4 bg-gray-800 rounded-lg border border-indigo-700">
      <Loader2 class="w-5 h-5 animate-spin text-indigo-500 shrink-0" />
      <div class="flex-1 min-w-0">
        <div class="text-white text-sm truncate">{{ uploadFile?.name }}</div>
        <div class="w-full bg-gray-700 rounded-full h-1.5 mt-2">
          <div class="bg-indigo-500 h-1.5 rounded-full transition-all" :style="{ width: uploadProgress + '%' }"></div>
        </div>
      </div>
      <span class="text-sm text-gray-400 shrink-0">{{ Math.round(uploadProgress) }}%</span>
    </div>

    <!-- Error banner -->
    <div v-if="error" class="p-3 bg-red-900/30 border border-red-700/50 rounded-lg text-red-400 text-sm flex items-center justify-between">
      {{ error }}
      <button @click="error = ''" class="text-red-500 hover:text-red-300 ml-3">✕</button>
    </div>

    <!-- Content list -->
    <div v-if="content?.data?.length" class="space-y-2">
      <div v-for="item in content.data" :key="item.id"
        class="flex items-center justify-between p-4 bg-gray-800 rounded-xl border border-gray-700 hover:border-gray-600 transition">
        <div class="flex items-center gap-4">
          <div class="w-16 h-12 bg-gray-700 rounded overflow-hidden flex items-center justify-center shrink-0">
            <img v-if="item.thumbnail_url" :src="'/storage/' + item.thumbnail_url" class="w-full h-full object-cover" />
            <Play v-else class="w-6 h-6 text-gray-500" />
          </div>
          <div>
            <div class="text-white font-medium">{{ item.title || item.file_name }}</div>
            <div class="flex gap-3 text-xs text-gray-400 mt-1">
              <span :class="qualityColor(item.quality_level)" class="font-medium uppercase">{{ item.quality_level }}</span>
              <span>{{ formatDuration(item.duration) }}</span>
              <span>{{ formatSize(item.file_size) }}</span>
              <span :class="item.is_transcoded ? 'text-green-400' : 'text-yellow-400'">
                {{ item.is_transcoded ? '✓ Ready' : '⏳ Processing' }}
              </span>
            </div>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <button @click="addToPlaylist(item)"
            class="px-2 py-1 bg-indigo-600/20 hover:bg-indigo-600 text-indigo-400 hover:text-white rounded text-xs transition">
            + Playlist
          </button>
          <span class="text-xs text-gray-500">{{ formatDate(item.uploaded_at) }}</span>
          <button @click="removeContent(item)" class="p-1 text-gray-400 hover:text-red-400 rounded transition">
            <Trash2 class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <div v-else-if="!loading" class="text-center py-12 text-gray-500">
      <Upload class="w-12 h-12 mx-auto mb-3 opacity-50" />
      <p>No content uploaded yet.</p>
      <p class="text-sm mt-1">Upload videos to build your playlist.</p>
    </div>

    <div v-if="loading" class="text-center py-8">
      <Loader2 class="w-8 h-8 animate-spin text-gray-500 mx-auto" />
    </div>

    <!-- Pagination -->
    <div v-if="content?.last_page > 1" class="flex items-center justify-between">
      <span class="text-sm text-gray-400">{{ content.from }}–{{ content.to }} of {{ content.total }}</span>
      <div class="flex gap-1">
        <button v-for="page in content.last_page" :key="page"
          @click="fetchContent(page)"
          class="px-3 py-1 rounded-lg text-sm"
          :class="page === content.current_page ? 'bg-indigo-600 text-white' : 'bg-gray-700 text-gray-400 hover:bg-gray-600'">
          {{ page }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { route } from '@/Composables/useRoute'
import { useApiFetch } from '@/Composables/useApiFetch'
import { Upload, Trash2, Play, Loader2 } from 'lucide-vue-next'

const props = defineProps({
  channel: { type: Object, required: true },
})

const emit = defineEmits(['content-added'])

const { apiFetch } = useApiFetch()
const content = ref(null)
const loading = ref(false)
const uploading = ref(false)
const uploadProgress = ref(0)
const uploadFile = ref(null)
const error = ref('')
const fileInputRef = ref(null)

const handleFileUpload = async (e) => {
  const file = e.target?.files?.[0]
  if (!file) return

  uploading.value = true
  uploadProgress.value = 10
  uploadFile.value = file
  error.value = ''

  const formData = new FormData()
  formData.append('file', file)
  formData.append('title', file.name.replace(/\.[^.]+$/, ''))
  formData.append('description', '')

  try {
    // Simulate progress since fetch doesn't support upload progress natively
    const progressInterval = setInterval(() => {
      if (uploadProgress.value < 85) uploadProgress.value += 5
    }, 300)

    const res = await apiFetch(route('admin.channels.my-channel.content.upload', props.channel.channel_slug), {
      method: 'POST',
      body: formData,
    })

    clearInterval(progressInterval)
    uploadProgress.value = 100

    if (res.ok) {
      await fetchContent()
      emit('content-added')
    } else {
      const json = await res.json()
      error.value = json?.message || 'Upload failed'
    }
  } catch (e) {
    error.value = 'Upload failed. Please try again.'
  } finally {
    uploading.value = false
    uploadProgress.value = 0
    uploadFile.value = null
    if (fileInputRef.value) fileInputRef.value.value = ''
  }
}

const addToPlaylist = async (item) => {
  const res = await apiFetch(route('admin.channels.my-channel.playlist.store', props.channel.channel_slug), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ content_id: item.id }),
  })
  if (res.ok) {
    emit('content-added')
  } else {
    const json = await res.json()
    error.value = json?.message || 'Failed to add to playlist'
  }
}

const removeContent = async (item) => {
  if (!confirm(`Delete "${item.title || item.file_name}"?`)) return
  const res = await apiFetch(
    route('admin.channels.my-channel.content.destroy', [props.channel.channel_slug, item.id]),
    { method: 'DELETE' }
  )
  if (res.ok) {
    await fetchContent()
  } else {
    error.value = 'Failed to delete content'
  }
}

const fetchContent = async (page = 1) => {
  loading.value = true
  try {
    const res = await apiFetch(route('admin.channels.my-channel.content', props.channel.channel_slug) + `?page=${page}`)
    const json = await res.json()
    content.value = json.content || json
  } finally {
    loading.value = false
  }
}

const qualityColor = (q) => ({
  '4k': 'text-purple-400', fhd: 'text-blue-400', hd: 'text-green-400', sd: 'text-yellow-400', low: 'text-gray-400',
}[q] || 'text-gray-400')

function formatDuration(s) {
  if (!s) return '0s'
  if (s < 60) return `${s}s`
  return `${Math.floor(s / 60)}m ${s % 60}s`
}

function formatSize(bytes) {
  if (!bytes) return '0 B'
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
  if (bytes < 1073741824) return (bytes / 1048576).toFixed(1) + ' MB'
  return (bytes / 1073741824).toFixed(2) + ' GB'
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString()
}

onMounted(() => fetchContent())
</script>
