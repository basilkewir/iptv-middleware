<template>
  <AdminLayout>
    <div class="p-6 max-w-3xl mx-auto space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <Link :href="route('admin.vod.index')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
            <ArrowLeft class="w-4 h-4" /> Back to VOD
          </Link>
          <h1 class="text-2xl font-bold text-white">Bulk Import VOD Content</h1>
          <p class="text-gray-400 mt-1">Import movies, series and other video content in bulk</p>
        </div>
      </div>

      <!-- Import Method Tabs -->
      <div class="flex gap-2 bg-gray-800 rounded-xl p-1 border border-gray-700">
        <button
          v-for="tab in tabs" :key="tab.id"
          @click="importMethod = tab.id"
          class="flex-1 px-4 py-2.5 rounded-lg text-sm font-medium transition flex items-center justify-center gap-2"
          :class="importMethod === tab.id ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-700'"
        >
          <component :is="tab.icon" class="w-4 h-4" />
          {{ tab.label }}
        </button>
      </div>

      <!-- File Upload Tab -->
      <div v-if="importMethod === 'file'" class="bg-gray-800 rounded-xl p-6 border border-gray-700 space-y-6">
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Import Format</label>
          <div class="grid grid-cols-4 gap-3">
            <label v-for="fmt in formats" :key="fmt.id" class="flex flex-col items-center gap-2 p-3 rounded-lg border cursor-pointer transition"
              :class="format === fmt.id ? 'border-indigo-500 bg-indigo-500/10' : 'border-gray-600 hover:border-gray-500'"
            >
              <input v-model="format" type="radio" :value="fmt.id" class="hidden" />
              <component :is="fmt.icon" class="w-6 h-6" :class="format === fmt.id ? 'text-indigo-400' : 'text-gray-400'" />
              <span class="text-xs" :class="format === fmt.id ? 'text-indigo-400' : 'text-gray-300'">{{ fmt.label }}</span>
            </label>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Upload File</label>
          <div
            class="border-2 border-dashed rounded-xl p-10 text-center transition cursor-pointer"
            :class="dragOver ? 'border-indigo-500 bg-indigo-500/5' : selectedFile ? 'border-green-500 bg-green-500/5' : 'border-gray-600 hover:border-indigo-500'"
            @click="$refs.fileInput.click()"
            @dragover.prevent="dragOver = true"
            @dragleave="dragOver = false"
            @drop.prevent="handleDrop"
          >
            <div v-if="!selectedFile">
              <Upload class="w-14 h-14 mx-auto mb-3 text-gray-500" />
              <p class="text-gray-300 font-medium">Click to upload or drag and drop</p>
              <p class="text-gray-500 text-sm mt-1">{{ acceptedExtensions }} files, max 50MB</p>
            </div>
            <div v-else class="flex items-center justify-center gap-4">
              <div class="p-3 bg-green-500/20 rounded-lg">
                <FileCheck class="w-8 h-8 text-green-400" />
              </div>
              <div class="text-left">
                <p class="text-white font-medium">{{ selectedFile.name }}</p>
                <p class="text-gray-400 text-sm">{{ formatFileSize(selectedFile.size) }}</p>
              </div>
              <button @click.stop="selectedFile = null" class="p-1 text-gray-400 hover:text-red-400 transition">
                <X class="w-5 h-5" />
              </button>
            </div>
          </div>
          <input ref="fileInput" type="file" :accept="acceptedExtensions" class="hidden" @change="handleFileSelect" />
        </div>

        <!-- Import Settings -->
        <div class="bg-gray-700/50 rounded-lg p-4 space-y-4">
          <h3 class="text-white font-medium">Import Settings</h3>
          <div class="grid grid-cols-2 gap-4">
            <div class="flex items-center gap-3">
              <input v-model="settings.skipDuplicates" type="checkbox" id="skip_dupes" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="skip_dupes" class="text-gray-300 text-sm">Skip duplicates</label>
            </div>
            <div class="flex items-center gap-3">
              <input v-model="settings.autoAssignBouquets" type="checkbox" id="auto_bouquets" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="auto_bouquets" class="text-gray-300 text-sm">Auto-assign bouquets</label>
            </div>
            <div>
              <label class="block text-sm text-gray-400 mb-1">Default Bouquet</label>
              <select v-model="settings.defaultBouquet" class="w-full px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm focus:outline-none focus:border-indigo-500">
                <option value="">None</option>
                <option v-for="b in bouquets" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm text-gray-400 mb-1">Default Category</label>
              <select v-model="settings.defaultCategory" class="w-full px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm focus:outline-none focus:border-indigo-500">
                <option value="">None</option>
                <option value="movie">Movie</option>
                <option value="series">Series</option>
                <option value="documentary">Documentary</option>
                <option value="kids">Kids</option>
                <option value="sports">Sports</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Format Example -->
        <div class="bg-gray-700/50 rounded-lg p-4">
          <div class="flex items-center justify-between mb-2">
            <h3 class="text-white font-medium">Expected Format</h3>
            <button @click="showExample = !showExample" class="text-indigo-400 text-sm hover:text-indigo-300 transition">
              {{ showExample ? 'Hide' : 'Show' }} Example
            </button>
          </div>
          <div v-if="showExample" class="mt-3">
            <pre class="text-gray-400 text-xs overflow-x-auto bg-gray-900 rounded-lg p-4">{{ formatExample }}</pre>
          </div>
        </div>

        <!-- Progress -->
        <div v-if="processing" class="bg-gray-700/50 rounded-lg p-4">
          <div class="flex items-center justify-between mb-2">
            <span class="text-white text-sm">Importing content...</span>
            <span class="text-indigo-400 text-sm">{{ progress }}%</span>
          </div>
          <div class="w-full bg-gray-700 rounded-full h-2">
            <div class="h-2 rounded-full bg-indigo-500 transition-all" :style="{ width: progress + '%' }" />
          </div>
        </div>

        <!-- Result -->
        <div v-if="importResult" class="p-4 rounded-lg flex items-start gap-3" :class="importResult.success ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'">
          <CheckCircle v-if="importResult.success" class="w-5 h-5 mt-0.5 flex-shrink-0" />
          <AlertCircle v-else class="w-5 h-5 mt-0.5 flex-shrink-0" />
          <div>
            <p class="font-medium">{{ importResult.message }}</p>
            <p v-if="importResult.details" class="text-sm mt-1 opacity-80">{{ importResult.details }}</p>
          </div>
        </div>

        <div class="flex justify-end gap-3">
          <Link :href="route('admin.vod.index')" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">Cancel</Link>
          <button @click="importFile" :disabled="!selectedFile || processing" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50 flex items-center gap-2">
            <Upload class="w-4 h-4" />
            {{ processing ? 'Importing...' : 'Import Content' }}
          </button>
        </div>
      </div>

      <!-- URL Import Tab -->
      <div v-if="importMethod === 'url'" class="bg-gray-800 rounded-xl p-6 border border-gray-700 space-y-6">
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Content URL</label>
          <input v-model="importUrl" type="url" placeholder="https://example.com/content.json" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500" />
          <p class="text-gray-500 text-xs mt-1">URL to a JSON, XML or M3U file containing VOD content</p>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Content Type</label>
            <select v-model="importUrlType" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
              <option value="auto">Auto-detect</option>
              <option value="json">JSON</option>
              <option value="xml">XML</option>
              <option value="m3u">M3U / M3U8</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Authentication</label>
            <select v-model="importUrlAuth" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
              <option value="none">None</option>
              <option value="basic">Basic Auth</option>
              <option value="bearer">Bearer Token</option>
            </select>
          </div>
        </div>
        <div v-if="importUrlAuth !== 'none'" class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Username</label>
            <input v-model="importUrlUser" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Password / Token</label>
            <input v-model="importUrlPass" type="password" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500" />
          </div>
        </div>
        <div class="flex justify-end gap-3">
          <button @click="importFromUrl" :disabled="!importUrl || processing" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50 flex items-center gap-2">
            <Download class="w-4 h-4" />
            {{ processing ? 'Importing...' : 'Import from URL' }}
          </button>
        </div>
      </div>

      <!-- Xtream API Tab -->
      <div v-if="importMethod === 'xtream'" class="bg-gray-800 rounded-xl p-6 border border-gray-700 space-y-6">
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Xtream API URL</label>
          <input v-model="xtream.url" type="url" placeholder="http://server:port" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Username</label>
            <input v-model="xtream.username" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Password</label>
            <input v-model="xtream.password" type="password" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Content to Import</label>
          <div class="grid grid-cols-3 gap-3">
            <label class="flex items-center gap-2 cursor-pointer p-2 rounded-lg border" :class="xtream.vod ? 'border-indigo-500 bg-indigo-500/10' : 'border-gray-600'">
              <input v-model="xtream.vod" type="checkbox" class="rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <span class="text-gray-300 text-sm">VOD</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer p-2 rounded-lg border" :class="xtream.series ? 'border-indigo-500 bg-indigo-500/10' : 'border-gray-600'">
              <input v-model="xtream.series" type="checkbox" class="rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <span class="text-gray-300 text-sm">Series</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer p-2 rounded-lg border" :class="xtream.live ? 'border-indigo-500 bg-indigo-500/10' : 'border-gray-600'">
              <input v-model="xtream.live" type="checkbox" class="rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <span class="text-gray-300 text-sm">Live TV</span>
            </label>
          </div>
        </div>
        <div class="flex justify-end gap-3">
          <button @click="importFromXtream" :disabled="!xtream.url || !xtream.username || processing" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50 flex items-center gap-2">
            <Radio class="w-4 h-4" />
            {{ processing ? 'Importing...' : 'Import from Xtream' }}
          </button>
        </div>
      </div>

      <!-- CSV Import Tab -->
      <div v-if="importMethod === 'csv'" class="bg-gray-800 rounded-xl p-6 border border-gray-700 space-y-6">
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">CSV File</label>
          <div
            class="border-2 border-dashed rounded-xl p-10 text-center transition cursor-pointer"
            :class="csvFile ? 'border-green-500 bg-green-500/5' : 'border-gray-600 hover:border-indigo-500'"
            @click="$refs.csvInput.click()"
          >
            <div v-if="!csvFile">
              <FileSpreadsheet class="w-14 h-14 mx-auto mb-3 text-gray-500" />
              <p class="text-gray-300 font-medium">Upload CSV file</p>
              <p class="text-gray-500 text-sm mt-1">.csv files only</p>
            </div>
            <div v-else class="flex items-center justify-center gap-4">
              <FileCheck class="w-8 h-8 text-green-400" />
              <p class="text-white">{{ csvFile.name }}</p>
              <button @click.stop="csvFile = null" class="text-gray-400 hover:text-red-400"><X class="w-5 h-5" /></button>
            </div>
          </div>
          <input ref="csvInput" type="file" accept=".csv" class="hidden" @change="e => csvFile = e.target.files[0]" />
        </div>
        <div class="bg-gray-700/50 rounded-lg p-4">
          <h3 class="text-white font-medium mb-2">Required Columns</h3>
          <p class="text-gray-400 text-sm">title, stream_url, type (movie/series)</p>
        </div>
        <div class="flex justify-end gap-3">
          <button @click="importCsv" :disabled="!csvFile || processing" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50 flex items-center gap-2">
            <FileSpreadsheet class="w-4 h-4" />
            {{ processing ? 'Importing...' : 'Import CSV' }}
          </button>
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
  ArrowLeft, Upload, FileCheck, X, Download, Radio, FileSpreadsheet,
  FileJson, FileCode, Music, CheckCircle, AlertCircle
} from 'lucide-vue-next'

