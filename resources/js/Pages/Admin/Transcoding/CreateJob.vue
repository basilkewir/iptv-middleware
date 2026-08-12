<template>
  <AdminLayout>
    <div class="p-6 max-w-3xl mx-auto">
      <div class="mb-6">
        <Link :href="route('admin.transcoding.jobs')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
          <ArrowLeft class="w-4 h-4" /> Back to Jobs
        </Link>
        <h1 class="text-2xl font-bold text-white">Create Transcoding Job</h1>
      </div>

      <form @submit.prevent="submit" class="bg-gray-800 rounded-xl p-6 border border-gray-700 space-y-6">
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Profile *</label>
          <select v-model="form.profile_id" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            <option value="">Select a profile</option>
            <option v-for="p in profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
          <p v-if="form.errors.profile_id" class="text-red-400 text-sm mt-1">{{ form.errors.profile_id }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Input URL *</label>
          <input v-model="form.input_url" type="url" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" placeholder="http://..." />
          <p v-if="form.errors.input_url" class="text-red-400 text-sm mt-1">{{ form.errors.input_url }}</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Channel</label>
            <select v-model="form.channel_id" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
              <option value="">None</option>
              <option v-for="ch in channels" :key="ch.id" :value="ch.id">{{ ch.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">VOD Content</label>
            <select v-model="form.vod_content_id" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
              <option value="">None</option>
              <option v-for="v in vodItems" :key="v.id" :value="v.id">{{ v.title }}</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Job Type</label>
            <select v-model="form.job_type" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
              <option value="live">Live</option>
              <option value="vod">VOD</option>
              <option value="series">Series</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Priority</label>
            <input v-model="form.priority" type="number" min="0" max="10" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-gray-700">
          <Link :href="route('admin.transcoding.jobs')" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">Cancel</Link>
          <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50">
            {{ form.processing ? 'Creating...' : 'Create Job' }}
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
import { ArrowLeft } from 'lucide-vue-next'

const props = defineProps({
  profiles: { type: Array, default: () => [] },
  channels: { type: Array, default: () => [] },
  vodItems: { type: Array, default: () => [] },
})

const form = useForm({
  profile_id: '',
  input_url: '',
  channel_id: '',
  vod_content_id: '',
  job_type: 'live',
  priority: 0,
})

const submit = () => {
  form.post(route('admin.transcoding.jobs.store'))
}
</script>
