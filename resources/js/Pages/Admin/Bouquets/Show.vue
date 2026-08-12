<template>
  <AdminLayout>
    <div class="p-6 max-w-6xl mx-auto">
      <div class="mb-6">
        <Link :href="route('admin.bouquets.index')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
          <ArrowLeft class="w-4 h-4" /> Back to Bouquets
        </Link>
        <div class="flex items-center gap-3">
          <h1 class="text-2xl font-bold text-white">Manage: {{ bouquet?.name }}</h1>
          <span class="px-2 py-1 text-xs rounded-full"
            :class="bouquet?.is_active ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'">
            {{ bouquet?.is_active ? 'Active' : 'Inactive' }}
          </span>
          <span class="px-2 py-1 text-xs rounded-full bg-gray-600 text-gray-300">
            {{ (bouquet?.channels || []).length }} channels
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
        <table class="w-full">
          <thead>
            <tr class="text-left text-gray-400 border-b border-gray-700">
              <th class="pb-3 font-medium w-12">#</th>
              <th class="pb-3 font-medium">Channel Name</th>
              <th class="pb-3 font-medium">Category</th>
              <th class="pb-3 font-medium text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-700">
            <tr v-if="filteredChannels.length === 0">
              <td colspan="4" class="py-12 text-center text-gray-500">No channels in this bouquet.</td>
            </tr>
            <tr v-for="(channel, index) in filteredChannels" :key="channel.id" class="hover:bg-gray-800/50">
              <td class="py-3 text-gray-500 text-sm">{{ index + 1 }}</td>
              <td class="py-3 text-white font-medium">{{ channel.name }}</td>
              <td class="py-3">
                <span v-if="channel.categories?.[0]?.name"
                  class="px-2 py-1 text-xs bg-gray-700 text-gray-300 rounded">
                  {{ channel.categories[0].name }}
                </span>
                <span v-else class="text-gray-500">-</span>
              </td>
              <td class="py-3 text-right">
                <div class="flex items-center justify-end gap-1">
                  <button @click="moveUp(channel, index)" :disabled="index === 0"
                    class="p-1.5 text-gray-400 hover:text-white hover:bg-gray-700 rounded disabled:opacity-30 disabled:cursor-not-allowed">
                    <ChevronUp class="w-4 h-4" />
                  </button>
                  <button @click="moveDown(channel, index)" :disabled="index === filteredChannels.length - 1"
                    class="p-1.5 text-gray-400 hover:text-white hover:bg-gray-700 rounded disabled:opacity-30 disabled:cursor-not-allowed">
                    <ChevronDown class="w-4 h-4" />
                  </button>
                  <button @click="editChannel(channel)"
                    class="p-1.5 text-gray-400 hover:text-white hover:bg-gray-700 rounded">
                    <Pencil class="w-4 h-4" />
                  </button>
                  <button @click="removeChannel(channel)"
                    class="p-1.5 text-gray-400 hover:text-red-400 hover:bg-gray-700 rounded">
                    <Trash2 class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
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

      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 mb-4">
        <h2 class="text-lg font-semibold text-white mb-4">Package Assignment</h2>
        <div v-if="(bouquet?.packages || []).length === 0" class="text-gray-500 text-sm py-4">
          This bouquet is not assigned to any packages.
        </div>
        <div v-else class="space-y-2">
          <div v-for="pkg in bouquet.packages" :key="pkg.id"
            class="flex items-center justify-between p-3 bg-gray-700 rounded-lg">
            <div class="flex items-center gap-3">
              <span class="text-white font-medium">{{ pkg.name }}</span>
              <span class="text-xs text-gray-400">${{ pkg.price }}</span>
            </div>
            <span class="px-2 py-1 text-xs rounded-full"
              :class="pkg.is_active ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'">
              {{ pkg.is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>
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
import ChannelMultiSelect from '@/Components/Bouquet/ChannelMultiSelect.vue'
import Modal from '@/Components/Common/Modal.vue'
import Dropdown from '@/Components/Common/Dropdown.vue'
import { ArrowLeft, Search, Plus, GripVertical, Copy, Download, Upload, Trash2, Check, ChevronUp, ChevronDown, Pencil } from 'lucide-vue-next'

const props = defineProps({
  bouquet: { type: Object, required: true },
  allChannels: { type: Array, default: () => [] },
})

const channelSearch = ref('')
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

const moveUp = (channel, index) => {
  if (index === 0) return
  const channels = [...(props.bouquet?.channels || [])]
  const [moved] = channels.splice(index, 1)
  channels.splice(index - 1, 0, moved)
  router.put(route('admin.bouquets.channels.reorder', props.bouquet.id), {
    channel_ids: channels.map((c) => c.id),
  })
}

const moveDown = (channel, index) => {
  const channels = [...(props.bouquet?.channels || [])]
  if (index >= channels.length - 1) return
  const [moved] = channels.splice(index, 1)
  channels.splice(index + 1, 0, moved)
  router.put(route('admin.bouquets.channels.reorder', props.bouquet.id), {
    channel_ids: channels.map((c) => c.id),
  })
}

const editChannel = (channel) => {
  alert(`Edit channel: ${channel.name}`)
}

const removeChannel = (channel) => {
  if (confirm(`Remove "${channel.name}" from this bouquet?`)) {
    router.delete(route('admin.bouquets.channels.remove', [props.bouquet.id, channel.id]))
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
  router.visit(route('admin.bouquets.index'))
}
</script>
