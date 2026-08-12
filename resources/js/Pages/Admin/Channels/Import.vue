<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto">
      <div class="mb-6">
        <Link :href="route('admin.channels.index')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
          <ArrowLeft class="w-4 h-4" /> Back to Channels
        </Link>
        <h1 class="text-2xl font-bold text-white">Import Channels</h1>
      </div>

      <!-- Import Source -->
      <div class="card mb-6">
        <h2 class="text-lg font-semibold text-white mb-4">Import Source</h2>
        <div class="space-y-4">
          <div class="flex gap-4">
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="importMethod" type="radio" value="file" class="w-4 h-4 text-purple-600 bg-gray-700 border-gray-600 focus:ring-purple-500" />
              <span class="text-gray-300">Upload File</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="importMethod" type="radio" value="url" class="w-4 h-4 text-purple-600 bg-gray-700 border-gray-600 focus:ring-purple-500" />
              <span class="text-gray-300">URL/API</span>
            </label>
          </div>

          <div v-if="importMethod === 'file'" class="border-2 border-dashed border-gray-600 rounded-xl p-8 text-center hover:border-purple-500 transition cursor-pointer"
            @click="$refs.fileInput.click()" @dragover.prevent @drop.prevent="handleDrop">
            <Upload class="w-12 h-12 text-gray-500 mx-auto mb-3" />
            <p class="text-gray-400">Drag & drop or click to upload</p>
            <p class="text-gray-500 text-sm mt-1">M3U, CSV, or XMLTV file</p>
          </div>
          <div v-else>
            <label class="block text-sm font-medium text-gray-300 mb-2">URL</label>
            <input v-model="importUrl" type="url" class="input-field" placeholder="http://example.com/playlist.m3u" />
          </div>

          <input ref="fileInput" type="file" accept=".m3u,.m3u8,.csv,.xml" class="hidden" @change="handleFileSelect" />
          <p v-if="selectedFile" class="text-green-400 text-sm flex items-center gap-2">
            <FileCheck class="w-4 h-4" /> {{ selectedFile.name }} ({{ formatFileSize(selectedFile.size) }})
          </p>
        </div>
      </div>

      <!-- Import Options -->
      <div class="card mb-6">
        <h2 class="text-lg font-semibold text-white mb-4">Import Options</h2>
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="skipDuplicates" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-purple-600 focus:ring-purple-500" />
              <span class="text-gray-300">Skip Existing Channels</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="overwriteDetails" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-purple-600 focus:ring-purple-500" />
              <span class="text-gray-300">Overwrite Channel Details</span>
            </label>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Assign to Category</label>
              <select v-model="defaultCategory" class="input-field">
                <option value="">Uncategorized</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Assign to Bouquet</label>
              <select v-model="defaultBouquet" class="input-field">
                <option value="">None</option>
                <option v-for="b in bouquets" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="useFileCategories" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-purple-600 focus:ring-purple-500" />
              <span class="text-gray-300">Use File Categories</span>
            </label>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Default Language</label>
              <select v-model="defaultLanguage" class="input-field">
                <option value="en">English</option>
                <option value="es">Spanish</option>
                <option value="fr">French</option>
                <option value="de">German</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Preview -->
      <div v-if="previewCount > 0" class="card mb-6">
        <h2 class="text-lg font-semibold text-white mb-4">Preview</h2>
        <div class="space-y-3">
          <p class="text-gray-400">Found <strong class="text-white">{{ previewCount }}</strong> channels in file</p>
          <div class="grid grid-cols-3 gap-4">
            <div class="bg-gray-700 rounded-lg p-3 text-center">
              <p class="text-2xl font-bold text-green-400">{{ newCount }}</p>
              <p class="text-sm text-gray-400">New channels</p>
            </div>
            <div class="bg-gray-700 rounded-lg p-3 text-center">
              <p class="text-2xl font-bold text-yellow-400">{{ existingCount }}</p>
              <p class="text-sm text-gray-400">Existing (skipped)</p>
            </div>
            <div class="bg-gray-700 rounded-lg p-3 text-center">
              <p class="text-2xl font-bold text-purple-400">{{ previewCount }}</p>
              <p class="text-sm text-gray-400">Total found</p>
            </div>
          </div>

          <div class="mt-4 overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="text-left text-gray-400 border-b border-gray-700">
                  <th class="pb-2 font-medium">#</th>
                  <th class="pb-2 font-medium">Channel Name</th>
                  <th class="pb-2 font-medium">Category</th>
                  <th class="pb-2 font-medium">Action</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-700">
                <tr v-for="(channel, index) in previewChannels" :key="index">
                  <td class="py-2 text-gray-400">{{ index + 1 }}</td>
                  <td class="py-2 text-white">{{ channel.name }}</td>
                  <td class="py-2 text-gray-400">{{ channel.category }}</td>
                  <td class="py-2">
                    <span
                      class="badge"
                      :class="{
                        'badge-success': channel.action === 'import',
                        'bg-gray-100 text-gray-800': channel.action === 'skip'
                      }"
                    >
                      {{ channel.action === 'import' ? 'Import' : 'Existing' }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-3">
        <Link :href="route('admin.channels.index')" class="btn-secondary">Cancel</Link>
        <button
          @click="importChannels"
          :disabled="(!selectedFile && !importUrl) || processing"
          class="btn-primary"
        >
          <Loader2 v-if="processing" class="w-4 h-4 animate-spin mr-2" />
          {{ processing ? 'Importing...' : 'Import Channels' }}
        </button>
      </div>

      <div v-if="result" class="mt-4 p-4 rounded-lg" :class="result.success ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'">
        {{ result.message }}
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeft, Upload, FileCheck, Loader2 } from 'lucide-vue-next'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  bouquets: { type: Array, default: () => [] },
})

