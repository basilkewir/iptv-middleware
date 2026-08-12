<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto">
      <div class="mb-6">
        <Link :href="route('admin.epg.index')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
          <ArrowLeft class="w-4 h-4" /> Back to EPG
        </Link>
        <h1 class="text-2xl font-bold text-white">Edit EPG Source: {{ source.name }}</h1>
      </div>

      <form @submit.prevent="form.put(route('admin.epg.update', source.id))" class="space-y-6">
        <!-- Source Details -->
        <div class="card">
          <h2 class="text-lg font-semibold text-white mb-4">Source Details</h2>
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Source Name *</label>
                <input v-model="form.name" type="text" class="input-field" />
                <p v-if="form.errors.name" class="text-red-400 text-sm mt-1">{{ form.errors.name }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Source URL *</label>
                <input v-model="form.url" type="url" class="input-field" />
                <p v-if="form.errors.url" class="text-red-400 text-sm mt-1">{{ form.errors.url }}</p>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Source Type</label>
                <select v-model="form.type" class="input-field">
                  <option value="xmltv">XMLTV</option>
                  <option value="json">JSON</option>
                  <option value="custom">Custom</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Language</label>
                <select v-model="form.language" class="input-field">
                  <option value="en">English</option>
                  <option value="es">Spanish</option>
                  <option value="fr">French</option>
                  <option value="de">German</option>
                  <option value="ar">Arabic</option>
                </select>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Timezone</label>
                <select v-model="form.timezone" class="input-field">
                  <option value="UTC">UTC</option>
                  <option value="America/New_York">Eastern (ET)</option>
                  <option value="America/Chicago">Central (CT)</option>
                  <option value="America/Denver">Mountain (MT)</option>
                  <option value="America/Los_Angeles">Pacific (PT)</option>
                  <option value="Europe/London">London (GMT)</option>
                  <option value="Europe/Berlin">Berlin (CET)</option>
                  <option value="Asia/Dubai">Dubai (GST)</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Update Interval</label>
                <select v-model.number="form.update_interval" class="input-field">
                  <option :value="3600">1 hour</option>
                  <option :value="21600">6 hours</option>
                  <option :value="43200">12 hours</option>
                  <option :value="86400">24 hours</option>
                </select>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                <div class="flex items-center gap-4 mt-2">
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input v-model="form.is_active" type="radio" :value="true" class="w-4 h-4 text-purple-600 bg-gray-700 border-gray-600" />
                    <span class="text-gray-300">Active</span>
                  </label>
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input v-model="form.is_active" type="radio" :value="false" class="w-4 h-4 text-purple-600 bg-gray-700 border-gray-600" />
                    <span class="text-gray-300">Inactive</span>
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Channel Mapping -->
        <div class="card">
          <h2 class="text-lg font-semibold text-white mb-4">Channel Mapping</h2>
          <div class="space-y-4">
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="form.auto_mapping" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-purple-600" />
              <span class="text-gray-300">Enable automatic mapping</span>
            </label>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Mapping Strategy</label>
              <select v-model="form.mapping_strategy" class="input-field w-auto">
                <option value="name">By Channel Name</option>
                <option value="id">By Channel ID</option>
                <option value="custom">Custom</option>
              </select>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full">
                <thead>
                  <tr class="text-left text-gray-400 border-b border-gray-700">
                    <th class="pb-2 font-medium">EPG Channel</th>
                    <th class="pb-2 font-medium">Local Channel</th>
                    <th class="pb-2 font-medium">Match</th>
                    <th class="pb-2 font-medium w-10"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                  <tr v-for="(mapping, index) in form.channel_mappings" :key="index">
                    <td class="py-2">
                      <input v-model="mapping.epg_channel_id" type="text" class="input-field" placeholder="EPG ID" />
                    </td>
                    <td class="py-2">
                      <select v-model="mapping.channel_id" class="input-field">
                        <option value="">Select Channel</option>
                        <option v-for="ch in channels" :key="ch.id" :value="ch.id">{{ ch.name }}</option>
                      </select>
                    </td>
                    <td class="py-2">
                      <span v-if="mapping.channel_id && mapping.epg_channel_id" class="badge badge-success">Matched</span>
                      <span v-else class="badge bg-gray-100 text-gray-800">Manual</span>
                    </td>
                    <td class="py-2">
                      <button @click="removeMapping(index)" type="button" class="p-1 text-gray-400 hover:text-red-400">
                        <X class="w-4 h-4" />
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <button @click="addMapping" type="button" class="btn-secondary text-sm">
              + Add Mapping
            </button>
          </div>
        </div>

        <div class="flex justify-end gap-3">
          <Link :href="route('admin.epg.index')" class="btn-secondary">Cancel</Link>
          <button type="submit" :disabled="form.processing" class="btn-primary">
            {{ form.processing ? 'Updating...' : 'Update EPG Source' }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeft, X } from 'lucide-vue-next'

const props = defineProps({
  source: { type: Object, required: true },
  channels: { type: Array, default: () => [] },
})

const form = useForm({
  name: props.source.name,
  url: props.source.url,
  type: props.source.type,
  language: props.source.language || 'en',
  timezone: props.source.timezone || 'UTC',
  update_interval: props.source.update_interval,
  is_active: props.source.is_active,
  auto_mapping: props.source.auto_mapping,
  mapping_strategy: props.source.mapping_strategy || 'name',
  channel_mappings: (props.source.channel_mappings || []).map(m => ({
    channel_id: m.channel_id,
    epg_channel_id: m.epg_channel_id,
    epg_channel_name: m.epg_channel_name || '',
  })),
})

const addMapping = () => {
  form.channel_mappings.push({ channel_id: '', epg_channel_id: '', epg_channel_name: '' })
}

const removeMapping = (index) => {
  form.channel_mappings.splice(index, 1)
}
</script>
