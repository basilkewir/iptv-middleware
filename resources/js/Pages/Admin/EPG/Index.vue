<template>
  <AdminLayout>
    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-white">EPG Sources</h1>
          <p class="text-gray-400 mt-1">Manage Electronic Program Guide data sources &amp; open-source feeds</p>
        </div>
        <div class="flex gap-2">
          <button @click="syncConfig" :disabled="syncing" class="px-3 py-2 bg-green-600/20 hover:bg-green-600 text-green-400 hover:text-white rounded-lg transition flex items-center gap-2 text-sm disabled:opacity-50">
            <Download :class="{ 'animate-spin': syncing }" class="w-4 h-4" />
            {{ syncing ? 'Syncing...' : 'Sync Config' }}
          </button>
          <button @click="fetchAll" :disabled="fetchingAll" class="px-3 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition flex items-center gap-2 text-sm disabled:opacity-50">
            <RefreshCw :class="{ 'animate-spin': fetchingAll }" class="w-4 h-4" />
            {{ fetchingAll ? 'Fetching...' : 'Fetch All' }}
          </button>
          <label class="px-3 py-2 bg-purple-600/20 hover:bg-purple-600 text-purple-400 hover:text-white rounded-lg transition flex items-center gap-2 text-sm cursor-pointer">
            <Upload class="w-4 h-4" />
            Import File
            <input type="file" accept=".xml,.xmltv,.gz" class="hidden" @change="importFile" />
          </label>
          <button @click="openAddModal" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition flex items-center gap-2">
            <Plus class="w-4 h-4" />
            Add Source
          </button>
        </div>
      </div>

      <!-- Status -->
      <div v-if="statusMsg" class="p-4 rounded-lg flex items-center gap-2" :class="statusOk ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'">
        <CheckCircle v-if="statusOk" class="w-4 h-4" />
        <AlertCircle v-else class="w-4 h-4" />
        {{ statusMsg }}
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
                  <FileText class="w-5 h-5" :class="sourceTypeColor(source)" />
                </div>
                <div>
                  <h3 class="text-white font-semibold">{{ source.name }}</h3>
                  <span class="text-xs px-2 py-0.5 rounded-full" :class="sourceTypeBadge(source)">
                    {{ source.type?.toUpperCase() || 'XMLTV' }}
                  </span>
                </div>
              </div>
              <span class="w-2 h-2 rounded-full" :class="source.is_active ? 'bg-green-400' : 'bg-gray-500'" />
            </div>

            <p class="text-gray-400 text-sm mb-3 truncate" :title="source.url">{{ source.url }}</p>

            <div class="grid grid-cols-2 gap-3 mb-4">
              <div class="bg-gray-700/50 rounded-lg p-2.5">
                <p class="text-gray-400 text-xs">Programs</p>
                <p class="text-white font-medium">{{ source.programs_count || 0 }}</p>
              </div>
              <div class="bg-gray-700/50 rounded-lg p-2.5">
                <p class="text-gray-400 text-xs">Interval</p>
                <p class="text-white font-medium">{{ formatInterval(source.update_interval) }}</p>
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
              {{ previewing === source.id ? 'Loading...' : 'Preview' }}
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
        <p class="text-gray-400 mb-4">Click <strong>Sync Config</strong> to import open-source feeds, or add one manually.</p>
        <div class="flex justify-center gap-3">
          <button @click="syncConfig" class="px-6 py-2 bg-green-600 hover:bg-green-500 text-white rounded-lg transition">
            Sync Config
          </button>
          <button @click="openAddModal" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition">
            Add Manually
          </button>
        </div>
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
              <label class="block text-sm font-medium text-gray-300 mb-2">EPG URL</label>
              <input v-model="form.url" type="url" placeholder="https://iptv-epg.org/files/epg-us.xml.gz" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500" />
              <p class="text-xs text-gray-500 mt-1">Supports .xml, .xml.gz, and regular XMLTV feeds</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Type</label>
                <select v-model="form.type" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option value="xmltv">XMLTV</option>
                  <option value="json">JSON</option>
                  <option value="custom">Custom</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Update Interval</label>
                <select v-model="form.update_interval" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                  <option :value="3600">Every 1 hour</option>
                  <option :value="7200">Every 2 hours</option>
                  <option :value="14400">Every 4 hours</option>
                  <option :value="21600">Every 6 hours</option>
                  <option :value="43200">Every 12 hours</option>
                  <option :value="86400">Every 24 hours</option>
                </select>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Language</label>
                <input v-model="form.language" type="text" placeholder="en" maxlength="10" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Timezone</label>
                <input v-model="form.timezone" type="text" placeholder="UTC" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500" />
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
              <p class="text-gray-400 text-sm">{{ previewSourceName }}</p>
            </div>
            <button @click="showPreview = false" class="text-gray-400 hover:text-white transition">
              <X class="w-5 h-5" />
            </button>
          </div>
          <div class="p-6 overflow-y-auto flex-1">
            <div v-if="previewLoading" class="text-center py-8">
              <RefreshCw class="w-8 h-8 mx-auto animate-spin text-indigo-400" />
              <p class="text-gray-400 mt-2">Loading preview...</p>
            </div>
            <div v-else-if="previewData.length" class="space-y-3">
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
              No program data available. Fetch the source first or check that channels are mapped.
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
  Pencil, Trash2, X, Save, FileText, Download, Upload, AlertCircle
} from 'lucide-vue-next'

