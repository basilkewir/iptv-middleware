<template>
  <AdminLayout>
    <div class="p-6">
      <div class="flex items-center gap-3 mb-6">
        <Link :href="route('admin.clients.index')" class="text-gray-400 hover:text-white">
          <ArrowLeft class="w-5 h-5" />
        </Link>
        <div>
          <h1 class="text-2xl font-bold text-white">Bulk Import Clients</h1>
          <p class="text-gray-400 text-sm mt-1">Import multiple clients from a CSV file</p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Instructions -->
        <div class="card lg:col-span-1">
          <h2 class="text-lg font-semibold text-white mb-4">Instructions</h2>
          <ul class="space-y-3 text-sm text-gray-400">
            <li class="flex gap-2">
              <FileText class="w-5 h-5 text-purple-400 flex-shrink-0" />
              <span>Prepare a CSV file with the required columns</span>
            </li>
            <li class="flex gap-2">
              <Download class="w-5 h-5 text-purple-400 flex-shrink-0" />
              <span>Download the template to see the correct format</span>
            </li>
            <li class="flex gap-2">
              <Upload class="w-5 h-5 text-purple-400 flex-shrink-0" />
              <span>Upload your CSV file below</span>
            </li>
            <li class="flex gap-2">
              <CheckCircle class="w-5 h-5 text-purple-400 flex-shrink-0" />
              <span>Review validation results and confirm import</span>
            </li>
          </ul>

          <a :href="route('admin.clients.bulkTemplate')" class="btn-primary mt-6 w-full">
            <Download class="w-4 h-4 inline mr-1" /> Download CSV Template
          </a>

          <div class="mt-4 p-3 bg-gray-800 rounded-lg">
            <p class="text-gray-400 text-xs font-medium mb-2">Required Columns:</p>
            <code class="text-green-400 text-xs block">username, password, email</code>
            <p class="text-gray-500 text-xs mt-2">Optional: first_name, last_name, package, max_connections, expiry_date, mac_address, country</p>
          </div>
        </div>

        <!-- Upload Form -->
        <div class="card lg:col-span-2">
          <h2 class="text-lg font-semibold text-white mb-4">Upload CSV File</h2>
          <form @submit.prevent="uploadFile" class="space-y-4">
            <!-- Drop Zone -->
            <div
              class="border-2 border-dashed rounded-xl p-8 text-center cursor-pointer transition-colors"
              :class="dragOver ? 'border-purple-500 bg-purple-500/5' : 'border-gray-600 hover:border-gray-500'"
              @dragover.prevent="dragOver = true"
              @dragleave="dragOver = false"
              @drop.prevent="handleDrop"
              @click="$refs.fileInput.click()"
            >
              <Upload v-if="!file" class="w-12 h-12 text-gray-500 mx-auto mb-3" />
              <FileText v-else class="w-12 h-12 text-purple-400 mx-auto mb-3" />
              <p v-if="!file" class="text-gray-400">Drag & drop your CSV file here, or click to browse</p>
              <p v-else class="text-white font-medium">{{ file.name }}</p>
              <p v-if="file" class="text-gray-500 text-sm mt-1">{{ formatFileSize(file.size) }}</p>
              <input ref="fileInput" type="file" accept=".csv" class="hidden" @change="handleFileChange" />
            </div>
            <p v-if="form.errors.file" class="text-red-400 text-xs">{{ form.errors.file }}</p>

            <!-- Options -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Default Package</label>
                <select v-model="form.default_package" class="input-field">
                  <option value="">Select default package</option>
                  <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">{{ pkg.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Default Expiry Date</label>
                <input v-model="form.default_expiry" type="date" class="input-field" :min="minDate" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Default Max Connections</label>
                <input v-model="form.default_max_connections" type="number" class="input-field" min="1" max="100" placeholder="2" />
              </div>
              <div class="flex items-end">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="checkbox" v-model="form.skip_duplicates" class="w-4 h-4 rounded bg-gray-600 text-purple-600" />
                  <span class="text-sm text-gray-300">Skip duplicate usernames</span>
                </label>
              </div>
            </div>

            <button type="submit" class="btn-primary" :disabled="form.processing || !file">
              <Loader v-if="form.processing" class="w-4 h-4 animate-spin inline mr-1" />
              Upload & Import
            </button>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeft, Download, Upload, FileText, CheckCircle, Loader } from 'lucide-vue-next'

const props = defineProps({
  packages: { type: Array, default: () => [] },
})

const dragOver = ref(false)
const file = ref(null)

const form = useForm({
  file: null,
  default_package: '',
  default_expiry: '',
  default_max_connections: '',
  skip_duplicates: true,
})

const minDate = computed(() => {
  const d = new Date()
  d.setDate(d.getDate() + 1)
  return d.toISOString().split('T')[0]
})

const handleFileChange = (e) => {
  file.value = e.target.files[0] || null
}

const handleDrop = (e) => {
  dragOver.value = false
  const droppedFile = e.dataTransfer.files[0]
  if (droppedFile && droppedFile.type === 'text/csv') {
    file.value = droppedFile
  }
}

const formatFileSize = (bytes) => {
  if (bytes < 1024) return bytes + ' bytes'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

const uploadFile = () => {
  if (!file.value) return
  form.file = file.value
  form.post(route('admin.clients.bulkImport'), {
    onSuccess: () => {
      file.value = null
    }
  })
}
</script>