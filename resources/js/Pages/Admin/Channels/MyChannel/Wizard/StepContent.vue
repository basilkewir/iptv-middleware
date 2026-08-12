<template>
  <div class="space-y-4">
    <h3 class="text-lg font-semibold text-white mb-4">Content Upload & Playlist</h3>

    <div v-if="editing" class="bg-gray-700/30 border border-gray-600 rounded-lg p-4 mb-4">
      <p class="text-sm text-gray-400">
        Uploaded content will appear here. Manage playlist order in the channel management page.
      </p>
    </div>

    <div v-else class="border-2 border-dashed border-gray-600 rounded-lg p-8 text-center cursor-pointer hover:border-indigo-500 transition">
      <input type="file" ref="fileInput" accept=".mp4,.mkv,.avi,.mov,.webm" class="hidden" @change="handleFileUpload" />
      <Upload class="w-12 h-12 text-gray-500 mx-auto mb-3" />
      <p class="text-gray-300 mb-2">{{ uploading ? 'Uploading...' : 'Drag & drop or click to upload videos' }}</p>
      <p class="text-xs text-gray-500">MP4, MKV, AVI, MOV, WEBM — Max 2GB per file</p>
      <div v-if="uploading" class="mt-3">
        <div class="w-full bg-gray-600 rounded-full h-2">
          <div class="bg-indigo-600 h-2 rounded-full transition-all"
               :style="{ width: uploadProgress + '%' }"></div>
        </div>
      </div>
    </div>

    <div v-if="form.uploaded_content.length" class="mt-4 space-y-2">
      <h4 class="text-sm font-medium text-gray-300">Uploaded Content ({{ form.uploaded_content.length }})</h4>
      <div v-for="(item, index) in form.uploaded_content" :key="item.id || index"
           class="flex items-center justify-between p-3 bg-gray-700 rounded-lg">
        <div>
          <span class="text-white font-medium">{{ item.title || item.file_name }}</span>
          <span class="text-xs text-gray-400 block">
            {{ item.quality_level }} • {{ formatDuration(item.duration) }} • {{ formatSize(item.file_size) }}
          </span>
        </div>
        <div class="flex items-center gap-2">
          <span :class="qualityBadge(item.quality_level)">{{ item.quality_level.toUpperCase() }}</span>
          <button type="button" @click="removeUploaded(index)"
            class="text-red-400 hover:text-red-300 text-xs px-2 py-1">Remove</button>
        </div>
      </div>
    </div>

    <div v-else class="text-xs text-gray-400 mt-2">
      No content uploaded yet. Add content to build your playlist.
    </div>

    <div class="bg-gray-700/30 rounded-lg p-4 border border-gray-600 mt-4">
      <h4 class="text-sm font-medium text-gray-300 mb-2">Playlist Settings</h4>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <label class="flex items-center gap-2 cursor-pointer text-gray-300">
          <input type="checkbox" v-model="form.loop_playlist"
            class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600" />
          <span>Continuous loop</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer text-gray-300">
          <input type="checkbox" v-model="form.shuffle_mode"
            class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600" />
          <span>Shuffle mode</span>
        </label>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Upload } from 'lucide-vue-next'

const props = defineProps({
  form: { type: Object, required: true },
  uploading: { type: Boolean, default: false },
  uploadProgress: { type: Number, default: 0 },
  handleFileUpload: { type: Function, required: true },
  removeUploaded: { type: Function, required: true },
  editing: { type: Boolean, default: false },
})

const fileInput = ref(null)

const formatDuration = (seconds) => {
  if (!seconds) return '0s'
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${m}m ${s}s`
}

const formatSize = (bytes) => {
  if (!bytes) return '0 B'
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / 1048576).toFixed(1) + ' MB'
}

const qualityBadge = (q) => {
  const badges = {
    '4k': 'text-purple-400',
    'fhd': 'text-blue-400',
    'hd': 'text-green-400',
    'sd': 'text-yellow-400',
    'low': 'text-gray-400',
  }
  return badges[q] || 'text-gray-400'
}

const settings = undefined // removed — form bindings used directly
</script>
