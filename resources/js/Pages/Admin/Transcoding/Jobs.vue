<template>
  <AdminLayout>
    <div class="p-6">
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-white">Transcoding Jobs</h1>
          <p class="text-gray-400 text-sm mt-1">Monitor and manage transcoding tasks</p>
        </div>
        <div class="flex gap-2">
          <button @click="clearCompleted" class="btn-secondary flex items-center gap-2">
            <Trash2 class="w-4 h-4" /> Clear Completed
          </button>
          <Link :href="route('admin.transcoding.jobs.create')" class="btn-primary flex items-center gap-2">
            <Plus class="w-4 h-4" /> Add Job
          </Link>
        </div>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="card">
          <p class="text-gray-400 text-sm">Running</p>
          <p class="text-2xl font-bold text-green-400">{{ stats.running }}</p>
        </div>
        <div class="card">
          <p class="text-gray-400 text-sm">Waiting</p>
          <p class="text-2xl font-bold text-yellow-400">{{ stats.waiting }}</p>
        </div>
        <div class="card">
          <p class="text-gray-400 text-sm">Completed</p>
          <p class="text-2xl font-bold text-blue-400">{{ stats.completed }}</p>
        </div>
        <div class="card">
          <p class="text-gray-400 text-sm">Failed</p>
          <p class="text-2xl font-bold text-red-400">{{ stats.failed }}</p>
        </div>
      </div>

      <!-- Filters -->
      <div class="card mb-6">
        <div class="flex flex-col md:flex-row gap-4">
          <select v-model="filterStatus" class="input-field w-auto" @change="applyFilters">
            <option value="">All Status</option>
            <option value="pending">Waiting</option>
            <option value="processing">Running</option>
            <option value="completed">Completed</option>
            <option value="failed">Failed</option>
          </select>
          <select v-model="filterChannel" class="input-field w-auto" @change="applyFilters">
            <option value="">All Channels</option>
            <option v-for="ch in channels" :key="ch.id" :value="ch.id">{{ ch.name }}</option>
          </select>
        </div>
      </div>

      <!-- Jobs Table -->
      <div class="card">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="text-left text-gray-400 border-b border-gray-700">
                <th class="pb-3 font-medium">Channel/VOD</th>
                <th class="pb-3 font-medium">Profile</th>
                <th class="pb-3 font-medium">Target Quality</th>
                <th class="pb-3 font-medium">Progress</th>
                <th class="pb-3 font-medium">ETA</th>
                <th class="pb-3 font-medium">Status</th>
                <th class="pb-3 font-medium">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
              <tr v-if="jobs.data.length === 0">
                <td colspan="6" class="py-12 text-center text-gray-500">No transcoding jobs found.</td>
              </tr>
              <tr v-for="job in jobs.data" :key="job.id" class="hover:bg-gray-800/50">
                <td class="py-3">
                  <span class="text-white">{{ job.channel?.name || job.vod_content?.title || 'N/A' }}</span>
                </td>
                <td class="py-3 text-gray-400">{{ job.profile?.name || '-' }}</td>
                <td class="py-3 text-gray-400 text-sm">{{ job.target_quality || job.profile?.resolution || '-' }}</td>
                <td class="py-3">
                  <div class="w-32 bg-gray-700 rounded-full h-2">
                    <div class="h-2 rounded-full transition-all"
                      :class="{
                        'bg-green-500': job.status === 'completed',
                        'bg-purple-500': job.status === 'processing',
                        'bg-yellow-500': job.status === 'pending',
                        'bg-red-500': job.status === 'failed',
                      }"
                      :style="{ width: `${job.progress || 0}%` }"
                    />
                  </div>
                  <span class="text-gray-500 text-xs">{{ job.progress || 0 }}%</span>
                </td>
                <td class="py-3 text-gray-400 text-sm">{{ job.eta || '-' }}</td>
                <td class="py-3">
                  <span class="badge"
                    :class="{
                      'badge-success': job.status === 'completed',
                      'bg-purple-100 text-purple-800': job.status === 'processing',
                      'badge-warning': job.status === 'pending',
                      'badge-danger': job.status === 'failed',
                      'bg-gray-100 text-gray-800': job.status === 'paused' || job.status === 'cancelled',
                    }">
                    {{ job.status }}
                  </span>
                </td>
                <td class="py-3">
                  <div class="flex items-center gap-1">
                    <button v-if="job.status === 'processing'" @click="pauseJob(job)" class="p-1.5 text-gray-400 hover:text-yellow-400 hover:bg-gray-700 rounded" title="Pause">
                      <Pause class="w-4 h-4" />
                    </button>
                    <button v-if="job.status === 'paused'" @click="resumeJob(job)" class="p-1.5 text-gray-400 hover:text-green-400 hover:bg-gray-700 rounded" title="Resume">
                      <Play class="w-4 h-4" />
                    </button>
                    <button v-if="['pending', 'processing', 'paused'].includes(job.status)" @click="cancelJob(job)" class="p-1.5 text-gray-400 hover:text-red-400 hover:bg-gray-700 rounded" title="Cancel">
                      <X class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="mt-4 pt-4 border-t border-gray-700">
          <Pagination :links="jobs.links" />
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Pagination from '@/Components/Common/Pagination.vue'
import { Plus, Pause, Play, X, Trash2 } from 'lucide-vue-next'

const props = defineProps({
  jobs: { type: Object, required: true },
  channels: { type: Array, default: () => [] },
  stats: { type: Object, default: () => ({}) },
})

const filterStatus = ref('')
const filterChannel = ref('')

const applyFilters = () => {
  router.get(route('admin.transcoding.jobs'), {
    status: filterStatus.value,
    channel_id: filterChannel.value,
  }, { preserveState: true, replace: true })
}

const pauseJob = (job) => router.post(route('admin.transcoding.jobs.pause', job.id))
const resumeJob = (job) => router.post(route('admin.transcoding.jobs.resume', job.id))
const cancelJob = (job) => {
  if (confirm('Cancel this job?')) {
    router.post(route('admin.transcoding.jobs.cancel', job.id))
  }
}

const clearCompleted = () => {
  if (confirm('Clear all completed jobs?')) {
    router.post(route('admin.transcoding.jobs.clear'))
  }
}
</script>
