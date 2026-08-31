<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h3 class="text-lg font-semibold text-white">Content Library</h3>
      <div class="flex items-center gap-2">
        <button @click="openFolderModal('create')"
          class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition flex items-center gap-2">
          <FolderPlus class="w-4 h-4" /> New Folder
        </button>
        <label class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition flex items-center gap-2 cursor-pointer">
          <Upload class="w-4 h-4" /> Upload Files
          <input type="file" accept=".mp4,.mkv,.avi,.mov,.webm" multiple class="hidden" ref="fileInputRef" @change="handleFileUpload" />
        </label>
      </div>
    </div>

    <!-- Upload progress -->
    <div v-if="uploading" class="flex items-center gap-3 p-4 bg-gray-800 rounded-lg border border-indigo-700">
      <Loader2 class="w-5 h-5 animate-spin text-indigo-500 shrink-0" />
      <div class="flex-1 min-w-0">
        <div class="text-white text-sm truncate">{{ uploadFile?.name }}</div>
        <div class="w-full bg-gray-700 rounded-full h-1.5 mt-2">
          <div class="bg-indigo-500 h-1.5 rounded-full transition-all" :style="{ width: uploadProgress + '%' }"></div>
        </div>
      </div>
      <span class="text-sm text-gray-400 shrink-0">{{ Math.round(uploadProgress) }}%</span>
    </div>

    <!-- Error banner -->
    <div v-if="error" class="p-3 bg-red-900/30 border border-red-700/50 rounded-lg text-red-400 text-sm flex items-center justify-between">
      {{ error }}
      <button @click="error = ''" class="text-red-500 hover:text-red-300 ml-3">✕</button>
    </div>

    <div class="flex gap-6">
      <!-- Folder sidebar -->
      <aside class="w-64 shrink-0">
        <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
          <div class="px-4 py-3 border-b border-gray-700 flex items-center justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Media Library</span>
            <button @click="openFolderModal('create')" class="text-gray-400 hover:text-indigo-400 transition" title="New folder">
              <FolderPlus class="w-4 h-4" />
            </button>
          </div>
          <div class="p-2 space-y-0.5 max-h-[540px] overflow-y-auto">
            <button @click="selectFolder(null)"
              class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm transition text-left"
              :class="currentFolderId === null ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-700'">
              <FolderOpen class="w-4 h-4 shrink-0" /> All Media
              <span class="ml-auto text-xs" :class="currentFolderId === null ? 'text-indigo-200' : 'text-gray-500'">{{ totalCount }}</span>
            </button>
            <button @click="selectFolder('uncategorized')"
              class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm transition text-left"
              :class="currentFolderId === 'uncategorized' ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:bg-gray-700'">
              <Inbox class="w-4 h-4 shrink-0" /> Uncategorized
              <span class="ml-auto text-xs" :class="currentFolderId === 'uncategorized' ? 'text-indigo-200' : 'text-gray-500'">{{ uncategorizedCount }}</span>
            </button>
            <div v-if="folderTree.length" class="border-t border-gray-700/60 my-1.5"></div>

            <div v-for="node in folderTree" :key="node.folder.id">
              <div class="group flex items-center rounded-lg transition"
                :class="[currentFolderId === node.folder.id
                  ? 'bg-indigo-600 text-white'
                  : 'text-gray-300 hover:bg-gray-700', dragOverFolder === node.folder.id ? 'ring-2 ring-indigo-400' : '']"
                @dragover.prevent="onFolderDragOver(node.folder.id)"
                @dragleave="onFolderDragLeave(node.folder.id)"
                @drop.prevent="onFolderDrop(node.folder.id)">
                <button @click="selectFolder(node.folder.id)"
                  class="flex-1 flex items-center gap-2 px-3 py-2 text-sm transition text-left min-w-0">
                  <ChevronRight v-if="node.children.length" @click.stop="toggleExpand(node.folder.id)"
                    class="w-3.5 h-3.5 shrink-0 cursor-pointer"
                    :class="[expanded.has(node.folder.id) ? '' : 'rotate-[-90deg]', currentFolderId === node.folder.id ? 'text-indigo-200' : 'text-gray-500']" />
                  <span v-else class="w-3.5 shrink-0"></span>
                  <Folder class="w-4 h-4 shrink-0" :class="currentFolderId === node.folder.id ? 'text-indigo-200' : 'text-indigo-400'" />
                  <span class="truncate">{{ node.folder.name }}</span>
                  <span class="ml-auto text-xs shrink-0" :class="currentFolderId === node.folder.id ? 'text-indigo-200' : 'text-gray-500'">
                    {{ node.folder.contents_count ?? 0 }}
                  </span>
                </button>
                <div class="flex items-center pr-1 shrink-0 opacity-0 group-hover:opacity-100 transition">
                  <button @click.stop="openFolderModal('rename', node.folder)" class="p-1 hover:text-indigo-300 transition" title="Rename">
                    <Edit3 class="w-3.5 h-3.5" />
                  </button>
                  <button @click.stop="openFolderModal('delete', node.folder)" class="p-1 hover:text-red-400 transition" title="Delete">
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
              <div v-if="expanded.has(node.folder.id) && node.children.length" class="ml-5 space-y-0.5">
                <div v-for="child in node.children" :key="child.folder.id">
                  <div class="group flex items-center rounded-lg transition"
                    :class="[currentFolderId === child.folder.id
                      ? 'bg-indigo-600 text-white'
                      : 'text-gray-300 hover:bg-gray-700', dragOverFolder === child.folder.id ? 'ring-2 ring-indigo-400' : '']"
                    @dragover.prevent="onFolderDragOver(child.folder.id)"
                    @dragleave="onFolderDragLeave(child.folder.id)"
                    @drop.prevent="onFolderDrop(child.folder.id)">
                    <button @click="selectFolder(child.folder.id)"
                      class="flex-1 flex items-center gap-2 px-3 py-2 text-sm transition text-left min-w-0">
                      <Folder class="w-4 h-4 shrink-0" :class="currentFolderId === child.folder.id ? 'text-indigo-200' : 'text-indigo-400'" />
                      <span class="truncate">{{ child.folder.name }}</span>
                      <span class="ml-auto text-xs shrink-0" :class="currentFolderId === child.folder.id ? 'text-indigo-200' : 'text-gray-500'">
                        {{ child.folder.contents_count ?? 0 }}
                      </span>
                    </button>
                    <div class="flex items-center pr-1 shrink-0 opacity-0 group-hover:opacity-100 transition">
                      <button @click.stop="openFolderModal('rename', child.folder)" class="p-1 hover:text-indigo-300 transition" title="Rename">
                        <Edit3 class="w-3.5 h-3.5" />
                      </button>
                      <button @click.stop="openFolderModal('delete', child.folder)" class="p-1 hover:text-red-400 transition" title="Delete">
                        <Trash2 class="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="!loadingFolders && !folderTree.length" class="text-center py-6 text-gray-500 text-sm">
              <p>No folders yet</p>
              <button @click="openFolderModal('create')" class="text-indigo-400 hover:text-indigo-300 mt-2 text-xs">+ Create one</button>
            </div>
          </div>
        </div>
      </aside>

      <!-- Main media area -->
      <div class="flex-1 min-w-0"
        @dragover.prevent="onAreaDragOver"
        @dragleave="onAreaDragLeave"
        @drop.prevent="onAreaDrop">
        <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
          <!-- Breadcrumb + current folder header -->
          <div class="px-4 py-3 border-b border-gray-700 flex items-center gap-2 text-sm">
            <template v-for="(crumb, i) in breadcrumb" :key="i">
              <ChevronRight v-if="i > 0" class="w-3.5 h-3.5 text-gray-600" />
              <span class="text-white font-medium">{{ crumb }}</span>
            </template>
            <span class="ml-auto text-xs text-gray-500">{{ content?.total ?? 0 }} file{{ content?.total === 1 ? '' : 's' }}</span>
          </div>

          <!-- Drag drop overlay -->
          <div v-if="areaDragging" class="m-4 rounded-xl border-2 border-dashed border-indigo-500 bg-indigo-950/40 min-h-[220px] flex flex-col items-center justify-center text-indigo-300 pointer-events-none">
            <UploadCloud class="w-12 h-12 mb-3" />
            <p class="font-medium">Drop files to upload</p>
            <p class="text-xs mt-1 text-indigo-400">Videos uploaded to “{{ currentFolderName }}”</p>
          </div>

          <!-- Grid -->
          <div v-else-if="content?.data?.length" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 p-4">
            <div v-for="item in content.data" :key="item.id"
              draggable="true"
              @dragstart="onCardDragStart(item)"
              @dragend="onCardDragEnd"
              class="group bg-gray-900 rounded-xl border border-gray-700 hover:border-indigo-500/60 transition overflow-hidden">
              <div class="relative aspect-video bg-gray-800 flex items-center justify-center overflow-hidden cursor-pointer" @click="previewItem(item)">
                <img v-if="item.thumbnail_url" :src="'/storage/' + item.thumbnail_url" class="w-full h-full object-cover" loading="lazy" />
                <Play v-else class="w-8 h-8 text-gray-600" />
                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2">
                  <button @click="previewItem(item)" class="p-2 bg-white/20 hover:bg-indigo-600 rounded-full text-white transition" title="Preview">
                    <Play class="w-4 h-4" />
                  </button>
                  <button @click="addToPlaylist(item)" class="p-2 bg-white/20 hover:bg-indigo-600 rounded-full text-white transition" title="Add to playlist">
                    <Plus class="w-4 h-4" />
                  </button>
                </div>
              </div>
              <div class="p-3">
                <div class="text-white text-sm font-medium truncate" :title="item.title || item.file_name">{{ item.title || item.file_name }}</div>
                <div class="flex gap-2 text-xs text-gray-400 mt-1">
                  <span :class="qualityColor(item.quality_level)" class="font-medium uppercase">{{ item.quality_level }}</span>
                  <span>{{ formatDuration(item.duration) }}</span>
                  <span>{{ formatSize(item.file_size) }}</span>
                </div>
                <div class="flex items-center justify-between mt-2">
                  <span :class="item.is_transcoded ? 'text-green-400' : 'text-yellow-400'" class="text-xs">
                    {{ item.is_transcoded ? '✓ Ready' : '⏳ Processing' }}
                  </span>
                  <button @click="removeContent(item)" class="p-1 text-gray-500 hover:text-red-400 rounded transition opacity-0 group-hover:opacity-100" title="Delete">
                    <Trash2 class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div v-else-if="!loading && !areaDragging" class="text-center py-16 text-gray-500">
            <Film class="w-12 h-12 mx-auto mb-3 opacity-50" />
            <p class="font-medium text-gray-400">No media in this folder</p>
            <p class="text-sm mt-1">Upload videos or drag & drop files here.</p>
          </div>

          <div v-if="loading" class="text-center py-10">
            <Loader2 class="w-8 h-8 animate-spin text-gray-500 mx-auto" />
          </div>

          <!-- Pagination -->
          <div v-if="content?.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-700">
            <span class="text-sm text-gray-400">{{ content.from }}–{{ content.to }} of {{ content.total }}</span>
            <div class="flex gap-1">
              <button @click="prevPage" :disabled="content.current_page <= 1"
                class="px-3 py-1 rounded-lg text-sm disabled:opacity-40 bg-gray-700 text-gray-400 hover:bg-gray-600">Prev</button>
              <span class="px-3 py-1 rounded-lg text-sm bg-indigo-600 text-white">{{ content.current_page }}</span>
              <button @click="nextPage" :disabled="content.current_page >= content.last_page"
                class="px-3 py-1 rounded-lg text-sm disabled:opacity-40 bg-gray-700 text-gray-400 hover:bg-gray-600">Next</button>
            </div>
          </div>
        </div>
        <p v-if="dragHint" class="text-xs text-gray-500 mt-3 flex items-center gap-1.5">
          <Move class="w-3.5 h-3.5" /> Drag “{{ dragHint }}” onto a folder in the sidebar to move it.
        </p>
      </div>
    </div>

    <!-- Preview modal -->
    <div v-if="previewItemModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm" @click.self="previewItemModal = null">
      <div class="bg-gray-800 rounded-xl border border-gray-700 w-full max-w-4xl overflow-hidden">
        <div class="flex items-center justify-between p-4 border-b border-gray-700">
          <div class="min-w-0">
            <h4 class="text-white font-semibold truncate">{{ previewItemModal.title || previewItemModal.file_name }}</h4>
            <p class="text-xs text-gray-400 mt-0.5">
              {{ previewItemModal.quality_level?.toUpperCase() }} • {{ formatDuration(previewItemModal.duration) }} • {{ formatSize(previewItemModal.file_size) }}
            </p>
          </div>
          <button @click="previewItemModal = null" class="text-gray-400 hover:text-white text-lg leading-none ml-3">✕</button>
        </div>
        <div class="p-4">
          <video :src="'/storage/' + previewItemModal.file_path" controls autoplay class="w-full rounded-lg bg-black aspect-video"></video>
        </div>
        <div class="flex items-center justify-end gap-2 px-4 pb-4">
          <button @click="addToPlaylist(previewItemModal); previewItemModal = null"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition flex items-center gap-2 text-sm">
            <Plus class="w-4 h-4" /> Add to Playlist
          </button>
        </div>
      </div>
    </div>

    <!-- Folder modal (create / rename / delete) -->
    <div v-if="folderModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
      <div class="bg-gray-800 rounded-xl border border-gray-700 w-full max-w-md">
        <div class="flex items-center justify-between p-4 border-b border-gray-700">
          <h4 class="text-white font-semibold">
            {{ folderModal.mode === 'create' ? 'New Folder' : folderModal.mode === 'rename' ? 'Rename Folder' : 'Delete Folder' }}
          </h4>
          <button @click="folderModal.open = false" class="text-gray-400 hover:text-white text-lg leading-none">✕</button>
        </div>
        <div class="p-4 space-y-4">
          <template v-if="folderModal.mode === 'delete'">
            <p class="text-sm text-gray-300">
              Delete “{{ folderModal.folder?.name }}”? Its files stay in the library — they will be moved to
              {{ folderModal.folder?.parent_id ? 'the parent folder' : 'Uncategorized' }}. Any subfolders are kept too.
            </p>
          </template>
          <template v-else>
            <div>
              <label class="block text-xs font-medium text-gray-400 mb-1.5">Name</label>
              <input v-model="folderModal.name" ref="folderNameInput"
                class="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white text-sm focus:border-indigo-500 focus:outline-none"
                placeholder="Folder name" @keydown.enter="submitFolderModal" />
            </div>
            <div v-if="folderModal.mode === 'create'">
              <label class="block text-xs font-medium text-gray-400 mb-1.5">Location</label>
              <select v-model="folderModal.parent" class="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white text-sm focus:border-indigo-500 focus:outline-none">
                <option :value="null">{{ currentFolderName || 'All Media / root' }}</option>
                <option v-for="f in folders" :key="f.id" :value="f.id">{{ '— '.repeat(depthOf(f.id)) }}{{ f.name }}</option>
              </select>
            </div>
          </template>
        </div>
        <div class="flex items-center justify-end gap-2 px-4 pb-4">
          <button @click="folderModal.open = false" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition text-sm">Cancel</button>
          <button v-if="folderModal.mode === 'delete'" @click="submitFolderModal"
            class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded-lg transition text-sm">Delete Folder</button>
          <button v-else @click="submitFolderModal"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition text-sm">
            {{ folderModal.mode === 'create' ? 'Create Folder' : 'Save' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { route } from '@/Composables/useRoute'
import { useApiFetch } from '@/Composables/useApiFetch'
import {
  Upload, UploadCloud, Trash2, Play, Plus, Loader2, ChevronRight,
  Folder, FolderPlus, FolderOpen, Inbox, Film, Edit3, Move,
} from 'lucide-vue-next'

const props = defineProps({
  channel: { type: Object, required: true },
})

const emit = defineEmits(['content-added'])

const { apiFetch } = useApiFetch()

// ── State ────────────────────────────────────────────────────────────────
const folders = ref([])
const content = ref(null)
const loading = ref(false)
const loadingFolders = ref(false)
const uploading = ref(false)
const uploadProgress = ref(0)
const uploadFile = ref(null)
const uploadQueue = ref([])
const error = ref('')
const currentFolderId = ref(null)
const expanded = ref(new Set())
const previewItemModal = ref(null)
const folderModal = ref({ open: false, mode: 'create', folder: null, name: '', parent: null })
const fileInputRef = ref(null)
const folderNameInput = ref(null)
const dragOverFolder = ref(null)
const areaDragging = ref(false)
const dragHint = ref('')

const channelSlug = computed(() => props.channel?.channel_slug)

// ── Folder tree helpers ───────────────────────────────────────────────────
const folderTree = computed(() => buildTree(null))

function buildTree(parentId) {
  return folders.value
    .filter((f) => String(f.parent_id ?? '') === String(parentId ?? ''))
    .map((f) => ({
      folder: f,
      children: buildTree(f.id),
    }))
}

function depthOf(id) {
  let d = 0
  let cur = folders.value.find((f) => f.id === id)
  const seen = new Set()
  while (cur?.parent_id && !seen.has(cur.id)) {
    seen.add(cur.id)
    d++
    cur = folders.value.find((f) => f.id === cur.parent_id)
  }
  return d
}

const folderLookup = computed(() => Object.fromEntries(folders.value.map((f) => [f.id, f])))

const currentFolderName = computed(() => {
  if (currentFolderId.value === null) return 'All Media'
  if (currentFolderId.value === 'uncategorized') return 'Uncategorized'
  return folderLookup.value[currentFolderId.value]?.name || 'All Media'
})

const breadcrumb = computed(() => {
  if (currentFolderId.value === null) return ['All Media']
  if (currentFolderId.value === 'uncategorized') return ['All Media', 'Uncategorized']
  const chain = []
  let id = currentFolderId.value
  const seen = new Set()
  while (id && !seen.has(id)) {
    seen.add(id)
    const f = folderLookup.value[id]
    if (!f) break
    chain.unshift(f.name)
    id = f.parent_id
  }
  return ['All Media', ...chain]
})

const totalCount = computed(() => content?.value ? content.value.total : 0)
const uncategorizedTotal = ref(0)
const uncategorizedCount = computed(() => uncategorizedTotal.value)

function toggleExpand(id) {
  const set = new Set(expanded.value)
  if (set.has(id)) set.delete(id)
  else set.add(id)
  expanded.value = set
}

// ── Fetching ──────────────────────────────────────────────────────────────
async function fetchFolders() {
  loadingFolders.value = true
  try {
    const res = await apiFetch(route('admin.channels.my-channel.folders', channelSlug.value))
    const json = await res.json()
    folders.value = json.folders || []
  } finally {
    loadingFolders.value = false
  }
  try {
    const res = await apiFetch(route('admin.channels.my-channel.content', channelSlug.value) + '?folder_id=null&per_page=1')
    const json = await res.json()
    uncategorizedTotal.value = json.content?.total ?? 0
  } catch (e) { /* optional */ }
}

async function fetchContent(page = 1) {
  loading.value = true
  try {
    let url = route('admin.channels.my-channel.content', channelSlug.value) + `?page=${page}&per_page=32`
    if (currentFolderId.value === 'uncategorized') url += '&folder_id=null'
    else if (currentFolderId.value !== null) url += `&folder_id=${currentFolderId.value}`
    const res = await apiFetch(url)
    const json = await res.json()
    content.value = json.content || json
  } finally {
    loading.value = false
  }
}

function selectFolder(id) {
  currentFolderId.value = id
  fetchContent(1)
}

const nextPage = () => content.value?.current_page < content.value.last_page && fetchContent(content.value.current_page + 1)
const prevPage = () => content.value?.current_page > 1 && fetchContent(content.value.current_page - 1)

// ── Uploads (button + drag & drop) ────────────────────────────────────────
function queueUpload(files) {
  const videos = Array.from(files).filter((f) => /\.(mp4|mkv|avi|mov|webm)$/i.test(f.name))
  if (!videos.length) {
    error.value = 'Only video files (.mp4, .mkv, .avi, .mov, .webm) can be uploaded.'
    return
  }
  uploadQueue.value.push(...videos)
  if (!uploading.value) processQueue()
}

async function processQueue() {
  const file = uploadQueue.value.shift()
  if (!file) {
    uploading.value = false
    return
  }
  uploading.value = true
  uploadProgress.value = 10
  uploadFile.value = file
  error.value = ''

  const formData = new FormData()
  formData.append('file', file)
  formData.append('title', file.name.replace(/\.[^.]+$/, ''))
  formData.append('description', '')
  if (currentFolderId.value && currentFolderId.value !== 'uncategorized') {
    formData.append('folder_id', currentFolderId.value)
  }

  try {
    const progressInterval = setInterval(() => {
      if (uploadProgress.value < 85) uploadProgress.value += 5
    }, 300)

    const res = await apiFetch(route('admin.channels.my-channel.content.upload', channelSlug.value), {
      method: 'POST',
      body: formData,
    })

    clearInterval(progressInterval)
    uploadProgress.value = 100

    if (res.ok) {
      emit('content-added')
      await fetchContent(content.value?.current_page || 1)
    } else {
      const json = await res.json().catch(() => ({}))
      error.value = json?.message || `Upload failed for ${file.name}`
    }
  } catch (e) {
    error.value = `Upload failed: ${file.name}`
  } finally {
    uploading.value = false
    uploadProgress.value = 0
    uploadFile.value = null
    if (fileInputRef.value) fileInputRef.value.value = ''
    processQueue()
  }
}

const handleFileUpload = (e) => {
  queueUpload(e.target?.files || [])
}

// Drag & drop upload on main area
function onAreaDragOver(e) {
  if (e.dataTransfer?.types?.includes('Files')) {
    areaDragging.value = true
  }
}
function onAreaDragLeave(e) {
  areaDragging.value = false
}
async function onAreaDrop(e) {
  areaDragging.value = false
  if (e.dataTransfer?.files?.length) queueUpload(e.dataTransfer.files)
}

// Card drag → move to folder
const draggedCardId = ref(null)

function onCardDragStart(item) {
  draggedCardId.value = item.id
  dragHint.value = item.title || item.file_name
}
function onCardDragEnd() {
  draggedCardId.value = null
  dragHint.value = ''
}

function onFolderDragOver(folderId) {
  dragOverFolder.value = folderId
}
function onFolderDragLeave(folderId) {
  if (dragOverFolder.value === folderId) dragOverFolder.value = null
}
async function onFolderDrop(folderId) {
  dragOverFolder.value = null
  if (!dragHint.value) return
  try {
    const res = await apiFetch(
      route('admin.channels.my-channel.content.update', [channelSlug.value, draggedCardId.value]),
      {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ folder_id: folderId }),
      }
    )
    if (!res.ok) {
      const json = await res.json().catch(() => ({}))
      error.value = json?.message || 'Failed to move file'
    } else {
      emit('content-added')
      await Promise.all([fetchContent(content.value?.current_page || 1), fetchFolders()])
      dragHint.value = ''
    }
  } catch (e) {
    error.value = 'Failed to move file'
  }
}

