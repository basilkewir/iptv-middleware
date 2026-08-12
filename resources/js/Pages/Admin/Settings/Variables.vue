<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto space-y-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">Variables & Feature Flags</h1>
        <p class="text-gray-400 mt-1">Manage environment variables, custom constants, and feature flags</p>
      </div>

      <form @submit.prevent="form.put(route('admin.settings.variables.update'))" class="space-y-6">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-white">Environment Variables</h3>
            <button type="button" @click="addVariable" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition text-sm">Add Variable</button>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="border-b border-gray-700">
                  <th class="text-left py-3 px-4 text-sm font-medium text-gray-400">Variable Name</th>
                  <th class="text-left py-3 px-4 text-sm font-medium text-gray-400">Value</th>
                  <th class="text-right py-3 px-4 text-sm font-medium text-gray-400">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(v, index) in form.env_vars" :key="index" class="border-b border-gray-700/50">
                  <td class="py-3 px-4">
                    <input v-model="v.name" type="text" class="w-full px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm focus:outline-none focus:border-indigo-500" />
                  </td>
                  <td class="py-3 px-4">
                    <input v-model="v.value" type="text" class="w-full px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm focus:outline-none focus:border-indigo-500" />
                  </td>
                  <td class="py-3 px-4 text-right">
                    <button type="button" @click="removeVariable(index)" class="text-red-400 hover:text-red-300 text-sm">Delete</button>
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-if="form.env_vars.length === 0" class="text-gray-500 text-sm text-center py-4">No variables defined</p>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-white">Custom Constants</h3>
            <button type="button" @click="addConstant" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition text-sm">Add Constant</button>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="border-b border-gray-700">
                  <th class="text-left py-3 px-4 text-sm font-medium text-gray-400">Constant Name</th>
                  <th class="text-left py-3 px-4 text-sm font-medium text-gray-400">Value</th>
                  <th class="text-left py-3 px-4 text-sm font-medium text-gray-400">Type</th>
                  <th class="text-right py-3 px-4 text-sm font-medium text-gray-400">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(c, index) in form.custom_constants" :key="index" class="border-b border-gray-700/50">
                  <td class="py-3 px-4">
                    <input v-model="c.name" type="text" class="w-full px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm focus:outline-none focus:border-indigo-500" />
                  </td>
                  <td class="py-3 px-4">
                    <input v-model="c.value" type="text" class="w-full px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm focus:outline-none focus:border-indigo-500" />
                  </td>
                  <td class="py-3 px-4">
                    <select v-model="c.type" class="w-full px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm focus:outline-none focus:border-indigo-500">
                      <option value="string">String</option>
                      <option value="integer">Integer</option>
                      <option value="float">Float</option>
                      <option value="boolean">Boolean</option>
                      <option value="json">JSON</option>
                    </select>
                  </td>
                  <td class="py-3 px-4 text-right">
                    <button type="button" @click="removeConstant(index)" class="text-red-400 hover:text-red-300 text-sm">Delete</button>
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-if="form.custom_constants.length === 0" class="text-gray-500 text-sm text-center py-4">No constants defined</p>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Feature Flags</h3>
          <div class="grid grid-cols-3 gap-3">
            <div v-for="flag in featureFlagOptions" :key="flag.key" class="flex items-center gap-3">
              <input v-model="form.feature_flags" :value="flag.key" type="checkbox" :id="flag.key" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label :for="flag.key" class="text-gray-300">{{ flag.label }}</label>
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

const featureFlagOptions = [
  { key: 'vod', label: 'VOD' },
  { key: 'series', label: 'Series' },
  { key: 'epg', label: 'EPG' },
  { key: 'transcoding', label: 'Transcoding' },
  { key: 'reseller', label: 'Reseller' },
  { key: 'multi_language', label: 'Multi-Language' },
  { key: 'social_login', label: 'Social Login' },
  { key: 'payment', label: 'Payment' },
  { key: 'api', label: 'API' },
  { key: 'webhooks', label: 'Webhooks' },
  { key: 'analytics', label: 'Analytics' },
  { key: 'email', label: 'Email' },
]

const form = useForm({
  env_vars: props.settings.env_vars || [],
  custom_constants: props.settings.custom_constants || [],
  feature_flags: props.settings.feature_flags || ['vod', 'epg', 'api'],
})

function addVariable() {
  form.env_vars.push({ name: '', value: '' })
}

function removeVariable(index) {
  form.env_vars.splice(index, 1)
}

function addConstant() {
  form.custom_constants.push({ name: '', value: '', type: 'string' })
}

function removeConstant(index) {
  form.custom_constants.splice(index, 1)
}
</script>
