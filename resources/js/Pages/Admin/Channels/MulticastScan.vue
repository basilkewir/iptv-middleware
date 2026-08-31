<template>
  <AdminLayout>
    <div class="p-6 max-w-5xl mx-auto space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-white">Multicast UDP Scanner</h1>
          <p class="text-gray-400 mt-1">Probe a UDP/RTP multicast stream and add programs as channels</p>
        </div>
        <Link :href="route('admin.channels.index')" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition flex items-center gap-2 text-sm">
          <ArrowLeft class="w-4 h-4" /> Back to Channels
        </Link>
      </div>

      <!-- Probe Form -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 space-y-4">
        <h2 class="text-lg font-semibold text-white">Stream Details</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-300 mb-1">Multicast URL <span class="text-red-400">*</span></label>
            <input
              v-model="url"
              type="text"
              placeholder="udp://@239.0.0.1:32768 or rtp://239.0.0.4:32768"
              class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500"
              @keyup.enter="probe"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-1">Local Interface IP</label>
            <input
              v-model="localAddr"
              type="text"
              placeholder="192.168.1.50 (optional)"
              class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500"
            />
          </div>
        </div>
        <div class="flex items-center gap-4">
          <div class="flex items-center gap-2">
            <label class="text-sm text-gray-300">Timeout (s):</label>
            <select v-model="timeout" class="px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm focus:outline-none focus:border-indigo-500">
              <option :value="10">10</option>
              <option :value="15">15</option>
              <option :value="20">20</option>
              <option :value="30">30</option>
            </select>
          </div>
          <button
            @click="probe"
            :disabled="scanning || !url.trim()"
            class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white rounded-lg transition flex items-center gap-2"
          >
            <Loader2 v-if="scanning" class="w-4 h-4 animate-spin" />
            <Radio v-else class="w-4 h-4" />
            {{ scanning ? 'Scanning…' : 'Scan Stream' }}
          </button>
        </div>
        <div v-if="error" class="p-3 bg-red-500/20 border border-red-500/30 rounded-lg text-red-400 text-sm">{{ error }}</div>
      </div>

      <!-- Results -->
      <div v-if="programs.length > 0" class="bg-gray-800 rounded-xl border border-gray-700 p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-white">
            Found {{ programs.length }} Program{{ programs.length !== 1 ? 's' : '' }}
          </h2>
          <div class="flex items-center gap-3">
            <button @click="selectAll" class="text-sm text-indigo-400 hover:text-indigo-300">Select All</button>
            <span class="text-gray-600">|</span>
            <button @click="selectNone" class="text-sm text-gray-400 hover:text-gray-300">None</button>
            <span class="text-gray-600">|</span>
            <button @click="selectNew" class="text-sm text-green-400 hover:text-green-300">New Only</button>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-700 text-left">
                <th class="pb-3 pr-4 w-8"><input type="checkbox" :checked="allSelected" @change="allSelected ? selectNone() : selectAll()" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600" /></th>
                <th class="pb-3 pr-4 text-gray-400 font-medium">Program ID</th>
                <th class="pb-3 pr-4 text-gray-400 font-medium">Channel Name</th>
                <th class="pb-3 pr-4 text-gray-400 font-medium">Provider</th>
                <th class="pb-3 pr-4 text-gray-400 font-medium">Video</th>
                <th class="pb-3 text-gray-400 font-medium">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-700/50">
              <tr v-for="prog in programs" :key="prog.program_id" class="hover:bg-gray-700/30 transition">
                <td class="py-3 pr-4">
                  <input
                    type="checkbox"
                    :value="prog.program_id"
                    v-model="selected"
                    :disabled="prog.already_exists"
                    class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 disabled:opacity-40"
                  />
                </td>
                <td class="py-3 pr-4 text-gray-300 font-mono">{{ prog.program_id }}</td>
                <td class="py-3 pr-4">
                  <input
                    v-if="selected.includes(prog.program_id)"
                    v-model="names[prog.program_id]"
                    type="text"
                    class="w-full px-2 py-1 bg-gray-700 border border-gray-600 rounded text-white text-sm focus:outline-none focus:border-indigo-500"
                    :placeholder="prog.name"
                  />
                  <span v-else class="text-gray-300">{{ prog.name }}</span>
                </td>
                <td class="py-3 pr-4 text-gray-400">{{ prog.provider || '—' }}</td>
                <td class="py-3 pr-4 text-gray-400">
                  <span v-if="prog.video_codec">{{ prog.video_codec.toUpperCase() }} {{ prog.video_height ? prog.video_height + 'p' : '' }}</span>
                  <span v-else class="text-gray-600">—</span>
                </td>
                <td class="py-3">
                  <span v-if="prog.already_exists" class="px-2 py-0.5 text-xs rounded-full bg-yellow-500/20 text-yellow-400">Already added</span>
                  <span v-else class="px-2 py-0.5 text-xs rounded-full bg-green-500/20 text-green-400">New</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Import Options -->
        <div class="border-t border-gray-700 pt-4 space-y-4">
          <div class="flex flex-wrap gap-4 items-end">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Assign to Category</label>
              <select v-model="categoryId" class="px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm focus:outline-none focus:border-indigo-500">
                <option value="">Uncategorized</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
              </select>
            </div>
            <div class="flex-1 flex justify-end">
              <button
                @click="importSelected"
                :disabled="importing || selected.length === 0"
                class="px-6 py-2 bg-green-600 hover:bg-green-500 disabled:opacity-50 text-white rounded-lg transition flex items-center gap-2"
              >
                <Loader2 v-if="importing" class="w-4 h-4 animate-spin" />
                <Plus v-else class="w-4 h-4" />
                {{ importing ? 'Importing…' : `Add ${selected.length} Channel${selected.length !== 1 ? 's' : ''}` }}
              </button>
            </div>
          </div>

          <div v-if="importResult" class="p-3 rounded-lg text-sm" :class="importResult.error ? 'bg-red-500/20 text-red-400' : 'bg-green-500/20 text-green-400'">
            <span v-if="importResult.error">{{ importResult.error }}</span>
            <span v-else>
              ✓ {{ importResult.imported }} channel{{ importResult.imported !== 1 ? 's' : '' }} added
              <span v-if="importResult.skipped > 0">, {{ importResult.skipped }} skipped (already exist)</span>.
              <Link :href="route('admin.channels.index')" class="underline ml-2">View Channels →</Link>
            </span>
          </div>
        </div>
      </div>

      <!-- Empty state after scan -->
      <div v-else-if="scanned && !scanning" class="bg-gray-800 rounded-xl border border-gray-700 p-12 text-center">
        <Radio class="w-12 h-12 text-gray-600 mx-auto mb-3" />
        <p class="text-gray-400">No programs detected in this stream.</p>
        <p class="text-gray-500 text-sm mt-1">Check that the multicast group is active and ffprobe is installed.</p>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeft, Radio, Plus, Loader2 } from 'lucide-vue-next'