// ── Playlist / delete / preview ───────────────────────────────────────────
async function addToPlaylist(item) {
  const res = await apiFetch(route('admin.channels.my-channel.playlist.store', channelSlug.value), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ content_id: item.id }),
  })
  if (res.ok) {
    emit('content-added')
  } else {
    const json = await res.json().catch(() => ({}))
    error.value = json?.message || 'Failed to add to playlist'
  }
}

const previewItem = (item) => { previewItemModal.value = item }

async function removeContent(item) {
  if (!confirm(`Delete "${item.title || item.file_name}"?\nThis permanently removes the video file from the server.`)) return
  const res = await apiFetch(
    route('admin.channels.my-channel.content.destroy', [channelSlug.value, item.id]),
    { method: 'DELETE' }
  )
  if (res.ok) {
    await Promise.all([fetchContent(content.value?.current_page || 1), fetchFolders()])
  } else {
    error.value = 'Failed to delete content'
  }
}

// ── Folder modal ──────────────────────────────────────────────────────────
function openFolderModal(mode, folder = null) {
  folderModal.value = {
    open: true,
    mode,
    folder,
    name: folder?.name || '',
    parent: currentFolderId.value !== null && currentFolderId.value !== 'uncategorized' ? currentFolderId.value : null,
  }
  nextTick(() => folderNameInput.value?.focus())
}

