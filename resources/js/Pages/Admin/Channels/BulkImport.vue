<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto">
      <div class="mb-6">
        <Link :href="route('admin.channels.index')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
          <ArrowLeft class="w-4 h-4" /> Back to Channels
        </Link>
        <h1 class="text-2xl font-bold text-white">Import Channels</h1>
      </div>

      <form @submit.prevent="submitImport" class="space-y-6">
        <!-- Import Source -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h2 class="text-lg font-semibold text-white mb-4">Import Source</h2>

          <!-- Source Type Tabs -->
          <div class="flex gap-4 mb-4">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" v-model="sourceType" value="file"
                class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 focus:ring-indigo-500" />
              <span class="text-gray-300 text-sm">Upload File</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" v-model="sourceType" value="url"
                class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 focus:ring-indigo-500" />
              <span class="text-gray-300 text-sm">URL / API</span>
            </label>
          </div>

          <!-- File Upload -->
          <div v-if="sourceType === 'file'">
            <div
              class="border-2 border-dashed border-gray-700 hover:border-gray-600 rounded-lg p-8 text-center cursor-pointer transition"
              @dragover.prevent="isDragging = true"
              @dragleave="isDragging = false"
              @drop.prevent="onDrop"
              :class="isDragging ? 'border-indigo-500 bg-indigo-500/10' : ''"
            >
              <input type="file" ref="fileInput" accept=".m3u,.m3u8,.csv,.xml,.xmltv" class="hidden" @change="onFileSelect" />
              <div v-if="!selectedFile">
                <Upload class="w-12 h-12 mx-auto text-gray-500 mb-3" />
                <p class="text-gray-400 text-sm">
                  <span class="text-indigo-400 font-medium cursor-pointer" @click="$refs.fileInput.click()">Click to upload</span>
                  or drag and drop
                </p>
                <p class="text-gray-500 text-xs mt-1">M3U, CSV, or XMLTV file</p>
              </div>
              <div v-else class="flex items-center justify-center gap-3">
                <FileText class="w-6 h-6 text-indigo-400" />
                <span class="text-gray-300 text-sm">{{ selectedFile.name }}</span>
                <span class="text-gray-500 text-xs">({{ formatSize(selectedFile.size) }})</span>
                <button type="button" @click="selectedFile = null" class="text-red-400 hover:text-red-300 text-sm">Remove</button>
              </div>
            </div>
          </div>

          <!-- URL Input -->
          <div v-else>
            <label class="block text-sm font-medium text-gray-300 mb-2">M3U / XMLTV URL</label>
            <input v-model="importUrl" type="url" placeholder="https://example.com/channels.m3u"
              class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>
        </div>

        <!-- Import Options -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h2 class="text-lg font-semibold text-white mb-4">Import Options</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="skipDuplicates"
                class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <span class="text-gray-300 text-sm">Skip Existing Channels</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="overwriteDetails"
                class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <span class="text-gray-300 text-sm">Overwrite Channel Details</span>
            </label>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Assign to Category</label>
              <select v-model="defaultCategory"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option :value="null">None</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Assign to Bouquet</label>
              <select v-model="defaultBouquet"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option :value="null">None</option>
                <option v-for="b in bouquets" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
            </div>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="useFileCategories"
                class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <span class="text-gray-300 text-sm">Use File Categories</span>
            </label>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Default Language</label>
              <select v-model="defaultLanguage"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="">None</option>
                <option value="en">English</option>
                <option value="es">Spanish</option>
                <option value="fr">French</option>
                <option value="de">German</option>
                <option value="it">Italian</option>
                <option value="pt">Portuguese</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Preview -->
        <div v-if="previewData" class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h2 class="text-lg font-semibold text-white mb-4">Preview</h2>
          <div class="flex gap-4 mb-4 text-sm">
            <span class="text-gray-400">Found <strong class="text-white">{{ previewData.total }}</strong> channels in file</span>
            <span class="text-green-400">New: {{ previewData.new }}</span>
            <span class="text-yellow-400">Existing: {{ previewData.existing }}</span>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="text-left text-xs font-semibold text-gray-400 uppercase">
                  <th class="pb-2 w-8">#</th>
                  <th class="pb-2">Channel Name</th>
                  <th class="pb-2">Category</th>
                  <th class="pb-2">Action</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in previewData.items" :key="index"
                  class="border-t border-gray-700">
                  <td class="py-2 text-sm text-gray-500">{{ index + 1 }}</td>
                  <td class="py-2 text-sm text-gray-300">{{ item.name }}</td>
                  <td class="py-2 text-sm text-gray-400">{{ item.category }}</td>
                  <td class="py-2">
                    <span v-if="item.action === 'import'"
                      class="px-2 py-0.5 text-xs rounded-full bg-green-500/20 text-green-400">Import</span>
                    <span v-else-if="item.action === 'skip'"
                      class="px-2 py-0.5 text-xs rounded-full bg-yellow-500/20 text-yellow-400">Existing</span>
                    <span v-else-if="item.action === 'update'"
                      class="px-2 py-0.5 text-xs rounded-full bg-blue-500/20 text-blue-400">Update</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Form Actions -->
        <div class="flex justify-end gap-3">
          <Link :href="route('admin.channels.index')"
            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">
            Cancel
          </Link>
          <button type="submit" :disabled="!canImport || importing"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50 flex items-center gap-2">
            <span v-if="importing" class="animate-spin">⟳</span>
            <span>{{ importing ? 'Importing...' : 'Import Channels' }}</span>
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeft, Upload, FileText } from 'lucide-vue-next'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  bouquets: { type: Array, default: () => [] },
})

const sourceType = ref('file')
const selectedFile = ref(null)
const importUrl = ref('')
const isDragging = ref(false)
const fileInput = ref(null)
const importing = ref(false)
const previewData = ref(null)

const skipDuplicates = ref(true)
const overwriteDetails = ref(false)
const defaultCategory = ref(null)
const defaultBouquet = ref(null)
const useFileCategories = ref(true)
const defaultLanguage = ref('')

const canImport = computed(() => {
  if (sourceType.value === 'file') return !!selectedFile.value
  return !!importUrl.value
})

const formatSize = (bytes) => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const onFileSelect = (event) => {
  const file = event.target.files[0]
  if (file) selectedFile.value = file
}

const onDrop = (event) => {
  isDragging.value = false
  const file = event.dataTransfer.files[0]
  if (file) selectedFile.value = file
}

const submitImport = () => {
  if (!canImport.value) return
  importing.value = true

  const formData = new FormData()
  if (sourceType.value === 'file' && selectedFile.value) {
    formData.append('file', selectedFile.value)
  } else if (sourceType.value === 'url' && importUrl.value) {
    formData.append('url', importUrl.value)
  }
  formData.append('skip_duplicates', skipDuplicates.value ? '1' : '0')
  formData.append('overwrite_details', overwriteDetails.value ? '1' : '0')
  formData.append('use_file_categories', useFileCategories.value ? '1' : '0')
  if (defaultCategory.value) formData.append('default_category', defaultCategory.value)
  if (defaultBouquet.value) formData.append('default_bouquet', defaultBouquet.value)
  if (defaultLanguage.value) formData.append('default_language', defaultLanguage.value)

  router.post(route('admin.channels.import.store'), formData, {
    onSuccess: () => {
      importing.value = false
    },
    onError: () => {
      importing.value = false
    },
  })
}
</script>