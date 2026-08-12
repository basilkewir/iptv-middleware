<template>
  <AdminLayout>
    <div class="p-6 max-w-6xl mx-auto">
      <div class="mb-6">
        <Link :href="route('admin.bouquets.index')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
          <ArrowLeft class="w-4 h-4" /> Back to Bouquets
        </Link>
        <div class="flex items-center gap-3">
          <h1 class="text-2xl font-bold text-white">Edit Bouquet: {{ bouquet?.name }}</h1>
          <span class="px-2 py-1 text-xs rounded-full"
            :class="bouquet?.is_active ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'">
            {{ bouquet?.is_active ? 'Active' : 'Inactive' }}
          </span>
        </div>
      </div>

      <div class="bg-gray-800 rounded-xl p-4 border border-gray-700 mb-4">
        <div class="relative">
          <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
          <input v-model="channelSearch" type="text" placeholder="Search channels in bouquet..."
            class="w-full pl-10 pr-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500" />
        </div>
      </div>

      <div class="bg-gray-800 rounded-xl border border-gray-700 p-4 mb-4">
        <DraggableChannelList
          :channels="filteredChannels"
          :selected-channel-ids="selectedChannels"
          @update:selected-channel-ids="selectedChannels = $event"
          @reorder="handleReorder"
          @edit="handleEditChannel"
          @remove="handleRemoveChannel"
          @move-up="handleMoveUp"
        />
      </div>

      <div v-if="selectedChannels.length > 0" class="mb-4 flex items-center gap-3">
        <span class="text-sm text-gray-300">{{ selectedChannels.length }} channel(s) selected</span>
        <button @click="deleteSelected"
          class="px-3 py-1 bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white rounded-lg text-sm transition">
          Delete Selected
        </button>
      </div>

      <div class="bg-gray-800 rounded-xl p-4 border border-gray-700 mb-4">
        <h3 class="text-sm font-semibold text-gray-300 mb-3">Quick Actions</h3>
        <div class="flex flex-wrap gap-2">
          <button @click="showAddModal = true"
            class="px-3 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg text-sm transition flex items-center gap-2">
            <Plus class="w-4 h-4" /> Add Channels
          </button>
          <button @click="reorderMode = !reorderMode"
            class="px-3 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg text-sm transition flex items-center gap-2"
            :class="reorderMode ? 'bg-indigo-600/20 text-indigo-400' : ''">
            <GripVertical class="w-4 h-4" /> Reorder
          </button>
          <button @click="showCloneModal = true"
            class="px-3 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg text-sm transition flex items-center gap-2">
            <Copy class="w-4 h-4" /> Clone
          </button>
          <Dropdown align="right">
            <template #trigger>
              <button class="px-3 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg text-sm transition flex items-center gap-2">
                <Download class="w-4 h-4" /> Export
              </button>
            </template>
            <template #content>
              <a :href="route('admin.bouquets.export', { bouquet: bouquet.id, format: 'm3u' })"
                class="block px-3 py-2 text-sm text-gray-300 hover:bg-gray-700">M3U Playlist</a>
              <a :href="route('admin.bouquets.export', { bouquet: bouquet.id, format: 'csv' })"
                class="block px-3 py-2 text-sm text-gray-300 hover:bg-gray-700">CSV</a>
              <a :href="route('admin.bouquets.export', { bouquet: bouquet.id, format: 'json' })"
                class="block px-3 py-2 text-sm text-gray-300 hover:bg-gray-700">JSON</a>
            </template>
          </Dropdown>
          <button @click="showImportModal = true"
            class="px-3 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg text-sm transition flex items-center gap-2">
            <Upload class="w-4 h-4" /> Import
          </button>
          <button @click="deleteAllChannels"
            class="px-3 py-2 bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white rounded-lg text-sm transition flex items-center gap-2">
            <Trash2 class="w-4 h-4" /> Delete All
          </button>
        </div>
      </div>

      <div class="flex justify-end gap-3">
        <Link :href="route('admin.bouquets.index')"
          class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">
          Cancel
        </Link>
        <button @click="saveChanges"
          class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition flex items-center gap-2">
          <Check class="w-4 h-4" /> Save Changes
        </button>
      </div>

      <Modal v-model:show="showAddModal" title="Add Channels" maxWidth="3xl">
        <template #default>
          <ChannelMultiSelect v-model="newChannelIds" :channels="allChannels" />
        </template>
        <template #footer>
          <button @click="showAddModal = false"
            class="px-3 py-1 bg-gray-700 hover:bg-gray-600 text-white rounded-lg text-sm">
            Cancel
          </button>
          <button @click="addChannels"
            class="px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm">
            Add
          </button>
        </template>
      </Modal>

      <Modal v-model:show="showCloneModal" title="Clone Bouquet" maxWidth="md">
        <template #default>
          <div class="space-y-3">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">New Bouquet Name</label>
              <input v-model="cloneName" type="text" placeholder="Enter name..."
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
          </div>
        </template>
        <template #footer>
          <button @click="showCloneModal = false"
            class="px-3 py-1 bg-gray-700 hover:bg-gray-600 text-white rounded-lg text-sm">
            Cancel
          </button>
          <button @click="cloneBouquet"
            class="px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm">
            Clone
          </button>
        </template>
      </Modal>

      <Modal v-model:show="showImportModal" title="Import Channels" maxWidth="md">
        <template #default>
          <div class="space-y-3">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Import File</label>
              <input type="file" ref="importFile" accept=".m3u,.csv,.json,.txt"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500 file:mr-4 file:py-1 file:px-4 file:rounded-lg file:border-0 file:text-sm file:bg-indigo-600 file:text-white hover:file:bg-indigo-500" />
              <p class="text-xs text-gray-500 mt-1">Supported: M3U, CSV, JSON (max 10MB)</p>
            </div>
          </div>
        </template>
        <template #footer>
          <button @click="showImportModal = false"
            class="px-3 py-1 bg-gray-700 hover:bg-gray-600 text-white rounded-lg text-sm">
            Cancel
          </button>
          <button @click="importChannels"
            class="px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm">
            Import
          </button>
        </template>
      </Modal>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import DraggableChannelList from '@/Components/Bouquet/DraggableChannelList.vue'