const props = defineProps({
  bouquets: { type: Array, default: () => [] }
})

const importMethod = ref('file')
const format = ref('json')
const selectedFile = ref(null)
const csvFile = ref(null)
const processing = ref(false)
const importResult = ref(null)
const progress = ref(0)
const showExample = ref(false)
const dragOver = ref(false)
const importUrl = ref('')
const importUrlType = ref('auto')
const importUrlAuth = ref('none')
const importUrlUser = ref('')
const importUrlPass = ref('')
const xtream = ref({ url: '', username: '', password: '', vod: true, series: false, live: false })
const settings = ref({ skipDuplicates: true, autoAssignBouquets: false, defaultBouquet: '', defaultCategory: '' })

const tabs = [
  { id: 'file', label: 'File Upload', icon: Upload },
  { id: 'url', label: 'URL Import', icon: Download },
  { id: 'xtream', label: 'Xtream API', icon: Radio },
  { id: 'csv', label: 'CSV Import', icon: FileSpreadsheet },
]

const formats = [
  { id: 'json', label: 'JSON', icon: FileJson },
  { id: 'xml', label: 'XML', icon: FileCode },
  { id: 'm3u', label: 'M3U', icon: Music },
]

const acceptedExtensions = computed(() => {
  const map = { json: '.json', xml: '.xml', m3u: '.m3u,.m3u8' }
  return map[format.value] || '.json'
})

