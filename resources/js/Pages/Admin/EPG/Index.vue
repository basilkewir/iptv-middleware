<template>
  <AdminLayout>
    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-white">EPG Sources</h1>
          <p class="text-gray-400 mt-1">Manage Electronic Program Guide data sources</p>
        </div>
        <button @click="openAddModal" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition flex items-center gap-2">
          <Plus class="w-4 h-4" />
          Add Source
        </button>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-indigo-500/20 rounded-lg">
              <Globe class="w-5 h-5 text-indigo-400" />
            </div>
            <div>
              <p class="text-gray-400 text-sm">Total Sources</p>
              <p class="text-2xl font-bold text-white">{{ sources?.length || 0 }}</p>
            </div>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-green-500/20 rounded-lg">
              <CheckCircle class="w-5 h-5 text-green-400" />
            </div>
            <div>
              <p class="text-gray-400 text-sm">Active</p>
              <p class="text-2xl font-bold text-green-400">{{ activeCount }}</p>
            </div>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-purple-500/20 rounded-lg">
              <Tv class="w-5 h-5 text-purple-400" />
            </div>
            <div>
              <p class="text-gray-400 text-sm">Total Programs</p>
              <p class="text-2xl font-bold text-white">{{ totalPrograms }}</p>
            </div>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-yellow-500/20 rounded-lg">
              <Clock class="w-5 h-5 text-yellow-400" />
            </div>
            <div>
              <p class="text-gray-400 text-sm">Last Fetch</p>
              <p class="text-lg font-bold text-white">{{ lastFetchTime }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Sources Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="source in sources" :key="source.id" class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden hover:border-gray-600 transition">
          <div class="p-6">
            <div class="flex items-center justify-between mb-4">
              <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-lg" :class="sourceTypeBg(source)">
                  <component :is="sourceTypeIcon(source)" class="w-5 h-5" :class="sourceTypeColor(source)" />
                </div>
                <div>
                  <h3 class="text-white font-semibold">{{ source.name }}</h3>
                  <span class="text-xs px-2 py-0.5 rounded-full" :class="sourceTypeBadge(source)">
                    {{ source.source_type || 'XMLTV' }}
                  </span>
                </div>
              </div>
              <div class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full" :class="source.is_active ? 'bg-green-400' : 'bg-gray-500'" />
              </div>
            </div>

            <p class="text-gray-400 text-sm mb-3 truncate" :title="source.url">{{ source.url }}</p>

            <div class="grid grid-cols-2 gap-3 mb-4">
              <div class="bg-gray-700/50 rounded-lg p-2.5">
                <p class="text-gray-400 text-xs">Programs</p>
                <p class="text-white font-medium">{{ source.program_count || 0 }}</p>
              </div>
              <div class="bg-gray-700/50 rounded-lg p-2.5">
                <p class="text-gray-400 text-xs">Interval</p>
                <p class="text-white font-medium">{{ source.fetch_interval || 24 }}h</p>
              </div>
            </div>

            <div class="text-xs text-gray-500 mb-3">
              Last fetched: {{ source.last_fetched_at ? formatRelativeTime(source.last_fetched_at) : 'Never' }}
            </div>

            <!-- Preview Button -->
            <button
              @click="previewSource(source)"
              :disabled="previewing === source.id"
              class="w-full px-3 py-2 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded-lg transition flex items-center justify-center gap-2 mb-3 disabled:opacity-50"
            >
              <Eye class="w-3.5 h-3.5" />
              {{ previewing === source.id ? 'Loading...' : 'Preview Programs' }}
            </button>

            <!-- Actions -->
            <div class="flex items-center justify-between pt-3 border-t border-gray-700">
              <button @click="fetchSource(source)" :disabled="fetching === source.id" class="px-3 py-1.5 bg-indigo-600/20 hover:bg-indigo-600 text-indigo-400 hover:text-white text-sm rounded-lg transition flex items-center gap-1.5 disabled:opacity-50">
                <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': fetching === source.id }" />
                Fetch
              </button>
              <div class="flex gap-1.5">
                <button @click="editSource(source)" class="p-1.5 bg-blue-600/20 hover:bg-blue-600 text-blue-400 hover:text-white rounded-lg transition">
                  <Pencil class="w-3.5 h-3.5" />
                </button>
                <button @click="deleteSource(source)" class="p-1.5 bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white rounded-lg transition">
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="!sources?.length" class="bg-gray-800 rounded-xl border border-gray-700 p-12 text-center">
        <Calendar class="w-16 h-16 mx-auto mb-4 text-gray-600" />
        <h3 class="text-lg font-medium text-white mb-2">No EPG sources configured</h3>
        <p class="text-gray-400 mb-6">Add an XMLTV orother EPG source to populate your program guide</p>
        <button @click="openAddModal" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition">
          Add Source
        </button>
      </div>

      <!-- Add/Edit Modal -->
      <div v-if="showModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
        <div class="bg-gray-800 rounded-xl p-6 w-full max-w-lg border border-gray-700 shadow-2xl">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-semibold text-white">{{ editingSource ? 'Edit' : 'Add' }} EPG Source</h2>
            <button @click="showModal = false" class="text-gray-400 hover:text-white transition">
              <X class="w-5 h-5" />
            </button>
          </div>
          <form @submit.prevent="saveSource" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Source Name</label>
              <input v-model="form.name" type="text" placeholder="e.g. US Channels EPG" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Source Type</label>
              <select v-model="form.source_type" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="XMLTV">XMLTV (Standard)</option>
                <option value="XTREAM">Xtream Codes API</option>
                <option value="M3U">M3U with EPG</option>
                <option value="JSON">JSON Feed</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">EPG URL</label>
              <input v-model="form.url" type="url" placeholder="http://example.com/epg.xml" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Fetch Interval (hours)</label>
                <select v-model="form.fetch_interval" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option :value="6">Every 6 hours</option>
                  <option :value="12">Every 12 hours</option>
                  <option :value="24">Every 24 hours</option>
                  <option :value="48">Every 48 hours</option>
                  <option :value="168">Weekly</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Priority</label>
                <select v-model="form.priority" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option :value="1">High</option>
                  <option :value="2">Medium</option>
                  <option :value="3">Low</option>
                </select>
              </div>
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.is_active" type="checkbox" id="epg_active" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="epg_active" class="text-gray-300 text-sm">Active (will fetch data automatically)</label>
            </div>
            <div class="flex justify-end gap-3 mt-6">
              <button type="button" @click="showModal = false" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">Cancel</button>
              <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition flex items-center gap-2">
                <Save class="w-4 h-4" />
                {{ editingSource ? 'Update' : 'Add' }} Source
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Preview Modal -->
      <div v-if="showPreview" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
        <div class="bg-gray-800 rounded-xl w-full max-w-3xl border border-gray-700 shadow-2xl max-h-[80vh] flex flex-col">
          <div class="flex items-center justify-between p-6 border-b border-gray-700">
            <div>
              <h2 class="text-lg font-semibold text-white">Program Preview</h2>
              <p class="text-gray-400 text-sm">{{ previewSourceName }} - Sample programs</p>
            </div>
            <button @click="showPreview = false" class="text-gray-400 hover:text-white transition">
              <X class="w-5 h-5" />
            </button>
          </div>
          <div class="p-6 overflow-y-auto flex-1">
            <div v-if="previewData.length" class="space-y-3">
              <div v-for="(prog, i) in previewData" :key="i" class="bg-gray-700/50 rounded-lg p-4">
                <div class="flex items-center justify-between mb-1">
                  <span class="text-white font-medium">{{ prog.title }}</span>
                  <span class="text-xs px-2 py-0.5 bg-indigo-500/20 text-indigo-400 rounded">{{ prog.channel }}</span>
                </div>
                <p class="text-gray-400 text-sm">{{ prog.start }} - {{ prog.end }}</p>
                <p v-if="prog.description" class="text-gray-500 text-xs mt-1">{{ prog.description }}</p>
              </div>
            </div>
            <div v-else class="text-center text-gray-400 py-8">
              No program data available yet. Fetch the source first.
            </div>
          </div>
          <div class="p-4 border-t border-gray-700 flex justify-end">
            <button @click="showPreview = false" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">Close</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  Plus, Globe, CheckCircle, Tv, Clock, Calendar, Eye, RefreshCw,
  Pencil, Trash2, X, Save, Radio, FileText, Database
} from 'lucide-vue-next'

