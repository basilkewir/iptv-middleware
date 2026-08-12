<template>
  <AdminLayout>
    <div class="p-6">
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-white">EPG Program Manager</h1>
          <p class="text-gray-400 text-sm mt-1">Manage electronic program guide data</p>
        </div>
        <div class="flex gap-2">
          <button @click="refreshEpg" class="btn-secondary flex items-center gap-2">
            <RefreshCw class="w-4 h-4" /> Refresh EPG
          </button>
          <button @click="exportPrograms" class="btn-secondary flex items-center gap-2">
            <Download class="w-4 h-4" /> Export
          </button>
          <button @click="showAddModal = true" class="btn-primary flex items-center gap-2">
            <Plus class="w-4 h-4" /> Add Program
          </button>
        </div>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="card">
          <p class="text-gray-400 text-sm">Total Programs</p>
          <p class="text-2xl font-bold text-white">{{ stats.total_programs }}</p>
        </div>
        <div class="card">
          <p class="text-gray-400 text-sm">Missing Data</p>
          <p class="text-2xl font-bold text-yellow-400">{{ stats.missing_data }}</p>
        </div>
        <div class="card">
          <p class="text-gray-400 text-sm">Last Updated</p>
          <p class="text-2xl font-bold text-green-400">{{ stats.last_updated ? 'Updated' : 'Never' }}</p>
        </div>
      </div>

      <!-- Filters -->
      <div class="card mb-6">
        <div class="flex flex-col md:flex-row gap-4">
          <div class="flex-1">
            <input v-model="search" type="text" placeholder="Search programs..." class="input-field" @keyup.enter="applyFilters" />
          </div>
          <div class="flex gap-3">
            <select v-model="filterChannel" class="input-field w-auto" @change="applyFilters">
              <option value="">All Channels</option>
              <option v-for="ch in channels" :key="ch.id" :value="ch.id">{{ ch.name }}</option>
            </select>
            <input v-model="filterDate" type="date" class="input-field w-auto" @change="applyFilters" />
          </div>
        </div>
      </div>

      <!-- Programs Table -->
      <div class="card">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="text-left text-gray-400 border-b border-gray-700">
                <th class="pb-3 font-medium">Time</th>
                <th class="pb-3 font-medium">Program</th>
                <th class="pb-3 font-medium">Channel</th>
                <th class="pb-3 font-medium">Duration</th>
                <th class="pb-3 font-medium">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
              <tr v-if="programs.data.length === 0">
                <td colspan="5" class="py-12 text-center text-gray-500">No programs found.</td>
              </tr>
              <tr v-for="program in programs.data" :key="program.id" class="hover:bg-gray-800/50">
                <td class="py-3">
                  <div class="text-white text-sm">{{ formatTime(program.start_time) }}</div>
                  <div class="text-gray-500 text-xs">to {{ formatTime(program.end_time) }}</div>
                </td>
                <td class="py-3">
                  <div class="text-white font-medium">{{ program.title }}</div>
                  <div class="text-gray-500 text-sm truncate max-w-xs">{{ program.description }}</div>
                </td>
                <td class="py-3 text-gray-400">{{ program.channel?.name || '-' }}</td>
                <td class="py-3 text-gray-400">{{ getDuration(program.start_time, program.end_time) }}</td>
                <td class="py-3">
                  <div class="flex items-center gap-2">
                    <button @click="editProgram(program)" class="p-1.5 text-gray-400 hover:text-white hover:bg-gray-700 rounded">
                      <Pencil class="w-4 h-4" />
                    </button>
                    <button @click="confirmDelete(program)" class="p-1.5 text-gray-400 hover:text-red-400 hover:bg-gray-700 rounded">
                      <Trash2 class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="mt-4 pt-4 border-t border-gray-700">
          <Pagination :links="programs.links" />
        </div>
      </div>

      <!-- Add/Edit Modal -->
      <Modal :show="showAddModal || editingProgram" @close="closeModal" max-width="lg">
        <div class="p-6">
          <h3 class="text-lg font-semibold text-white mb-4">{{ editingProgram ? 'Edit Program' : 'Add Program' }}</h3>
          <form @submit.prevent="saveProgram" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Channel *</label>
                <select v-model="programForm.channel_id" class="input-field" required>
                  <option value="">Select Channel</option>
                  <option v-for="ch in channels" :key="ch.id" :value="ch.id">{{ ch.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Title *</label>
                <input v-model="programForm.title" type="text" class="input-field" required />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
              <textarea v-model="programForm.description" rows="2" class="input-field" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Start Time *</label>
                <input v-model="programForm.start_time" type="datetime-local" class="input-field" required />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">End Time *</label>
                <input v-model="programForm.end_time" type="datetime-local" class="input-field" required />
              </div>
            </div>
            <div class="grid grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Season</label>
                <input v-model.number="programForm.season" type="number" class="input-field" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Episode</label>
                <input v-model.number="programForm.episode" type="number" class="input-field" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Rating</label>
                <input v-model="programForm.rating" type="text" class="input-field" placeholder="TV-14" />
              </div>
            </div>
            <div class="flex justify-end gap-3 pt-4">
              <button type="button" @click="closeModal" class="btn-secondary">Cancel</button>
              <button type="submit" :disabled="programForm.processing" class="btn-primary">
                {{ programForm.processing ? 'Saving...' : 'Save Program' }}
              </button>
            </div>
          </form>
        </div>
      </Modal>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Pagination from '@/Components/Common/Pagination.vue'
import Modal from '@/Components/Common/Modal.vue'
import { Plus, RefreshCw, Pencil, Trash2, Download } from 'lucide-vue-next'

const props = defineProps({
  programs: { type: Object, required: true },
  channels: { type: Array, default: () => [] },
  stats: { type: Object, default: () => ({}) },
})

const search = ref('')
const filterChannel = ref('')
const filterDate = ref('')
const showAddModal = ref(false)
const editingProgram = ref(null)

const programForm = useForm({
  channel_id: '',
  title: '',
  description: '',
  start_time: '',
  end_time: '',
  season: null,
  episode: null,
  rating: '',
})

const applyFilters = () => {
  router.get(route('admin.epg.programs'), {
    search: search.value,
    channel_id: filterChannel.value,
    date: filterDate.value,
  }, { preserveState: true, replace: true })
}

const formatTime = (dt) => {
  if (!dt) return ''
  return new Date(dt).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
}

const getDuration = (start, end) => {
  const ms = new Date(end) - new Date(start)
  const hours = Math.floor(ms / 3600000)
  const mins = Math.floor((ms % 3600000) / 60000)
  return hours > 0 ? `${hours}h ${mins}m` : `${mins}m`
}

const editProgram = (program) => {
  editingProgram.value = program
  programForm.channel_id = program.channel_id
  programForm.title = program.title
  programForm.description = program.description || ''
  programForm.start_time = program.start_time?.slice(0, 16) || ''
  programForm.end_time = program.end_time?.slice(0, 16) || ''
  programForm.season = program.season
  programForm.episode = program.episode
  programForm.rating = program.rating || ''
}

const closeModal = () => {
  showAddModal.value = false
  editingProgram.value = null
  programForm.reset()
}

const saveProgram = () => {
  if (editingProgram.value) {
    programForm.put(route('admin.epg.programs.update', editingProgram.value.id), {
      onSuccess: () => closeModal(),
    })
  } else {
    programForm.post(route('admin.epg.programs.store'), {
      onSuccess: () => closeModal(),
    })
  }
}

const confirmDelete = (program) => {
  if (confirm(`Delete "${program.title}"?`)) {
    router.delete(route('admin.epg.programs.destroy', program.id))
  }
}

const refreshEpg = () => {
  router.post(route('admin.epg.update-all'))
}

const exportPrograms = () => {
  const params = {
    search: search.value,
    channel_id: filterChannel.value,
    date: filterDate.value,
    format: 'csv',
  }
  window.location.href = route('admin.epg.programs.export', params)
}
</script>
