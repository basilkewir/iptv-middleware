<template>
  <AdminLayout>
    <div class="p-6 max-w-7xl mx-auto space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-white">Stream Push</h1>
          <p class="text-gray-400 mt-1">Push channels to external RTMP or SRT servers in real time</p>
        </div>
        <div class="flex items-center gap-3">
          <button
            v-if="activePushes.length > 0"
            @click="stopAll"
            :disabled="stoppingAll"
            class="px-4 py-2 bg-red-600 hover:bg-red-500 disabled:opacity-50 text-white rounded-lg transition flex items-center gap-2 text-sm"
          >
            <Loader2 v-if="stoppingAll" class="w-4 h-4 animate-spin" />
            <Square v-else class="w-4 h-4" />
            Stop All ({{ activePushes.length }})
          </button>
          <button
            @click="showDestModal = true"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition flex items-center gap-2 text-sm"
          >
            <Plus class="w-4 h-4" /> Add Destination
          </button>
        </div>
      </div>

      <!-- Active Pushes Overview -->
      <div v-if="activePushes.length > 0" class="bg-gray-800 rounded-xl border border-green-500/30 p-6">
        <div class="flex items-center gap-2 mb-4">
          <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse" />
          <h2 class="text-lg font-semibold text-green-400">
            Active Pushes ({{ activePushes.length }})
          </h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
          <div
            v-for="push in activePushes"
            :key="`${push.channel_id}-${push.destination}`"
            class="bg-gray-700/50 rounded-lg p-4 border border-gray-600"
          >
            <div class="flex items-center justify-between mb-2">
              <span class="text-white font-medium text-sm truncate">{{ push.channel }}</span>
              <span
                class="px-2 py-0.5 text-xs rounded-full"
                :class="push.protocol === 'srt' ? 'bg-purple-500/20 text-purple-400' : 'bg-blue-500/20 text-blue-400'"
              >
                {{ push.protocol.toUpperCase() }}
              </span>
            </div>
            <p class="text-gray-400 text-xs mb-3">→ {{ push.destination }}</p>
            <div class="flex items-center justify-between">
              <span class="text-gray-500 text-xs">{{ formatTime(push.started_at) }}</span>
              <button
                @click="stopPush(push.channel_id, findDestinationId(push.destination))"
                class="px-3 py-1 bg-red-600/20 hover:bg-red-600/40 text-red-400 text-xs rounded transition"
              >
                Stop
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Destinations -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-white">Push Destinations</h2>
          <span class="text-gray-500 text-sm">{{ destinations.length }} configured</span>
        </div>

        <div v-if="destinations.length === 0" class="text-center py-8">
          <Radio class="w-10 h-10 text-gray-600 mx-auto mb-3" />
          <p class="text-gray-400 mb-2">No push destinations configured</p>
          <p class="text-gray-500 text-sm">Add an RTMP or SRT destination to start pushing streams.</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-700 text-left">
                <th class="pb-3 pr-4 text-gray-400 font-medium">Name</th>
                <th class="pb-3 pr-4 text-gray-400 font-medium">Protocol</th>
                <th class="pb-3 pr-4 text-gray-400 font-medium">URL</th>
                <th class="pb-3 pr-4 text-gray-400 font-medium">Status</th>
                <th class="pb-3 text-gray-400 font-medium text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-700/50">
              <tr v-for="dest in destinations" :key="dest.id" class="hover:bg-gray-700/30 transition">
                <td class="py-3 pr-4">
                  <span class="text-white font-medium">{{ dest.name }}</span>
                </td>
                <td class="py-3 pr-4">
                  <span
                    class="px-2 py-0.5 text-xs rounded-full"
                    :class="dest.protocol === 'srt' ? 'bg-purple-500/20 text-purple-400' : 'bg-blue-500/20 text-blue-400'"
                  >
                    {{ dest.protocol.toUpperCase() }}
                  </span>
                </td>
                <td class="py-3 pr-4">
                  <span class="text-gray-400 font-mono text-xs truncate block max-w-xs">{{ dest.url }}</span>
                </td>
                <td class="py-3 pr-4">
                  <span
                    class="px-2 py-0.5 text-xs rounded-full"
                    :class="dest.is_active ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'"
                  >
                    {{ dest.is_active ? 'Active' : 'Disabled' }}
                  </span>
                </td>
                <td class="py-3 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <button
                      @click="editDestination(dest)"
                      class="px-3 py-1 bg-gray-600 hover:bg-gray-500 text-white text-xs rounded transition"
                    >
                      Edit
                    </button>
                    <button
                      @click="deleteDestination(dest)"
                      class="px-3 py-1 bg-red-600/20 hover:bg-red-600/40 text-red-400 text-xs rounded transition"
                    >
                      Delete
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Channel Push Matrix -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-white">Push Channels</h2>
          <div class="flex items-center gap-3">
            <input
              v-model="search"
              type="text"
              placeholder="Search channels…"
              class="px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm placeholder-gray-500 focus:outline-none focus:border-indigo-500 w-56"
            />
          </div>
        </div>

        <div v-if="destinations.length === 0" class="text-center py-8 text-gray-500 text-sm">
          Add a push destination above first.
        </div>

        <div v-else-if="filteredChannels.length === 0" class="text-center py-8 text-gray-500 text-sm">
          No active channels found.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-700 text-left">
                <th class="pb-3 pr-4 w-8 text-gray-400 font-medium">#</th>
                <th class="pb-3 pr-4 text-gray-400 font-medium">Channel</th>
                <th class="pb-3 pr-4 text-gray-400 font-medium">Source</th>
                <th
                  v-for="dest in destinations"
                  :key="dest.id"
                  class="pb-3 px-2 text-gray-400 font-medium text-center min-w-[100px]"
                >
                  <div class="flex flex-col items-center">
                    <span>{{ dest.name }}</span>
                    <span class="text-xs text-gray-500">{{ dest.protocol.toUpperCase() }}</span>
                  </div>
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-700/50">
              <tr v-for="ch in filteredChannels" :key="ch.id" class="hover:bg-gray-700/30 transition">
                <td class="py-3 pr-4 text-gray-500 font-mono">{{ ch.channel_number }}</td>
                <td class="py-3 pr-4">
                  <span class="text-white font-medium">{{ ch.name }}</span>
                </td>
                <td class="py-3 pr-4">
                  <span class="text-gray-400 text-xs font-mono truncate block max-w-[200px]">{{ ch.stream_type?.toUpperCase() || '—' }}</span>
                </td>
                <td
                  v-for="dest in destinations"
                  :key="dest.id"
                  class="py-3 px-2 text-center"
                >
                  <button
                    v-if="isPushing(ch.id, dest.id)"
                    @click="stopPush(ch.id, dest.id)"
                    :disabled="loadingPush[`${ch.id}-${dest.id}`]"
                    class="px-3 py-1 bg-red-600/20 hover:bg-red-600/40 text-red-400 text-xs rounded transition inline-flex items-center gap-1"
                  >
                    <Loader2 v-if="loadingPush[`${ch.id}-${dest.id}`]" class="w-3 h-3 animate-spin" />
                    <Square v-else class="w-3 h-3" />
                    Stop
                  </button>
                  <button
                  v-else
                    @click="startPush(ch.id, dest.id)"
                    :disabled="loadingPush[`${ch.id}-${dest.id}`] || !ch.active_stream_url"
                    class="px-3 py-1 bg-green-600/20 hover:bg-green-600/40 text-green-400 text-xs rounded transition inline-flex items-center gap-1 disabled:opacity-40"
                  >
                    <Loader2 v-if="loadingPush[`${ch.id}-${dest.id}`]" class="w-3 h-3 animate-spin" />
                    <Play v-else class="w-3 h-3" />
                    Push
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Destination Modal -->
      <Teleport to="body">
        <div
          v-if="showDestModal"
          class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
          @click.self="closeDestModal"
        >
          <div class="bg-gray-800 rounded-xl border border-gray-700 w-full max-w-lg p-6 shadow-2xl">
            <div class="flex items-center justify-between mb-6">
              <h3 class="text-lg font-semibold text-white">
                {{ editingDest ? 'Edit Destination' : 'Add Destination' }}
              </h3>
              <button @click="closeDestModal" class="text-gray-400 hover:text-white">
                <X class="w-5 h-5" />
              </button>
            </div>

            <form @submit.prevent="saveDestination" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Name <span class="text-red-400">*</span></label>
                <input
                  v-model="destForm.name"
                  type="text"
                  placeholder="e.g. CDN Primary, Backup Server"
                  class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500"
                  required
                />
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-1">Protocol <span class="text-red-400">*</span></label>
                  <select
                    v-model="destForm.protocol"
                    class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500"
                    required
                  >
                    <option value="rtmp">RTMP</option>
                    <option value="srt">SRT</option>
                  </select>
                </div>
                <div class="flex items-end">
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input
                      v-model="destForm.is_active"
                      type="checkbox"
                      class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600"
                    />
                    <span class="text-sm text-gray-300">Enabled</span>
                  </label>
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">
                  Server URL <span class="text-red-400">*</span>
                </label>
                <input
                  v-model="destForm.url"
                  type="text"
                  :placeholder="destForm.protocol === 'srt' ? 'srt://your-server.com:9000' : 'rtmp://your-server.com/live'"
                  class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500"
                  required
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Stream Key</label>
                <input
                  v-model="destForm.stream_key"
                  type="text"
                  placeholder="Appended to URL as /stream_key"
                  class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Notes</label>
                <textarea
                  v-model="destForm.notes"
                  rows="2"
                  placeholder="Optional notes about this destination"
                  class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500"
                />
              </div>

              <div class="flex justify-end gap-3 pt-2">
                <button
                  type="button"
                  @click="closeDestModal"
                  class="px-4 py-2 bg-gray-600 hover:bg-gray-500 text-white rounded-lg transition text-sm"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  :disabled="savingDest"
                  class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white rounded-lg transition flex items-center gap-2 text-sm"
                >
                  <Loader2 v-if="savingDest" class="w-4 h-4 animate-spin" />
                  {{ editingDest ? 'Update' : 'Create' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Teleport>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeft, Radio, Plus, Loader2, Square, Play, X } from 'lucide-vue-next'

const props = defineProps({
  channels:      { type: Array, default: () => [] },
  destinations:  { type: Array, default: () => [] },
  activePushes:  { type: Array, default: () => [] },
  pushMap:       { type: Object, default: () => ({}) },
})

const search = ref('')
const showDestModal = ref(false)
const editingDest = ref(null)
const savingDest = ref(false)
const stoppingAll = ref(false)
const loadingPush = reactive({})

const destForm = reactive({
  name: '',
  protocol: 'rtmp',
  url: '',
  stream_key: '',
  is_active: true,
  notes: '',
})

const filteredChannels = computed(() => {
  if (!search.value.trim()) return props.channels
  const q = search.value.toLowerCase()
  return props.channels.filter(
    ch => ch.name.toLowerCase().includes(q) || String(ch.channel_number).includes(q)
  )
})

const isPushing = (channelId, destinationId) => {
  return props.pushMap[channelId] && props.pushMap[channelId][destinationId]
}

const findDestinationId = (name) => {
  const dest = props.destinations.find(d => d.name === name)
  return dest ? dest.id : null
}

const csrfToken = () => decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || '')