const props = defineProps({ sources: { type: Array, default: () => [] } })

const showModal = ref(false)
const showPreview = ref(false)
const editingSource = ref(null)
const fetching = ref(null)
const previewing = ref(null)
const previewData = ref([])
const previewSourceName = ref('')
const form = ref({
  name: '', url: '', source_type: 'XMLTV', fetch_interval: 24,
  priority: 2, is_active: true
})

const activeCount = computed(() => (props.sources || []).filter(s => s.is_active).length)
const totalPrograms = computed(() => (props.sources || []).reduce((sum, s) => sum + (s.program_count || 0), 0))
const lastFetchTime = computed(() => {
  const sorted = [...(props.sources || [])].filter(s => s.last_fetched_at).sort((a, b) => new Date(b.last_fetched_at) - new Date(a.last_fetched_at))
  return sorted[0] ? formatRelativeTime(sorted[0].last_fetched_at) : 'Never'
})

const sourceTypeBg = (source) => ({
  'bg-indigo-500/20': source.source_type === 'XMLTV' || !source.source_type,
  'bg-green-500/20': source.source_type === 'XTREAM',
  'bg-purple-500/20': source.source_type === 'M3U',
  'bg-yellow-500/20': source.source_type === 'JSON',
})

const sourceTypeColor = (source) => ({
  'text-indigo-400': source.source_type === 'XMLTV' || !source.source_type,
  'text-green-400': source.source_type === 'XTREAM',
  'text-purple-400': source.source_type === 'M3U',
  'text-yellow-400': source.source_type === 'JSON',
})