async function submitFolderModal() {
  const m = folderModal.value
  try {
    if (m.mode === 'create') {
      const body = JSON.stringify({ name: m.name, parent_id: m.parent })
      const res = await apiFetch(route('admin.channels.my-channel.folders.store', channelSlug.value), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body,
      })
      const json = await res.json().catch(() => ({}))
      if (!res.ok) { error.value = json?.message || 'Failed to create folder'; return }
      if (m.parent) expanded.value = new Set([...expanded.value, m.parent])
    } else if (m.mode === 'rename') {
      const res = await apiFetch(route('admin.channels.my-channel.folders.update', [channelSlug.value, m.folder.id]), {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: m.name }),
      })
      const json = await res.json().catch(() => ({}))
      if (!res.ok) { error.value = json?.message || 'Failed to rename folder'; return }
    } else if (m.mode === 'delete') {
      const res = await apiFetch(route('admin.channels.my-channel.folders.destroy', [channelSlug.value, m.folder.id]), {
        method: 'DELETE',
      })
      const json = await res.json().catch(() => ({}))
      if (!res.ok) { error.value = json?.message || 'Failed to delete folder'; return }
      if (currentFolderId.value === m.folder.id) currentFolderId.value = null
    }
    folderModal.value.open = false
    await Promise.all([fetchFolders(), fetchContent(content.value?.current_page || 1)])
  } catch (e) {
    error.value = 'Something went wrong'
  }
}

// ── Formatters ────────────────────────────────────────────────────────────
const qualityColor = (q) => ({
  '4k': 'text-purple-400', fhd: 'text-blue-400', hd: 'text-green-400', sd: 'text-yellow-400', low: 'text-gray-400',
}[q] || 'text-gray-400')

function formatDuration(s) {
  if (!s) return '0s'
  if (s < 60) return `${s}s`
  return `${Math.floor(s / 60)}m ${s % 60}s`
}

function formatSize(bytes) {
  if (!bytes) return '0 B'
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
  if (bytes < 1073741824) return (bytes / 1048576).toFixed(1) + ' MB'
  return (bytes / 1073741824).toFixed(2) + ' GB'
}

onMounted(() => {
  fetchFolders()
  fetchContent()
})
</script>