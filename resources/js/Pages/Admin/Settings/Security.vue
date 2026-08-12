<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto space-y-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">Security Settings</h1>
        <p class="text-gray-400 mt-1">Configure authentication, access control, and encryption</p>
      </div>
      <form @submit.prevent="form.put(route('admin.settings.security.update'))" class="space-y-6">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Authentication</h3>
          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <label class="text-white font-medium">Enable Two-Factor Authentication</label>
                <p class="text-gray-400 text-sm">Require 2FA for user accounts</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="form.enable_2fa" class="sr-only peer" />
                <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
              </label>
            </div>
            <div v-if="form.enable_2fa">
              <label class="block text-white font-medium mb-2">2FA Method</label>
              <div class="space-y-2">
                <label v-for="method in ['Authenticator App', 'SMS', 'Email', 'Backup Codes']" :key="method" class="flex items-center space-x-3 cursor-pointer">
                  <input type="radio" v-model="form.tfa_method" :value="method" class="w-4 h-4 text-indigo-500 bg-gray-700 border-gray-600 focus:ring-indigo-500" />
                  <span class="text-gray-300">{{ method }}</span>
                </label>
              </div>
            </div>
            <div class="flex items-center justify-between">
              <div>
                <label class="text-white font-medium">Enable Social Login</label>
                <p class="text-gray-400 text-sm">Allow users to login with social accounts</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="form.enable_social_login" class="sr-only peer" />
                <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
              </label>
            </div>
            <div v-if="form.enable_social_login" class="space-y-3">
              <label class="block text-white font-medium mb-2">Social Providers</label>
              <div class="grid grid-cols-2 gap-2">
                <label v-for="provider in ['Google', 'Facebook', 'Twitter', 'GitHub', 'Apple']" :key="provider" class="flex items-center space-x-3 cursor-pointer p-2 rounded-lg hover:bg-gray-700">
                  <input type="checkbox" v-model="form.social_providers" :value="provider" class="w-4 h-4 text-indigo-500 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                  <span class="text-gray-300">{{ provider }}</span>
                </label>
              </div>
            </div>
            <div v-if="form.social_providers.includes('Google')" class="space-y-3">
              <div>
                <label class="block text-white font-medium mb-2">Google Client ID</label>
                <input type="text" v-model="form.google_client_id" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" placeholder="Google Client ID" />
              </div>
              <div>
                <label class="block text-white font-medium mb-2">Google Client Secret</label>
                <input type="password" v-model="form.google_client_secret" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" placeholder="Google Client Secret" />
              </div>
            </div>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Access Control</h3>
          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <label class="text-white font-medium">IP Whitelist</label>
                <p class="text-gray-400 text-sm">Only allow access from whitelisted IPs</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="form.ip_whitelist_enabled" class="sr-only peer" />
                <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
              </label>
            </div>
            <div v-if="form.ip_whitelist_enabled">
              <label class="block text-white font-medium mb-2">Allowed IPs</label>
              <textarea v-model="form.allowed_ips" rows="3" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" placeholder="One IP per line..."></textarea>
            </div>
            <div class="flex items-center justify-between">
              <div>
                <label class="text-white font-medium">IP Blacklist</label>
                <p class="text-gray-400 text-sm">Block access from blacklisted IPs</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="form.ip_blacklist_enabled" class="sr-only peer" />
                <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
              </label>
            </div>
            <div v-if="form.ip_blacklist_enabled">
              <label class="block text-white font-medium mb-2">Blocked IPs</label>
              <textarea v-model="form.blocked_ips" rows="3" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" placeholder="One IP per line..."></textarea>
            </div>
            <div class="flex items-center justify-between">
              <div>
                <label class="text-white font-medium">Country Restriction</label>
                <p class="text-gray-400 text-sm">Restrict access by country</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="form.country_restriction" class="sr-only peer" />
                <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
              </label>
            </div>
            <div v-if="form.country_restriction" class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-white font-medium mb-2">Allowed Countries</label>
                <select v-model="form.allowed_countries" multiple class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 h-32">
                  <option value="US">United States</option>
                  <option value="UK">United Kingdom</option>
                  <option value="CA">Canada</option>
                  <option value="AU">Australia</option>
                  <option value="DE">Germany</option>
                  <option value="FR">France</option>
                </select>
              </div>
              <div>
                <label class="block text-white font-medium mb-2">Blocked Countries</label>
                <select v-model="form.blocked_countries" multiple class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 h-32">
                  <option value="CN">China</option>
                  <option value="RU">Russia</option>
                  <option value="IR">Iran</option>
                  <option value="KP">North Korea</option>
                </select>
              </div>
            </div>
            <div class="flex items-center justify-between">
              <div>
                <label class="text-white font-medium">User Agent Restriction</label>
                <p class="text-gray-400 text-sm">Block suspicious user agents</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="form.user_agent_restriction" class="sr-only peer" />
                <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
              </label>
            </div>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Rate Limiting</h3>
          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <label class="text-white font-medium">Enable Rate Limiting</label>
                <p class="text-gray-400 text-sm">Protect against brute force attacks</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="form.enable_rate_limiting" class="sr-only peer" />
                <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
              </label>
            </div>
            <div v-if="form.enable_rate_limiting" class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-white font-medium mb-2">API Requests per Minute</label>
                <input type="number" v-model="form.api_requests_per_minute" min="1" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-white font-medium mb-2">Login Attempts per 15min</label>
                <input type="number" v-model="form.login_attempts_per_15min" min="1" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-white font-medium mb-2">Registration Attempts per Hour</label>
                <input type="number" v-model="form.registration_attempts_per_hour" min="1" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-white font-medium mb-2">Password Reset per Hour</label>
                <input type="number" v-model="form.password_reset_per_hour" min="1" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-white font-medium mb-2">API Key Requests per Minute</label>
                <input type="number" v-model="form.api_key_requests_per_minute" min="1" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-white font-medium mb-2">Burst Limit</label>
                <input type="number" v-model="form.burst_limit" min="1" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" />
              </div>
            </div>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Encryption</h3>
          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <label class="text-white font-medium">Enable Stream Encryption</label>
                <p class="text-gray-400 text-sm">Encrypt video streams</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="form.enable_stream_encryption" class="sr-only peer" />
                <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
              </label>
            </div>
            <div v-if="form.enable_stream_encryption" class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-white font-medium mb-2">Encryption Method</label>
                <select v-model="form.encryption_method" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500">
                  <option value="AES-128">AES-128</option>
                  <option value="AES-256">AES-256</option>
                </select>
              </div>
              <div>
                <label class="block text-white font-medium mb-2">Key Rotation Interval (hours)</label>
                <input type="number" v-model="form.key_rotation_interval" min="1" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" />
              </div>
            </div>
            <div class="flex items-center justify-between">
              <div>
                <label class="text-white font-medium">DRM Integration</label>
                <p class="text-gray-400 text-sm">Digital Rights Management</p>
              </div>
              <div class="space-y-2">
                <label v-for="drm in ['None', 'Widevine', 'PlayReady', 'ClearKey', 'Multi-DRM']" :key="drm" class="flex items-center space-x-3 cursor-pointer">
                  <input type="radio" v-model="form.drm_integration" :value="drm" class="w-4 h-4 text-indigo-500 bg-gray-700 border-gray-600 focus:ring-indigo-500" />
                  <span class="text-gray-300">{{ drm }}</span>
                </label>
              </div>
            </div>
            <div class="flex items-center justify-between">
              <div>
                <label class="text-white font-medium">Enable Database Encryption</label>
                <p class="text-gray-400 text-sm">Encrypt sensitive database fields</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="form.enable_database_encryption" class="sr-only peer" />
                <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
              </label>
            </div>
            <div>
              <label class="block text-white font-medium mb-2">Encryption Key</label>
              <div class="flex space-x-2">
                <input type="password" v-model="form.encryption_key" class="flex-1 bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" placeholder="Encryption Key" />
                <button type="button" @click="generateKey" class="px-4 py-2 bg-gray-600 hover:bg-gray-500 text-white rounded-lg transition">
                  Generate
                </button>
              </div>
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
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'