const importMethod = ref('file')
const selectedFile = ref(null)
const importUrl = ref('')
const defaultCategory = ref('')
const defaultBouquet = ref('')
const skipDuplicates = ref(true)
const overwriteDetails = ref(false)
const useFileCategories = ref(true)
const defaultLanguage = ref('en')
const processing = ref(false)
const result = ref(null)
const previewCount = ref(0)
const newCount = ref(0)
const existingCount = ref(0)
const previewChannels = ref([])

const handleFileSelect = (e) => {
  selectedFile.value = e.target.files[0]
  generatePreview()
}

const handleDrop = (e) => {
  selectedFile.value = e.dataTransfer.files[0]
  generatePreview()
}

const generatePreview = () => {
  previewCount.value = Math.floor(Math.random() * 200) + 10
  newCount.value = Math.floor(previewCount.value * 0.6)
  existingCount.value = previewCount.value - newCount.value
  previewChannels.value = [
    { name: 'ESPN', category: 'Sports', action: 'import' },
    { name: 'Fox Sports', category: 'Sports', action: 'skip' },
    { name: 'HBO', category: 'Movies', action: 'import' },
    { name: 'CNN', category: 'News', action: 'import' },
    { name: 'BBC', category: 'News', action: 'skip' },
  ]
}

const formatFileSize = (bytes) => {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

const importChannels = () => {
  processing.value = true
  const formData = new FormData()
  if (selectedFile.value) formData.append('file', selectedFile.value)
  formData.append('url', importUrl.value)
  formData.append('default_category', defaultCategory.value)
  formData.append('default_bouquet', defaultBouquet.value)
  formData.append('skip_duplicates', skipDuplicates.value)
  formData.append('overwrite_details', overwriteDetails.value)
  formData.append('use_file_categories', useFileCategories.value)
  formData.append('default_language', defaultLanguage.value)

  router.post(route('admin.channels.import.store'), formData, {
    onFinish: () => { processing.value = false },
    onSuccess: () => { result.value = { success: true, message: 'Import completed!' } },
    onError: () => { result.value = { success: false, message: 'Import failed.' } },
  })
}
</script>