import ChannelMultiSelect from '@/Components/Bouquet/ChannelMultiSelect.vue'
import Modal from '@/Components/Common/Modal.vue'
import Dropdown from '@/Components/Common/Dropdown.vue'
import { ArrowLeft, Search, Plus, GripVertical, Copy, Download, Upload, Trash2, Check } from 'lucide-vue-next'

const props = defineProps({
  bouquet: { type: Object, required: true },
  allChannels: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
})

const channelSearch = ref('')
const selectedChannels = ref([])
const reorderMode = ref(false)
const showAddModal = ref(false)
const showCloneModal = ref(false)
const showImportModal = ref(false)
const newChannelIds = ref([])
const cloneName = ref('')
const importFile = ref(null)

const filteredChannels = computed(() => {
  const channels = props.bouquet?.channels || []
  if (!channelSearch.value) return channels
  const query = channelSearch.value.toLowerCase()
  return channels.filter(
    (c) =>
      c.name.toLowerCase().includes(query) ||
      (c.categories?.[0]?.name && c.categories[0].name.toLowerCase().includes(query))
  )
})

const handleReorder = (channelIds) => {
  router.put(route('admin.bouquets.channels.reorder', props.bouquet.id), {
    channel_ids: channelIds,
  })
}

const handleEditChannel = (channel) => {
  alert(`Edit channel: ${channel.name}`)
}

const handleRemoveChannel = (channel) => {
  if (confirm(`Remove "${channel.name}" from this bouquet?`)) {
    router.delete(route('admin.bouquets.channels.remove', [props.bouquet.id, channel.id]))
  }
}

const handleMoveUp = (channel, index) => {
  const channels = [...(props.bouquet?.channels || [])]
  const [moved] = channels.splice(index, 1)
  channels.splice(index - 1, 0, moved)
  handleReorder(channels.map((c) => c.id))
}

const deleteSelected = () => {
  if (confirm(`Remove ${selectedChannels.value.length} channel(s) from this bouquet?`)) {
    selectedChannels.value.forEach((id) => {
      router.delete(route('admin.bouquets.channels.remove', [props.bouquet.id, id]))
    })
    selectedChannels.value = []
  }
}

const addChannels = () => {
  if (newChannelIds.value.length === 0) {
    showAddModal.value = false
    return
  }
  router.post(route('admin.bouquets.channels.add', props.bouquet.id), {
    channel_ids: newChannelIds.value,
  })
  newChannelIds.value = []
  showAddModal.value = false
}

const cloneBouquet = () => {
  if (!cloneName.value.trim()) return
  router.post(route('admin.bouquets.clone', props.bouquet.id), {
    name: cloneName.value,
  })
  cloneName.value = ''
  showCloneModal.value = false
}

const importChannels = () => {
  if (!importFile.value?.files?.[0]) return
  const formData = new FormData()
  formData.append('file', importFile.value.files[0])
  router.post(route('admin.bouquets.import', props.bouquet.id), formData)
  showImportModal.value = false
}

const deleteAllChannels = () => {
  if (confirm('Remove ALL channels from this bouquet? This cannot be undone.')) {
    router.delete(route('admin.bouquets.channels.deleteAll', props.bouquet.id))
  }
}

const saveChanges = () => {
  router.visit(route('admin.bouquets.show', props.bouquet.id))
}
</script>
