<template>
  <AdminLayout>
    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-white">Channel Order</h1>
          <p class="text-gray-400 mt-1">Drag and drop to reorder channels in the player. Numbers are assigned top to bottom.</p>
        </div>
        <button @click="saveOrder" :disabled="saving || !hasChanges"
          class="px-5 py-2.5 rounded-lg font-medium transition flex items-center gap-2"
          :class="hasChanges ? 'bg-indigo-600 hover:bg-indigo-500 text-white' : 'bg-gray-700 text-gray-500 cursor-not-allowed'">
          <Save class="w-4 h-4" />
          {{ saving ? 'Saving...' : 'Save Order' }}
        </button>
      </div>

      <div v-if="success" class="p-3 bg-green-900/30 border border-green-700/50 rounded-lg text-green-400 text-sm flex items-center justify-between">
        {{ success }}
        <button @click="success = ''" class="text-green-500 hover:text-green-300">✕</button>
      </div>

      <div v-if="error" class="p-3 bg-red-900/30 border border-red-700/50 rounded-lg text-red-400 text-sm flex items-center justify-between">
        {{ error }}
        <button @click="error = ''" class="text-red-500 hover:text-red-300">✕</button>
      </div>

      <div v-if="loading" class="text-center py-12 text-gray-500">
        <Loader2 class="w-8 h-8 mx-auto mb-3 animate-spin" />
        <p>Loading channels...</p>
      </div>

      <div v-else class="space-y-1.5">
        <div v-for="(item, index) in ordered" :key="item.type + '-' + item.id"
          draggable="true"
          @dragstart="dragStart(index)"
          @dragover.prevent="dragOver(index)"
          @drop="drop"
          @dragend="dragEnd"
          class="flex items-center gap-4 p-4 bg-gray-800 rounded-xl border border-gray-700 cursor-grab active:cursor-grabbing hover:border-gray-600 transition select-none"
          :class="{
            'opacity-40 scale-[0.98]': dragIndex === index,
            'border-indigo-500/50 bg-indigo-900/10': overIndex === index && dragIndex !== null && dragIndex !== index
          }">
          <span class="text-lg font-bold text-gray-500 w-8 text-center shrink-0 tabular-nums">{{ index + 1 }}</span>
          <GripVertical class="w-5 h-5 text-gray-500 shrink-0" />
          <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 overflow-hidden"
            :class="item.type === 'admin_channel' ? 'bg-purple-900/50 border border-purple-700/50' : 'bg-gray-700'">
            <img v-if="item.logo_url" :src="item.logo_url" class="w-full h-full object-cover" />
            <Tv v-else class="w-5 h-5 text-gray-400" />
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-white font-medium truncate">{{ item.name }}</div>
            <div class="text-xs text-gray-400 mt-0.5 flex items-center gap-2">
              <span v-if="item.type === 'admin_channel'" class="px-1.5 py-0.5 bg-purple-900/50 text-purple-400 rounded text-[10px] font-medium uppercase">My Channel</span>
              <span v-else class="px-1.5 py-0.5 bg-gray-700 text-gray-400 rounded text-[10px] font-medium uppercase">IPTV</span>
              <span class="truncate max-w-[200px]">{{ item.stream_url || 'No URL' }}</span>
            </div>
          </div>
          <div class="text-xs text-gray-500 shrink-0">ch# {{ index + 1 }}</div>
        </div>
      </div>

      <div v-if="!loading && ordered.length === 0" class="text-center py-12 text-gray-500">
        <Tv class="w-12 h-12 mx-auto mb-3 opacity-50" />
        <p>No channels found.</p>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { GripVertical, Save, Loader2, Tv } from 'lucide-vue-next'

const loading = ref(true)
const saving = ref(false)
const ordered = ref([])
const original = ref([])
const error = ref('')
const success = ref('')

const dragIndex = ref(null)
const overIndex = ref(null)

const hasChanges = ref(false)

const checkChanges = () => {
  if (ordered.value.length !== original.value.length) {
    hasChanges.value = true
    return
  }
  hasChanges.value = ordered.value.some((item, i) =>
    item.id !== original.value[i]?.id || item.type !== original.value[i]?.type
  )
}

onMounted(async () => {
  try {
    const res = await fetch('/admin/channels/all/list')
    const data = await res.json()
    ordered.value = [...data]
    original.value = data.map(i => ({ ...i }))
    checkChanges()
  } catch (e) {
    error.value = 'Failed to load channels.'
  } finally {
    loading.value = false
  }
})

function dragStart(index) {
  dragIndex.value = index
}

function dragOver(index) {
  overIndex.value = index
}

function drop(index) {
  if (dragIndex.value === null || dragIndex.value === index) return
  const item = ordered.value.splice(dragIndex.value, 1)[0]
  ordered.value.splice(index, 0, item)
  dragIndex.value = null
  overIndex.value = null
  checkChanges()
}

function dragEnd() {
  dragIndex.value = null
  overIndex.value = null
}

async function saveOrder() {
  saving.value = true
  error.value = ''
  success.value = ''

  try {
    const items = ordered.value.map(item => ({
      id: item.id,
      type: item.type,
    }))

    const res = await fetch('/admin/channels/reorder', {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'Accept': 'application/json',
      },
      body: JSON.stringify({ items }),
    })

    if (!res.ok) {
      const body = await res.json().catch(() => ({}))
      throw new Error(body.message || 'Failed to save order.')
    }

    original.value = ordered.value.map(i => ({ ...i }))
    checkChanges()
    success.value = 'Channel order saved successfully.'
    setTimeout(() => { success.value = '' }, 4000)
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}
</script>