const api = async (url, method = 'GET', body = null) => {
  const opts = {
    method,
    headers: {
      'Content-Type': 'application/json',
      'X-XSRF-TOKEN': csrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    },
  }
  if (body) opts.body = JSON.stringify(body)
  const res = await fetch(url, opts)
  const data = await res.json()
  if (!res.ok) throw new Error(data.message || 'Request failed.')
  return data
}

const startPush = async (channelId, destinationId) => {
  const key = `${channelId}-${destinationId}`
  loadingPush[key] = true
  try {
    await api(route('admin.channels.push.start'), 'POST', {
      channel_id: channelId,
      destination_id: destinationId,
    })
    location.reload()
  } catch (e) {
    alert(e.message)
  } finally {
    loadingPush[key] = false
  }
}

const stopPush = async (channelId, destinationId) => {
  const key = `${channelId}-${destinationId}`
  loadingPush[key] = true
  try {
    await api(route('admin.channels.push.stop'), 'POST', {
      channel_id: channelId,
      destination_id: destinationId,
    })
    location.reload()
  } catch (e) {
    alert(e.message)
  } finally {
    loadingPush[key] = false
  }
}

const stopAll = async () => {
  stoppingAll.value = true
  try {
    await api(route('admin.channels.push.stop-all'), 'POST')
    location.reload()
  } catch (e) {
    alert(e.message)
  } finally {
    stoppingAll.value = false
  }
}