const props = defineProps({ sources: { type: Array, default: () => [] } })

const showModal = ref(false)
const showPreview = ref(false)
const editingSource = ref(null)
const fetching = ref(null)
const fetchingAll = ref(false)
const syncing = ref(false)
const previewing = ref(null)
const previewLoading = ref(false)
const previewData = ref([])
const previewSourceName = ref('')
const statusMsg = ref('')
const statusOk = ref(true)

const form = ref({
  name: '', url: '', type: 'xmltv', update_interval: 14400,
  language: '', timezone: 'UTC', is_active: true
})

const activeCount = computed(() => (props.sources || []).filter(s => s.is_active).length)
const totalPrograms = computed(() => (props.sources || []).reduce((sum, s) => sum + (s.programs_count || 0), 0))
const lastFetchTime = computed(() => {
  const sorted = [...(props.sources || [])].filter(s => s.last_fetched_at).sort((a, b) => new Date(b.last_fetched_at) - new Date(a.last_fetched_at))
  return sorted[0] ? formatRelativeTime(sorted[0].last_fetched_at) : 'Never'
})

const sourceTypeBg = (source) => ({
  'bg-indigo-500/20': source.type === 'xmltv' || !source.type,
  'bg-green-500/20': source.type === 'json',
  'bg-yellow-500/20': source.type === 'custom',
})

const sourceTypeColor = (source) => ({
  'text-indigo-400': source.type === 'xmltv' || !source.type,
  'text-green-400': source.type === 'json',
  'text-yellow-400': source.type === 'custom',
})

const sourceTypeBadge = (source) => ({
  'bg-indigo-500/20 text-indigo-400': source.type === 'xmltv' || !source.type,
  'bg-green-500/20 text-green-400': source.type === 'json',
  'bg-yellow-500/20 text-yellow-400': source.type === 'custom',
})