const formatExample = computed(() => {
  const examples = {
    json: `[
  {
    "title": "Movie Title",
    "type": "movie",
    "year": 2024,
    "genre": "Action",
    "stream_url": "http://...",
    "poster": "http://...",
    "rating": 8.5,
    "duration": "2h 15m"
  }
]`,
    xml: `<?xml version="1.0"?>
<vod>
  <item>
    <title>Movie Title</title>
    <type>movie</type>
    <year>2024</year>
    <stream_url>http://...</stream_url>
  </item>
</vod>`,
    m3u: `#EXTM3U
#EXTINF:-1 tvg-logo="http://poster.jpg" group-title="Action", Movie Title (2024)
http://server/movie.mp4`
  }
  return examples[format.value] || examples.json
})

const formatFileSize = (bytes) => {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / 1048576).toFixed(1) + ' MB'
}

const handleFileSelect = (e) => { selectedFile.value = e.target.files[0] }
const handleDrop = (e) => { dragOver.value = false; selectedFile.value = e.dataTransfer.files[0] }

const importFile = () => {
  processing.value = true
  progress.value = 0
  importResult.value = null
  const formData = new FormData()
  formData.append('file', selectedFile.value)
  formData.append('format', format.value)
  Object.entries(settings.value).forEach(([k, v]) => formData.append(k, v))

  router.post(route('admin.vod.import.store'), formData, {
    onProgress: (e) => { progress.value = e.percentage },
    onFinish: () => { processing.value = false },
    onSuccess: () => { importResult.value = { success: true, message: 'Import completed successfully!', details: 'Content has been imported and is ready to use.' } },
    onError: () => { importResult.value = { success: false, message: 'Import failed. Please check your file format and try again.' } },
  })
}

