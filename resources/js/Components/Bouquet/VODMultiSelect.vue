<template>
  <div class="space-y-3">
    <div class="flex items-center justify-between">
      <label class="block text-sm font-medium text-gray-300">VOD Content</label>
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
        placeholder="Search VOD content..."
        class="w-full pl-10 pr-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500"
      />
    </div>

    <!-- VOD List grouped by category -->
    <div class="space-y-3 max-h-64 overflow-y-auto bg-gray-800 rounded-lg p-3 border border-gray-700">
      <div
        v-for="category in filteredVodByCategory"
        :key="category.id || 'uncategorized'"
        class="space-y-2"
      >
        <div class="flex items-center justify-between mb-1">
          <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
            {{ category.name }}
          </h4>
          <button
            type="button"
            @click="selectCategory(category.items)"
            class="text-xs px-1.5 py-0.5 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded transition"
          >
            Select All
          </button>
        </div>
        <label
          v-for="item in category.items"
          :key="item.id"
          class="flex items-center gap-2 text-gray-300 text-sm p-1 hover:bg-gray-700 rounded cursor-pointer"
        >
          <input
            type="checkbox"
            :value="item.id"
            v-model="selectedItems"
            class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600 focus:ring-indigo-500"
          />
          <span class="flex-1">{{ item.title }}</span>
          <span v-if="item.type" class="text-xs text-gray-500">
            {{ item.type }}
          </span>
        </label>
      </div>

      <p v-if="filteredVod.length === 0" class="text-sm text-gray-500 py-4 text-center">
        No VOD content found.
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
  vodContent: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue'])

const searchQuery = ref('')
const selectedItems = ref([])
let isInternalChange = false

// Initialize from props
selectedItems.value = [...props.modelValue]

// Sync with modelValue when it changes externally (but not from our own emit)
watch(
  () => props.modelValue,
  (newVal) => {
    if (isInternalChange) {
      isInternalChange = false
      return
    }
    selectedItems.value = [...newVal]
  }
)

// Emit changes when selection changes (but not when synced from parent)
watch(selectedItems, (newVal) => {
  if (isInternalChange) {
    return
  }
  isInternalChange = true
  emit('update:modelValue', [...newVal])
})

const selectedCount = computed(() => selectedItems.value.length)

const selectAll = () => {
  isInternalChange = true
  selectedItems.value = props.vodContent.map((c) => c.id)
  emit('update:modelValue', [...selectedItems.value])
}

const clearAll = () => {
  isInternalChange = true
  selectedItems.value = []
  emit('update:modelValue', [])
}

const selectCategory = (categoryItems) => {
  isInternalChange = true
  const currentIds = new Set(selectedItems.value)
  categoryItems.forEach((item) => currentIds.add(item.id))
  selectedItems.value = Array.from(currentIds)
  emit('update:modelValue', [...selectedItems.value])
}

const filteredVod = computed(() => {
  if (!searchQuery.value) return props.vodContent
  const query = searchQuery.value.toLowerCase()
  return props.vodContent.filter(
    (c) =>
      c.title.toLowerCase().includes(query) ||
      (c.description && c.description.toLowerCase().includes(query))
  )
})

const filteredVodByCategory = computed(() => {
  const items = filteredVod.value
  const byCategory = {}
  const uncategorized = []

  items.forEach((item) => {
    if (item.categories && item.categories.length > 0) {
      item.categories.forEach((cat) => {
        if (!byCategory[cat.id]) {
          byCategory[cat.id] = { id: cat.id, name: cat.name, items: [] }
        }
        byCategory[cat.id].items.push(item)
      })
    } else {
      uncategorized.push(item)
    }
  })

  const result = Object.values(byCategory)
  if (uncategorized.length > 0) {
    result.push({ id: 'uncategorized', name: 'Uncategorized', items: uncategorized })
  }

  return result
})
</script>