const props = defineProps({ settings: Object })

const form = useForm({
  enable_2fa: props.settings.enable_2fa ?? false,
  tfa_method: props.settings.tfa_method ?? 'Authenticator App',
  enable_social_login: props.settings.enable_social_login ?? false,
  social_providers: props.settings.social_providers ?? [],
  google_client_id: props.settings.google_client_id ?? '',
  google_client_secret: props.settings.google_client_secret ?? '',
  ip_whitelist_enabled: props.settings.ip_whitelist_enabled ?? false,
  allowed_ips: props.settings.allowed_ips ?? '',
  ip_blacklist_enabled: props.settings.ip_blacklist_enabled ?? false,
  blocked_ips: props.settings.blocked_ips ?? '',
  country_restriction: props.settings.country_restriction ?? false,
  allowed_countries: props.settings.allowed_countries ?? [],
  blocked_countries: props.settings.blocked_countries ?? [],
  user_agent_restriction: props.settings.user_agent_restriction ?? false,
  enable_rate_limiting: props.settings.enable_rate_limiting ?? true,
  api_requests_per_minute: props.settings.api_requests_per_minute ?? 60,
  login_attempts_per_15min: props.settings.login_attempts_per_15min ?? 5,
  registration_attempts_per_hour: props.settings.registration_attempts_per_hour ?? 3,
  password_reset_per_hour: props.settings.password_reset_per_hour ?? 3,
  api_key_requests_per_minute: props.settings.api_key_requests_per_minute ?? 1000,
  burst_limit: props.settings.burst_limit ?? 10,
  enable_stream_encryption: props.settings.enable_stream_encryption ?? false,
  encryption_method: props.settings.encryption_method ?? 'AES-256',
  key_rotation_interval: props.settings.key_rotation_interval ?? 24,
  drm_integration: props.settings.drm_integration ?? 'None',
  enable_database_encryption: props.settings.enable_database_encryption ?? false,
  encryption_key: props.settings.encryption_key ?? '',
})

function generateKey() {
  const array = new Uint8Array(32)
  window.crypto.getRandomValues(array)
  form.encryption_key = Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('')
}
</script>