const importFromUrl = () => {
  processing.value = true
  importResult.value = null
  router.post(route('admin.vod.import.url'), {
    url: importUrl.value,
    type: importUrlType.value,
    auth: importUrlAuth.value,
    username: importUrlUser.value,
    password: importUrlPass.value,
    ...settings.value,
  }, {
    onFinish: () => { processing.value = false },
    onSuccess: () => { importResult.value = { success: true, message: 'URL import completed successfully!' } },
    onError: () => { importResult.value = { success: false, message: 'Failed to import from URL.' } },
  })
}

const importFromXtream = () => {
  processing.value = true
  importResult.value = null
  router.post(route('admin.vod.import.xtream'), {
    ...xtream.value,
    ...settings.value,
  }, {
    onFinish: () => { processing.value = false },
    onSuccess: () => { importResult.value = { success: true, message: 'Xtream API import completed successfully!' } },
    onError: () => { importResult.value = { success: false, message: 'Failed to import from Xtream API.' } },
  })
}

const importCsv = () => {
  processing.value = true
  importResult.value = null
  const formData = new FormData()
  formData.append('file', csvFile.value)
  router.post(route('admin.vod.import.store'), formData, {
    onFinish: () => { processing.value = false },
    onSuccess: () => { importResult.value = { success: true, message: 'CSV import completed successfully!' } },
    onError: () => { importResult.value = { success: false, message: 'Failed to import CSV.' } },
  })
}
</script>