const formatInterval = (seconds) => {
  if (!seconds) return '4h'
  const hours = Math.floor(seconds / 3600)
  if (hours < 1) return `${Math.floor(seconds / 60)}m`
  return `${hours}h`
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

const showStatus = (msg, ok = true) => {
  statusMsg.value = msg
  statusOk.value = ok
  setTimeout(() => { statusMsg.value = '' }, 8000)
}

const openAddModal = () => {
  editingSource.value = null
  form.value = { name: '', url: '', type: 'xmltv', update_interval: 14400, language: '', timezone: 'UTC', is_active: true }
  showModal.value = true
}

const editSource = (source) => {
  editingSource.value = source
  form.value = {
    name: source.name, url: source.url, type: source.type || 'xmltv',
    update_interval: source.update_interval || 14400,
    language: source.language || '', timezone: source.timezone || 'UTC',
    is_active: source.is_active
  }
  showModal.value = true
}

const saveSource = () => {
  if (editingSource.value) {
    router.put(route('admin.epg.update', editingSource.value.id), form.value, {
      onSuccess: () => { showModal.value = false; showStatus('Source updated.') }
    })
  } else {
    router.post(route('admin.epg.store'), form.value, {
      onSuccess: () => { showModal.value = false; showStatus('Source added.') }
    })
  }
}

const deleteSource = (source) => {
  if (confirm(`Delete EPG source "${source.name}"? All associated programs will be removed.`)) {
    router.delete(route('admin.epg.destroy', source.id), {
      onSuccess: () => showStatus('Source deleted.')
    })
  }
}

const fetchSource = (source) => {
  fetching.value = source.id
  router.post(route('admin.epg.update.trigger'), { source_id: source.id }, {
    onFinish: () => { fetching.value = null },
    onSuccess: (page) => {
      const msg = page.props.flash?.success || 'Fetch started.'
      showStatus(msg)
    },
    onError: () => showStatus('Fetch failed.', false)
  })
}

const fetchAll = () => {
  if (!confirm('Fetch EPG data from all active sources now?')) return
  fetchingAll.value = true
  router.post(route('admin.epg.update-all'), {}, {
    onFinish: () => { fetchingAll.value = false },
    onSuccess: (page) => {
      const msg = page.props.flash?.success || 'EPG update started.'
      showStatus(msg)
    },
    onError: () => showStatus('EPG update failed.', false)
  })
}

const syncConfig = () => {
  syncing.value = true
  router.post(route('admin.epg.refresh'), {}, {
    onFinish: () => { syncing.value = false },
    onSuccess: (page) => {
      const msg = page.props.flash?.success || 'Config synced.'
      showStatus(msg)
    },
    onError: () => showStatus('Sync failed.', false)
  })
}

const importFile = (event) => {
  const file = event.target.files[0]
  if (!file) return

  const formData = new FormData()
  formData.append('epg_file', file)

  router.post(route('admin.epg.import'), formData, {
    forceFormData: true,
    onSuccess: (page) => {
      const msg = page.props.flash?.success || 'File imported.'
      showStatus(msg)
    },
    onError: () => showStatus('Import failed.', false)
  })

  event.target.value = ''
}

const previewSource = (source) => {
  previewing.value = source.id
  previewSourceName.value = source.name
  previewData.value = []
  previewLoading.value = true
  showPreview.value = true

  fetch(route('admin.epg.preview', source.id))
    .then(r => r.json())
    .then(data => {
      const preview = data?.data?.preview
      if (preview?.programme) {
        previewData.value = preview.programme.slice(0, 30).map(p => ({
          title: p.title?.['#text'] || p.title || 'Untitled',
          channel: p['@attributes']?.channel || '',
          start: p['@attributes']?.start || '',
          end: p['@attributes']?.stop || '',
          description: p.desc?.['#text'] || p.desc || '',
        }))
      } else if (preview?.channel) {
        const channels = Array.isArray(preview.channel) ? preview.channel : [preview.channel]
        const programmes = Array.isArray(preview.programme) ? preview.programme : (preview.programme ? [preview.programme] : [])
        previewData.value = programmes.slice(0, 30).map(p => ({
          title: p.title?.['#text'] || p.title || 'Untitled',
          channel: p['@attributes']?.channel || '',
          start: p['@attributes']?.start || '',
          end: p['@attributes']?.stop || '',
          description: p.desc?.['#text'] || p.desc || '',
        }))
      }
      previewLoading.value = false
    })
    .catch(() => { previewLoading.value = false })
}
</script>