const sourceTypeBadge = (source) => ({
  'bg-indigo-500/20 text-indigo-400': source.source_type === 'XMLTV' || !source.source_type,
  'bg-green-500/20 text-green-400': source.source_type === 'XTREAM',
  'bg-purple-500/20 text-purple-400': source.source_type === 'M3U',
  'bg-yellow-500/20 text-yellow-400': source.source_type === 'JSON',
})

const sourceTypeIcon = (source) => {
  const types = { XMLTV: FileText, XTREAM: Radio, M3U: Database, JSON: Tv }
  return types[source.source_type] || FileText
}

const formatRelativeTime = (date) => {
  if (!date) return 'Never'
  const diff = Date.now() - new Date(date).getTime()
  const hours = Math.floor(diff / 3600000)
  if (hours < 1) return 'Just now'
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  return `${days}d ago`
}

const openAddModal = () => {
  editingSource.value = null
  form.value = { name: '', url: '', source_type: 'XMLTV', fetch_interval: 24, priority: 2, is_active: true }
  showModal.value = true
}

const editSource = (source) => {
  editingSource.value = source
  form.value = {
    name: source.name, url: source.url, source_type: source.source_type || 'XMLTV',
    fetch_interval: source.fetch_interval || 24, priority: source.priority || 2,
    is_active: source.is_active
  }
  showModal.value = true
}

const saveSource = () => {
  if (editingSource.value) {
    router.put(route('admin.epg.update', editingSource.value.id), form.value)
  } else {
    router.post(route('admin.epg.store'), form.value)
  }
  showModal.value = false
  editingSource.value = null
}

const deleteSource = (source) => {
  if (confirm(`Delete EPG source "${source.name}"? All associated program data will be removed.`)) {
    router.delete(route('admin.epg.destroy', source.id))
  }
}

const fetchSource = (source) => {
  fetching.value = source.id
  router.post(route('admin.epg.update.trigger'), { source_id: source.id }, {
    onFinish: () => { fetching.value = null }
  })
}

const previewSource = (source) => {
  previewing.value = source.id
  previewSourceName.value = source.name
  previewData.value = []
  showPreview.value = true
  // Simulated preview data - in production this would be an API call
  setTimeout(() => {
    previewData.value = [
      { title: 'Morning News', channel: 'CNN', start: '06:00 AM', end: '09:00 AM', description: 'Live morning news coverage' },
      { title: 'Sports Center', channel: 'ESPN', start: '09:00 AM', end: '11:00 AM', description: 'Sports highlights and analysis' },
      { title: 'Movie Premiere', channel: 'HBO', start: '08:00 PM', end: '10:30 PM', description: 'New movie premiere' },
    ]
    previewing.value = null
  }, 1000)
}
</script>
