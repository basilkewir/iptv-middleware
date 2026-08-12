<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto space-y-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">Domain Settings</h1>
        <p class="text-gray-400 mt-1">Manage domains, SSL certificates, and server configuration</p>
      </div>

      <form @submit.prevent="form.put(route('admin.settings.domains.update'))" class="space-y-6">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-white">Domain List</h3>
            <button type="button" @click="addDomain" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition text-sm">Add Domain</button>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="border-b border-gray-700">
                  <th class="text-left py-3 px-4 text-sm font-medium text-gray-400">Domain</th>
                  <th class="text-left py-3 px-4 text-sm font-medium text-gray-400">Type</th>
                  <th class="text-left py-3 px-4 text-sm font-medium text-gray-400">SSL</th>
                  <th class="text-right py-3 px-4 text-sm font-medium text-gray-400">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(d, index) in form.domains" :key="index" class="border-b border-gray-700/50">
                  <td class="py-3 px-4 text-white font-mono text-sm">{{ d.domain_url }}</td>
                  <td class="py-3 px-4">
                    <span class="px-2 py-1 text-xs rounded-full bg-indigo-500/20 text-indigo-400">{{ d.domain_type }}</span>
                  </td>
                  <td class="py-3 px-4">
                    <span class="px-2 py-1 text-xs rounded-full" :class="d.ssl_certificate !== 'none' ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'">
                      {{ d.ssl_certificate === 'none' ? 'None' : d.ssl_certificate }}
                    </span>
                  </td>
                  <td class="py-3 px-4 text-right">
                    <button type="button" @click="editDomain(index)" class="text-indigo-400 hover:text-indigo-300 text-sm">Edit</button>
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-if="form.domains.length === 0" class="text-gray-500 text-sm text-center py-4">No domains configured</p>
          </div>
        </div>

        <div v-if="editingIndex !== null" class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Domain Configuration</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Domain URL</label>
              <input v-model="editingDomain.domain_url" type="url" placeholder="https://example.com" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white font-mono text-sm focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Domain Type</label>
              <div class="flex gap-4 mt-2 flex-wrap">
                <div v-for="t in domainTypes" :key="t.value" class="flex items-center gap-2">
                  <input v-model="editingDomain.domain_type" :value="t.value" type="radio" :id="'domain_type_' + t.value" class="w-4 h-4 bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                  <label :for="'domain_type_' + t.value" class="text-gray-300">{{ t.label }}</label>
                </div>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">SSL Certificate</label>
              <div class="flex gap-4 mt-2">
                <div v-for="s in sslOptions" :key="s.value" class="flex items-center gap-2">
                  <input v-model="editingDomain.ssl_certificate" :value="s.value" type="radio" :id="'ssl_' + s.value" class="w-4 h-4 bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                  <label :for="'ssl_' + s.value" class="text-gray-300">{{ s.label }}</label>
                </div>
              </div>
            </div>
            <div v-if="editingDomain.ssl_certificate === 'custom'" class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">SSL Certificate Path</label>
                <input v-model="editingDomain.ssl_cert_path" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white font-mono text-sm focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">SSL Key Path</label>
                <input v-model="editingDomain.ssl_key_path" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white font-mono text-sm focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
            <div class="flex gap-6">
              <div class="flex items-center gap-3">
                <input v-model="editingDomain.enable_hsts" type="checkbox" id="enable_hsts" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                <label for="enable_hsts" class="text-gray-300">Enable HSTS</label>
              </div>
              <div class="flex items-center gap-3">
                <input v-model="editingDomain.enable_http2" type="checkbox" id="enable_http2" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                <label for="enable_http2" class="text-gray-300">Enable HTTP/2</label>
              </div>
            </div>
            <div class="flex gap-3 pt-2">
              <button type="button" @click="saveDomain" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition">Save Domain</button>
              <button type="button" @click="cancelEdit" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">Cancel</button>
              <button type="button" @click="deleteDomain" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded-lg transition ml-auto">Delete Domain</button>
            </div>
          </div>
        </div>

        <div class="flex justify-end">
          <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50">
            {{ form.processing ? 'Saving...' : 'Save Settings' }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref } from 'vue'

const props = defineProps({ settings: Object })

const domainTypes = [
  { value: 'main', label: 'Main' },
  { value: 'admin', label: 'Admin' },
  { value: 'reseller', label: 'Reseller' },
  { value: 'api', label: 'API' },
  { value: 'cdn', label: 'CDN' },
  { value: 'custom', label: 'Custom' },
]

const sslOptions = [
  { value: 'none', label: "None" },
  { value: 'letsencrypt', label: "Let's Encrypt" },
  { value: 'custom', label: 'Custom' },
  { value: 'cloudflare', label: 'Cloudflare' },
]

const form = useForm({
  domains: props.settings.domains || [],
})

const editingIndex = ref(null)
const editingDomain = ref({})

function addDomain() {
  const newDomain = {
    domain_url: '',
    domain_type: 'main',
    ssl_certificate: 'letsencrypt',
    ssl_cert_path: '',
    ssl_key_path: '',
    enable_hsts: true,
    enable_http2: true,
  }
  form.domains.push(newDomain)
  editingIndex.value = form.domains.length - 1
  editingDomain.value = { ...form.domains[editingIndex.value] }
}

function editDomain(index) {
  editingIndex.value = index
  editingDomain.value = { ...form.domains[index] }
}

function saveDomain() {
  form.domains[editingIndex.value] = { ...editingDomain.value }
  editingIndex.value = null
  editingDomain.value = {}
}

function cancelEdit() {
  editingIndex.value = null
  editingDomain.value = {}
}

function deleteDomain() {
  form.domains.splice(editingIndex.value, 1)
  editingIndex.value = null
  editingDomain.value = {}
}
</script>
