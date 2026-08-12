<template>
  <AdminLayout>
    <div class="p-6 max-w-3xl mx-auto">
      <div class="mb-6">
        <Link :href="route('admin.users.index')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
          <ArrowLeft class="w-4 h-4" /> Back to Users
        </Link>
        <h1 class="text-2xl font-bold text-white">Bulk Create Clients</h1>
        <p class="text-gray-400 mt-1">Import multiple client accounts at once</p>
      </div>

      <!-- Step 1: Download Template -->
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 mb-6">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white font-bold text-sm">1</div>
          <h2 class="text-white font-semibold">Download Template</h2>
        </div>
        <p class="text-gray-400 text-sm mb-3">Download the CSV template with the required columns.</p>
        <a :href="route('admin.users.bulk.template')" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">
          <Download class="w-4 h-4" /> Download CSV Template
        </a>
        <p class="text-gray-500 text-xs mt-2">Format: username, password, email, package, max_connections, expiry_date</p>
      </div>

      <!-- Step 2: Upload File -->
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 mb-6">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white font-bold text-sm">2</div>
          <h2 class="text-white font-semibold">Upload File</h2>
        </div>
        <div class="border-2 border-dashed border-gray-600 rounded-xl p-8 text-center hover:border-indigo-500 transition cursor-pointer"
          @click="$refs.fileInput.click()" @dragover.prevent @drop.prevent="handleDrop">
          <Upload class="w-12 h-12 text-gray-500 mx-auto mb-3" />
          <p class="text-gray-400">Drag & drop or click to upload</p>
          <p class="text-gray-500 text-sm mt-1">CSV files only (max 1000 rows)</p>
        </div>
        <input ref="fileInput" type="file" accept=".csv" class="hidden" @change="handleFileSelect" />
        <p v-if="selectedFile" class="text-green-400 text-sm mt-2 flex items-center gap-2">
          <FileCheck class="w-4 h-4" /> {{ selectedFile.name }} ({{ (selectedFile.size / 1024).toFixed(1) }} KB)
        </p>
      </div>

      <!-- Step 3: Settings -->
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 mb-6">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white font-bold text-sm">3</div>
          <h2 class="text-white font-semibold">Import Settings</h2>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Default Package</label>
            <select v-model="defaultPackage" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
              <option value="">None</option>
              <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">{{ pkg.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Default Expiry Date</label>
            <input v-model="defaultExpiry" type="date" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4 mt-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Max Connections</label>
            <input v-model="defaultMaxConn" type="number" min="1" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>
          <div class="flex items-end gap-6">
            <label class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
              <input type="checkbox" v-model="sendCredentials" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              Send via email
            </label>
            <label class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
              <input type="checkbox" v-model="skipDuplicates" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              Skip duplicates
            </label>
          </div>
        </div>
      </div>

      <!-- Step 4: Preview -->
      <div v-if="csvData.length" class="bg-gray-800 rounded-xl p-6 border border-gray-700 mb-6">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white font-bold text-sm">4</div>
          <h2 class="text-white font-semibold">Preview & Confirm</h2>
        </div>
        <div class="flex gap-4 mb-4 text-sm">
          <span class="text-gray-400">Total: <strong class="text-white">{{ csvData.length }}</strong></span>
          <span class="text-green-400">Valid: <strong>{{ validCount }}</strong></span>
          <span class="text-red-400">Invalid: <strong>{{ csvData.length - validCount }}</strong></span>
        </div>
        <div class="overflow-x-auto max-h-64 overflow-y-auto">
          <table class="w-full text-sm">
            <thead class="sticky top-0 bg-gray-700">
              <tr>
                <th class="px-3 py-2 text-left text-gray-400">#</th>
                <th class="px-3 py-2 text-left text-gray-400">Username</th>
                <th class="px-3 py-2 text-left text-gray-400">Email</th>
                <th class="px-3 py-2 text-left text-gray-400">Package</th>
                <th class="px-3 py-2 text-left text-gray-400">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
              <tr v-for="(row, i) in csvData.slice(0, 50)" :key="i" class="hover:bg-gray-700/50">
                <td class="px-3 py-2 text-gray-500">{{ i + 1 }}</td>
                <td class="px-3 py-2 text-white">{{ row.username }}</td>
                <td class="px-3 py-2 text-gray-400">{{ row.email }}</td>
                <td class="px-3 py-2 text-gray-400">{{ row.package || 'Default' }}</td>
                <td class="px-3 py-2">
                  <span class="px-2 py-0.5 text-xs rounded-full" :class="isValidRow(row) ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'">
                    {{ isValidRow(row) ? 'Valid' : 'Invalid' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Import Button -->
      <div class="flex justify-end gap-3">
        <Link :href="route('admin.users.index')" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">Cancel</Link>
        <button @click="importUsers" :disabled="!selectedFile || processing || validCount === 0" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50 flex items-center gap-2">
          <Loader2 v-if="processing" class="w-4 h-4 animate-spin" />
          {{ processing ? 'Importing...' : `Import ${validCount} Users` }}
        </button>
      </div>

      <!-- Result -->
      <div v-if="importResult" class="mt-6 p-4 rounded-lg" :class="importResult.success ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'">
        {{ importResult.message }}
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeft, Download, Upload, FileCheck, Loader2 } from 'lucide-vue-next'

const props = defineProps({
  packages: { type: Array, default: () => [] },
  resellers: { type: Array, default: () => [] },
})

const selectedFile = ref(null)
const csvData = ref([])
const processing = ref(false)
const importResult = ref(null)
const defaultPackage = ref('')
const defaultExpiry = ref('')
const defaultMaxConn = ref(2)
const sendCredentials = ref(true)
const skipDuplicates = ref(true)

const validCount = computed(() => csvData.value.filter(r => isValidRow(r)).length)

const isValidRow = (row) => row.username && row.username.length >= 3

const handleFileSelect = (e) => {
  selectedFile.value = e.target.files[0]
  parseCSV(e.target.files[0])
}

const handleDrop = (e) => {
  selectedFile.value = e.dataTransfer.files[0]
  parseCSV(e.dataTransfer.files[0])
}

const parseCSV = (file) => {
  const reader = new FileReader()
  reader.onload = (e) => {
    const lines = e.target.result.split('\n').filter(l => l.trim())
    const headers = lines[0].split(',').map(h => h.trim().toLowerCase())
    csvData.value = lines.slice(1).map(line => {
      const values = line.split(',').map(v => v.trim())
      const row = {}
      headers.forEach((h, i) => { row[h] = values[i] || '' })
      return row
    })
  }
  reader.readAsText(file)
}

const importUsers = () => {
  if (!selectedFile.value) return
  processing.value = true
  const formData = new FormData()
  formData.append('file', selectedFile.value)
  formData.append('default_package', defaultPackage.value)
  formData.append('default_expiry', defaultExpiry.value)
  formData.append('default_max_connections', defaultMaxConn.value)
  formData.append('send_credentials', sendCredentials.value)
  formData.append('skip_duplicates', skipDuplicates.value)

  router.post(route('admin.users.bulk.store'), formData, {
    onFinish: () => { processing.value = false },
    onSuccess: () => { importResult.value = { success: true, message: 'Import completed successfully!' } },
    onError: () => { importResult.value = { success: false, message: 'Import failed. Please check your file.' } },
  })
}
</script>
