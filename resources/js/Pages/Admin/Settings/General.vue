<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto space-y-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">General Settings</h1>
        <p class="text-gray-400 mt-1">Configure platform and branding settings</p>
      </div>
      <form @submit.prevent="form.put(route('admin.settings.general.update'))" class="space-y-6">
        <!-- Platform -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Platform</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Site Name</label>
              <input v-model="form.site_name" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Site URL</label>
              <input v-model="form.site_url" type="url" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Default Language</label>
              <select v-model="form.default_language" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="en">English</option>
                <option value="es">Spanish</option>
                <option value="fr">French</option>
                <option value="de">German</option>
                <option value="zh">Chinese</option>
                <option value="ar">Arabic</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Default Timezone</label>
              <select v-model="form.default_timezone" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="UTC+0">UTC+0</option>
                <option value="UTC+1">UTC+1</option>
                <option value="UTC+2">UTC+2</option>
                <option value="UTC+3">UTC+3</option>
                <option value="UTC+4">UTC+4</option>
                <option value="UTC+5">UTC+5</option>
                <option value="UTC+6">UTC+6</option>
                <option value="UTC+7">UTC+7</option>
                <option value="UTC+8">UTC+8</option>
                <option value="UTC+9">UTC+9</option>
                <option value="UTC+10">UTC+10</option>
                <option value="UTC+11">UTC+11</option>
                <option value="UTC+12">UTC+12</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Default Currency</label>
              <select v-model="form.default_currency" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="USD">USD - US Dollar</option>
                <option value="EUR">EUR - Euro</option>
                <option value="GBP">GBP - British Pound</option>
              </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Date Format</label>
                <select v-model="form.date_format" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option value="YYYY-MM-DD">YYYY-MM-DD</option>
                  <option value="DD/MM/YYYY">DD/MM/YYYY</option>
                  <option value="MM/DD/YYYY">MM/DD/YYYY</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Time Format</label>
                <div class="flex items-center gap-4 mt-2">
                  <label class="flex items-center gap-2 text-gray-300">
                    <input v-model="form.time_format" type="radio" value="12h" class="w-4 h-4 bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                    12-hour
                  </label>
                  <label class="flex items-center gap-2 text-gray-300">
                    <input v-model="form.time_format" type="radio" value="24h" class="w-4 h-4 bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                    24-hour
                  </label>
                </div>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Timezone Offset (minutes)</label>
              <input v-model="form.timezone_offset" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
          </div>
        </div>

        <!-- Branding -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Branding</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Company Name</label>
              <input v-model="form.company_name" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Logo</label>
              <div class="flex items-center gap-4">
                <div v-if="form.logo_url" class="w-16 h-16 rounded-lg bg-gray-700 flex items-center justify-center overflow-hidden">
                  <img :src="form.logo_url" class="max-w-full max-h-full object-contain" />
                </div>
                <label class="flex-1 flex items-center justify-center px-4 py-3 bg-gray-700 border border-gray-600 border-dashed rounded-lg cursor-pointer hover:border-indigo-500 transition">
                  <input type="file" accept="image/*" class="hidden" @change="handleFileUpload($event, 'logo_url')" />
                  <span class="text-gray-400 text-sm">Click to upload logo</span>
                </label>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Favicon</label>
              <div class="flex items-center gap-4">
                <div v-if="form.favicon_url" class="w-8 h-8 rounded bg-gray-700 flex items-center justify-center overflow-hidden">
                  <img :src="form.favicon_url" class="max-w-full max-h-full object-contain" />
                </div>
                <label class="flex-1 flex items-center justify-center px-4 py-3 bg-gray-700 border border-gray-600 border-dashed rounded-lg cursor-pointer hover:border-indigo-500 transition">
                  <input type="file" accept="image/*" class="hidden" @change="handleFileUpload($event, 'favicon_url')" />
                  <span class="text-gray-400 text-sm">Click to upload favicon</span>
                </label>
              </div>
            </div>
            <div class="grid grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Primary Color</label>
                <div class="flex items-center gap-2">
                  <input v-model="form.primary_color" type="color" class="w-10 h-10 rounded bg-transparent border border-gray-600 cursor-pointer" />
                  <input v-model="form.primary_color" type="text" class="flex-1 px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm font-mono focus:outline-none focus:border-indigo-500" />
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Secondary Color</label>
                <div class="flex items-center gap-2">
                  <input v-model="form.secondary_color" type="color" class="w-10 h-10 rounded bg-transparent border border-gray-600 cursor-pointer" />
                  <input v-model="form.secondary_color" type="text" class="flex-1 px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm font-mono focus:outline-none focus:border-indigo-500" />
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Accent Color</label>
                <div class="flex items-center gap-2">
                  <input v-model="form.accent_color" type="color" class="w-10 h-10 rounded bg-transparent border border-gray-600 cursor-pointer" />
                  <input v-model="form.accent_color" type="text" class="flex-1 px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm font-mono focus:outline-none focus:border-indigo-500" />
                </div>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Font Family</label>
              <select v-model="form.font_family" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="Inter">Inter</option>
                <option value="Roboto">Roboto</option>
                <option value="Open Sans">Open Sans</option>
                <option value="Lato">Lato</option>
                <option value="Poppins">Poppins</option>
                <option value="Nunito">Nunito</option>
                <option value="Source Sans Pro">Source Sans Pro</option>
                <option value="Montserrat">Montserrat</option>
                <option value="system-ui">System UI</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Custom CSS</label>
              <textarea v-model="form.custom_css" rows="4" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white font-mono text-sm focus:outline-none focus:border-indigo-500" placeholder="/* Custom CSS */" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Custom JavaScript</label>
              <textarea v-model="form.custom_js" rows="4" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white font-mono text-sm focus:outline-none focus:border-indigo-500" placeholder="// Custom JavaScript" />
            </div>
          </div>
        </div>

        <!-- Maintenance Mode -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Maintenance Mode</h3>
          <div class="space-y-4">
            <div class="flex items-center gap-4">
              <label class="flex items-center gap-2 text-gray-300">
                <input v-model="form.maintenance_mode" type="radio" value="online" class="w-4 h-4 bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                Online
              </label>
              <label class="flex items-center gap-2 text-gray-300">
                <input v-model="form.maintenance_mode" type="radio" value="maintenance" class="w-4 h-4 bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                Maintenance
              </label>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Maintenance Message</label>
              <textarea v-model="form.maintenance_message" rows="3" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" placeholder="We are currently performing scheduled maintenance..." />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Allowed IPs (one per line)</label>
              <textarea v-model="form.maintenance_ips" rows="3" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white font-mono text-sm focus:outline-none focus:border-indigo-500" placeholder="192.168.1.0/24&#10;10.0.0.1" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Maintenance Schedule</label>
              <input v-model="form.maintenance_schedule" type="datetime-local" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
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

