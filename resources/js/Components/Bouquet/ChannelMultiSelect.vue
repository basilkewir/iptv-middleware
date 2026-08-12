<template>
  <div class="space-y-3">
    <div class="flex items-center justify-between">
      <label class="block text-sm font-medium text-gray-300">Channels</label>
      <div class="flex items-center gap-3">
        <span class="text-sm text-gray-400">{{ selectedCount }} selected</span>
        <button
          type="button"
          @click="selectAll"
          class="text-xs px-2 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded transition"
        >
          Select All
        </button>
        <button
          type="button"
          @click="clearAll"
          class="text-xs px-2 py-1 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded transition"
        >
          Clear All
        </button>
      </div>
    </div>

    <!-- Search -->
    <div class="relative">
      <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
      <input
        v-model="searchQuery"
        type="text"
        placeholder="Search channels..."
        class="w-full pl-10 pr-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500"
      />
    </div>

    <!-- Channel List grouped by category -->
    <div class="space-y-3 max-h-64 overflow-y-auto bg-gray-800 rounded-lg p-3 border border-gray-700">
      <div
        v-for="category in filteredChannelsByCategory"
        :key="category.id || 'uncategorized'"
        class="space-y-2"
      >
        <div class="flex items-center justify-between mb-1">
          <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
            {{ category.name }}
          </h4>
          <button
            type="button"
            @click="selectCategory(category.channels)"
            class="text-xs px-1.5 py-0.5 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded transition"
          >
            Select All
          </button>
        </div>
        <label
          v-for="channel in category.channels"
          :key="channel.id"
          class="flex items-center gap-2 text-gray-300 text-sm p-1 hover:bg-gray-700 rounded cursor-pointer"
        >
          <input
            type="checkbox"
            :value="channel.id"
            v-model="selectedChannels"
            class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600 focus:ring-indigo-500"
          />
          <span class="flex-1">{{ channel.name }}</span>
          <span v-if="channel.stream_type" class="text-xs text-gray-500">
            {{ channel.stream_type }}
          </span>
        </label>
      </div>

      <p v-if="filteredChannels.length === 0" class="text-sm text-gray-500 py-4 text-center">
        No channels found.
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Search } from 'lucide-vue-next'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  channels: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue'])

const searchQuery = ref('')
const selectedChannels = ref([])
let isInternalChange = false

// Initialize from props
selectedChannels.value = [...props.modelValue]

// Sync with modelValue when it changes externally (but not from our own emit)
watch(
  () => props.modelValue,
  (newVal) => {
    if (isInternalChange) {
      isInternalChange = false
      return
    }
    selectedChannels.value = [...newVal]
  }
)

// Emit changes when selection changes (but not when synced from parent)
watch(selectedChannels, (newVal) => {
  if (isInternalChange) {
    return
  }
  isInternalChange = true
  emit('update:modelValue', [...newVal])
})

const selectedCount = computed(() => selectedChannels.value.length)

const selectAll = () => {
  isInternalChange = true
  selectedChannels.value = props.channels.map((c) => c.id)
  emit('update:modelValue', [...selectedChannels.value])
}

const clearAll = () => {
  isInternalChange = true
  selectedChannels.value = []
  emit('update:modelValue', [])
}

const selectCategory = (categoryChannels) => {
  isInternalChange = true
  const currentIds = new Set(selectedChannels.value)
  categoryChannels.forEach((ch) => currentIds.add(ch.id))
  selectedChannels.value = Array.from(currentIds)
  emit('update:modelValue', [...selectedChannels.value])
}

const filteredChannels = computed(() => {
  if (!searchQuery.value) return props.channels
  const query = searchQuery.value.toLowerCase()
  return props.channels.filter(
    (c) =>
      c.name.toLowerCase().includes(query) ||
      (c.stream_url && c.stream_url.toLowerCase().includes(query))
  )
})

const filteredChannelsByCategory = computed(() => {
  const channels = filteredChannels.value
  const byCategory = {}
  const uncategorized = []

  channels.forEach((channel) => {
    if (channel.categories && channel.categories.length > 0) {
      channel.categories.forEach((cat) => {
        if (!byCategory[cat.id]) {
          byCategory[cat.id] = { id: cat.id, name: cat.name, channels: [] }
        }
        byCategory[cat.id].channels.push(channel)
      })
    } else {
      uncategorized.push(channel)
    }
  })

  const result = Object.values(byCategory)
  if (uncategorized.length > 0) {
    result.push({ id: 'uncategorized', name: 'Uncategorized', channels: uncategorized })
  }

  return result
})
</script>