const props = defineProps({
  categories: { type: Array, default: () => [] },
})

const url       = ref('')
const localAddr = ref('')
const timeout   = ref(15)
const scanning  = ref(false)
const importing = ref(false)
const scanned   = ref(false)
const error     = ref(null)
const programs  = ref([])
const selected  = ref([])
const names     = ref({})
const categoryId = ref('')
const importResult = ref(null)

const allSelected = computed(() =>
  programs.value.filter(p => !p.already_exists).every(p => selected.value.includes(p.program_id))
)

const selectAll  = () => { selected.value = programs.value.filter(p => !p.already_exists).map(p => p.program_id) }
const selectNone = () => { selected.value = [] }
const selectNew  = () => { selected.value = programs.value.filter(p => !p.already_exists).map(p => p.program_id) }

// Pre-fill names when programs load
watch(programs, (list) => {
  list.forEach(p => { if (!names.value[p.program_id]) names.value[p.program_id] = p.name })
})

const csrfToken = () => decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || '')

const probe = async () => {
  if (!url.value.trim()) return
  scanning.value = true
  error.value = null
  programs.value = []
  selected.value = []
  importResult.value = null
  scanned.value = false

  try {
    const res = await fetch(route('admin.channels.multicast-scan.probe'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ url: url.value, local_addr: localAddr.value || null, timeout: timeout.value }),
    })
    const data = await res.json()
    if (!res.ok) { error.value = data.error || data.message || 'Scan failed.'; return }
    programs.value = data.programs || []
    selectNew()
  } catch (e) {
    error.value = 'Network error: ' + e.message
  } finally {
    scanning.value = false
    scanned.value = true
  }
}

const importSelected = async () => {
  if (!selected.value.length) return
  importing.value = true
  importResult.value = null

  const payload = {
    url: url.value,
    local_addr: localAddr.value || null,
    category_id: categoryId.value || null,
    programs: selected.value.map(id => ({
      program_id: id,
      name: names.value[id] || ('Program ' + id),
    })),
  }

  try {
    const res = await fetch(route('admin.channels.multicast-scan.import'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(payload),
    })
    const data = await res.json()
    if (!res.ok) { importResult.value = { error: data.message || 'Import failed.' }; return }
    importResult.value = { imported: data.imported, skipped: data.skipped }
    // Refresh already_exists flags
    selected.value.forEach(id => {
      const p = programs.value.find(x => x.program_id === id)
      if (p) p.already_exists = true
    })
    selected.value = []
  } catch (e) {
    importResult.value = { error: 'Network error: ' + e.message }
  } finally {
    importing.value = false
  }
}
</script>
