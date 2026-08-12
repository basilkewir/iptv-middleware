<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto space-y-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">API Settings</h1>
        <p class="text-gray-400 mt-1">Configure API general settings, authentication, and endpoints</p>
      </div>

      <form @submit.prevent="form.put(route('admin.settings.api.update'))" class="space-y-6">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">API General</h3>
          <div class="space-y-4">
            <div class="flex items-center gap-3">
              <input v-model="form.enable_api" type="checkbox" id="enable_api" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="enable_api" class="text-gray-300">Enable API</label>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">API Version</label>
                <input v-model="form.api_version" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">API Base URL</label>
                <input v-model="form.api_base_url" type="url" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
            <div class="grid grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Timeout (seconds)</label>
                <input v-model="form.api_timeout" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Rate Limit (req/min)</label>
                <input v-model="form.api_rate_limit" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Burst Limit</label>
                <input v-model="form.api_burst_limit" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.enable_docs" type="checkbox" id="enable_docs" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="enable_docs" class="text-gray-300">Enable API Documentation</label>
            </div>
            <div v-if="form.enable_docs">
              <label class="block text-sm font-medium text-gray-300 mb-2">Docs URL</label>
              <input v-model="form.docs_url" type="url" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">API Authentication</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Auth Type</label>
              <div class="flex gap-4 mt-2">
                <div v-for="a in authOptions" :key="a.value" class="flex items-center gap-2">
                  <input v-model="form.auth_type" :value="a.value" type="radio" :id="'auth_' + a.value" class="w-4 h-4 bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                  <label :for="'auth_' + a.value" class="text-gray-300">{{ a.label }}</label>
                </div>
              </div>
            </div>
            <div v-if="form.auth_type === 'jwt'">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">JWT Secret</label>
                <div class="flex gap-2">
                  <input v-model="form.jwt_secret" type="password" class="flex-1 px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
                  <button type="button" @click="regenerateJwt" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">Regenerate</button>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-4 mt-4">
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">JWT Expiry (seconds)</label>
                  <input v-model="form.jwt_expiry" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Refresh Expiry (seconds)</label>
                  <input v-model="form.refresh_expiry" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
                </div>
              </div>
            </div>
            <div v-if="form.auth_type === 'api_key'" class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">API Key Prefix</label>
                <input v-model="form.api_key_prefix" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">API Key Length</label>
                <input v-model="form.api_key_length" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">API Endpoints</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Enabled Endpoints</label>
              <div class="flex gap-4 mt-2">
                <div v-for="ep in endpointOptions" :key="ep.key" class="flex items-center gap-3">
                  <input v-model="form.enabled_endpoints" :value="ep.key" type="checkbox" :id="ep.key" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                  <label :for="ep.key" class="text-gray-300">{{ ep.label }}</label>
                </div>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Allowed Origins (one per line)</label>
              <textarea v-model="form.allowed_origins" rows="3" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white font-mono text-sm focus:outline-none focus:border-indigo-500" placeholder="https://example.com&#10;https://app.example.com" />
            </div>
          </div>
        </div>

        <div class="flex justify-between">
          <button type="button" @click="regenerateKeys" class="px-6 py-2 bg-yellow-600 hover:bg-yellow-500 text-white rounded-lg transition">Regenerate Keys</button>
          <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50">
            {{ form.processing ? 'Saving...' : 'Save Settings' }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { useForm, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ settings: Object })

const authOptions = [
  { value: 'jwt', label: 'JWT' },
  { value: 'oauth2', label: 'OAuth2' },
  { value: 'api_key', label: 'API Key' },
  { value: 'basic', label: 'Basic Auth' },
]

const endpointOptions = [
  { key: 'public', label: 'Public' },
  { key: 'user', label: 'User' },
  { key: 'admin', label: 'Admin' },
  { key: 'reseller', label: 'Reseller' },
  { key: 'webhook', label: 'Webhook' },
]

const form = useForm({
  enable_api: props.settings.enable_api ?? true,
  api_version: props.settings.api_version || 'v1',
  api_base_url: props.settings.api_base_url || '',
  api_timeout: props.settings.api_timeout || 30,
  api_rate_limit: props.settings.api_rate_limit || 1000,
  api_burst_limit: props.settings.api_burst_limit || 100,
  enable_docs: props.settings.enable_docs ?? true,
  docs_url: props.settings.docs_url || '/api/docs',
  auth_type: props.settings.auth_type || 'jwt',
  jwt_secret: props.settings.jwt_secret || '',
  jwt_expiry: props.settings.jwt_expiry || 3600,
  refresh_expiry: props.settings.refresh_expiry || 604800,
  api_key_prefix: props.settings.api_key_prefix || 'iptv_',
  api_key_length: props.settings.api_key_length || 32,
  enabled_endpoints: props.settings.enabled_endpoints || ['public', 'user', 'admin'],
  allowed_origins: props.settings.allowed_origins || '',
})

function regenerateJwt() {
  form.jwt_secret = Array.from(crypto.getRandomValues(new Uint8Array(32)))
    .map(b => b.toString(16).padStart(2, '0')).join('')
}

function regenerateKeys() {
  if (confirm('Regenerate all API keys? This will invalidate existing keys.')) {
    router.post(route('admin.settings.api.regenerate'))
  }
}
</script>