const props = defineProps({ settings: Object })

const form = useForm({
  site_name: props.settings.site_name || '',
  site_url: props.settings.site_url || '',
  default_language: props.settings.default_language || 'en',
  default_timezone: props.settings.default_timezone || 'UTC+0',
  default_currency: props.settings.default_currency || 'USD',
  date_format: props.settings.date_format || 'YYYY-MM-DD',
  time_format: props.settings.time_format || '24h',
  timezone_offset: props.settings.timezone_offset || 0,
  company_name: props.settings.company_name || '',
  logo_url: props.settings.logo_url || '',
  favicon_url: props.settings.favicon_url || '',
  primary_color: props.settings.primary_color || '#6366f1',
  secondary_color: props.settings.secondary_color || '#4f46e5',
  accent_color: props.settings.accent_color || '#818cf8',
  font_family: props.settings.font_family || 'Inter',
  custom_css: props.settings.custom_css || '',
  custom_js: props.settings.custom_js || '',
  maintenance_mode: props.settings.maintenance_mode || 'online',
  maintenance_message: props.settings.maintenance_message || '',
  maintenance_ips: props.settings.maintenance_ips || '',
  maintenance_schedule: props.settings.maintenance_schedule || '',
})

const handleFileUpload = (event, field) => {
  const file = event.target.files[0]
  if (file) {
    const reader = new FileReader()
    reader.onload = (e) => {
      form[field] = e.target.result
    }
    reader.readAsDataURL(file)
  }
}
</script>