const editDestination = (dest) => {
  editingDest.value = dest
  destForm.name = dest.name
  destForm.protocol = dest.protocol
  destForm.url = dest.url
  destForm.stream_key = dest.stream_key || ''
  destForm.is_active = dest.is_active
  destForm.notes = dest.notes || ''
  showDestModal.value = true
}

const closeDestModal = () => {
  showDestModal.value = false
  editingDest.value = null
  destForm.name = ''
  destForm.protocol = 'rtmp'
  destForm.url = ''
  destForm.stream_key = ''
  destForm.is_active = true
  destForm.notes = ''
}

const saveDestination = async () => {
  savingDest.value = true
  try {
    const payload = {
      name: destForm.name,
      protocol: destForm.protocol,
      url: destForm.url,
      stream_key: destForm.stream_key || null,
      is_active: destForm.is_active,
      notes: destForm.notes || null,
    }
    if (editingDest.value) {
      await api(route('admin.channels.push.destinations.update', editingDest.value.id), 'PUT', payload)
    } else {
      await api(route('admin.channels.push.destinations.store'), 'POST', payload)
    }
    location.reload()
  } catch (e) {
    alert(e.message)
  } finally {
    savingDest.value = false
  }
}

const deleteDestination = async (dest) => {
  if (!confirm(`Delete destination "${dest.name}"? Any active pushes to this destination will be stopped.`)) return
  try {
    await api(route('admin.channels.push.destinations.destroy', dest.id), 'DELETE')
    location.reload()
  } catch (e) {
    alert(e.message)
  }
}

const formatTime = (iso) => {
  if (!iso) return ''
  const d = new Date(iso)
  return d.toLocaleTimeString()
}
</script>
