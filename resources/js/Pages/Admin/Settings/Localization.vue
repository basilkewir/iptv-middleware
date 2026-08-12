<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto space-y-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">Localization Settings</h1>
        <p class="text-gray-400 mt-1">Configure language and regional preferences</p>
      </div>
      <form @submit.prevent="form.put(route('admin.settings.localization.update'))" class="space-y-6">
        <!-- Languages -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Languages</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Default Language</label>
              <select v-model="form.default_language" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="en">English</option>
                <option value="es">Spanish</option>
                <option value="fr">French</option>
                <option value="de">German</option>
                <option value="zh">Chinese</option>
                <option value="ar">Arabic</option>
                <option value="pt">Portuguese</option>
                <option value="it">Italian</option>
                <option value="ja">Japanese</option>
                <option value="ko">Korean</option>
                <option value="ru">Russian</option>
                <option value="tr">Turkish</option>
                <option value="hi">Hindi</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-3">Supported Languages</label>
              <div class="overflow-hidden rounded-lg border border-gray-600">
                <table class="w-full">
                  <thead>
                    <tr class="bg-gray-700/50">
                      <th class="px-4 py-3 text-left text-sm font-medium text-gray-300">Language</th>
                      <th class="px-4 py-3 text-left text-sm font-medium text-gray-300">Code</th>
                      <th class="px-4 py-3 text-center text-sm font-medium text-gray-300">Active</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-600">
                    <tr v-for="lang in languages" :key="lang.code" class="hover:bg-gray-700/30">
                      <td class="px-4 py-3 text-sm text-white">{{ lang.name }}</td>
                      <td class="px-4 py-3 text-sm text-gray-400 font-mono">{{ lang.code }}</td>
                      <td class="px-4 py-3 text-center">
                        <input v-model="form.supported_languages[lang.code]" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Translation Management -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Translation Management</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Search Translations</label>
              <input v-model="translationSearch" type="text" placeholder="Search by key or value..." class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div class="overflow-hidden rounded-lg border border-gray-600">
              <table class="w-full">
                <thead>
                  <tr class="bg-gray-700/50">
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-300">Key</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-300">Value</th>
                    <th class="px-4 py-3 text-center text-sm font-medium text-gray-300">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-600">
                  <tr v-for="(t, index) in filteredTranslations" :key="index" class="hover:bg-gray-700/30">
                    <td class="px-4 py-3 text-sm text-gray-400 font-mono">{{ t.key }}</td>
                    <td class="px-4 py-3">
                      <input v-model="t.value" type="text" class="w-full px-2 py-1 bg-gray-700 border border-gray-600 rounded text-white text-sm focus:outline-none focus:border-indigo-500" />
                    </td>
                    <td class="px-4 py-3 text-center">
                      <button type="button" @click="removeTranslation(index)" class="text-red-400 hover:text-red-300 text-sm">Remove</button>
                    </td>
                  </tr>
                </tbody>
              </table>
              <p v-if="filteredTranslations.length === 0" class="text-gray-500 text-sm text-center py-4">No translations found</p>
            </div>
            <button type="button" @click="addTranslation" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition text-sm">Add Translation</button>
          </div>
        </div>

        <!-- Regional -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Regional Settings</h3>
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Number Format</label>
                <select v-model="form.number_format" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option value="1,234.56">1,234.56 (US/UK)</option>
                  <option value="1.234,56">1.234,56 (EU)</option>
                  <option value="1 234.56">1 234.56 (FR)</option>
                  <option value="1'234.56">1'234.56 (CH)</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Date Format</label>
                <select v-model="form.date_format" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option value="Y-m-d">Y-m-d (2026-07-28)</option>
                  <option value="d/m/Y">d/m/Y (28/07/2026)</option>
                  <option value="m/d/Y">m/d/Y (07/28/2026)</option>
                  <option value="d.m.Y">d.m.Y (28.07.2026)</option>
                  <option value="d-M-Y">d-M-Y (28-Jul-2026)</option>
                </select>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Time Format</label>
                <select v-model="form.time_format" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option value="H:i">24-hour (14:30)</option>
                  <option value="h:i A">12-hour (2:30 PM)</option>
                  <option value="H:i:s">24-hour with seconds (14:30:00)</option>
                  <option value="h:i:s A">12-hour with seconds (2:30:00 PM)</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">First Day of Week</label>
                <select v-model="form.first_day_of_week" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option value="sunday">Sunday</option>
                  <option value="monday">Monday</option>
                  <option value="saturday">Saturday</option>
                </select>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Measurement System</label>
                <select v-model="form.measurement_system" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option value="metric">Metric (km, kg, °C)</option>
                  <option value="imperial">Imperial (mi, lb, °F)</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Timezone Display</label>
                <select v-model="form.timezone_display" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option value="utc_offset">UTC Offset (UTC+5)</option>
                  <option value="abbreviated">Abbreviated (EST, PST)</option>
                  <option value="full_name">Full Name (Eastern Standard Time)</option>
                </select>
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
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ settings: Object })

const languages = [
  { code: 'en', name: 'English' },
  { code: 'es', name: 'Spanish' },
  { code: 'fr', name: 'French' },
  { code: 'de', name: 'German' },
  { code: 'zh', name: 'Chinese' },
  { code: 'ar', name: 'Arabic' },
  { code: 'pt', name: 'Portuguese' },
  { code: 'it', name: 'Italian' },
  { code: 'ja', name: 'Japanese' },
  { code: 'ko', name: 'Korean' },
  { code: 'ru', name: 'Russian' },
  { code: 'tr', name: 'Turkish' },
  { code: 'hi', name: 'Hindi' },
]

const defaultSupported = {}
languages.forEach(l => { defaultSupported[l.code] = false })
defaultSupported['en'] = true

const translationSearch = ref('')

const form = useForm({
  default_language: props.settings.default_language || 'en',
  supported_languages: props.settings.supported_languages || defaultSupported,
  number_format: props.settings.number_format || '1,234.56',
  date_format: props.settings.date_format || 'Y-m-d',
  time_format: props.settings.time_format || 'H:i',
  first_day_of_week: props.settings.first_day_of_week || 'monday',
  measurement_system: props.settings.measurement_system || 'metric',
  timezone_display: props.settings.timezone_display || 'utc_offset',
  translations: props.settings.translations || [
    { key: 'welcome', value: 'Welcome' },
    { key: 'login', value: 'Log In' },
    { key: 'logout', value: 'Log Out' },
    { key: 'settings', value: 'Settings' },
    { key: 'save', value: 'Save' },
    { key: 'cancel', value: 'Cancel' },
    { key: 'delete', value: 'Delete' },
    { key: 'search', value: 'Search' },
    { key: 'channels', value: 'Channels' },
    { key: 'epg', value: 'EPG' },
  ],
})

const filteredTranslations = computed(() => {
  if (!translationSearch.value) return form.translations
  const search = translationSearch.value.toLowerCase()
  return form.translations.filter(t => t.key.toLowerCase().includes(search) || t.value.toLowerCase().includes(search))
})

function addTranslation() {
  form.translations.push({ key: '', value: '' })
}

function removeTranslation(index) {
  form.translations.splice(index, 1)
}
</script>
