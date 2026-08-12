<template>
  <div class="space-y-3">
    <!-- Header with select all -->
    <div class="flex items-center gap-2 pb-2 border-b border-gray-700">
      <input
        type="checkbox"
        :checked="allSelected"
        :indeterminate="someSelected && !allSelected"
        @change="toggleSelectAll"
        class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600 focus:ring-indigo-500"
      />
      <span class="text-sm text-gray-400">
        {{ selectedCount }} of {{ channels.length }} selected
      </span>
    </div>

    <!-- Channel Table -->
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="text-left text-xs font-semibold text-gray-400 uppercase">
            <th class="pb-2 w-8"></th>
            <th class="pb-2 w-8">#</th>
            <th class="pb-2">Channel Name</th>
            <th class="pb-2">Category</th>
            <th class="pb-2 w-32">Actions</th>
          </tr>
        </thead>
        <tbody class="space-y-1">
          <tr
            v-for="(channel, index) in channels"
            :key="channel.id"
            class="border border-gray-700 rounded-lg hover:bg-gray-800 transition group"
          >
            <!-- Selection checkbox -->
            <td class="py-2">
              <input
                type="checkbox"
                :value="channel.id"
                v-model="selectedChannels"
                class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600 focus:ring-indigo-500"
              />
            </td>

            <!-- Drag handle + order number -->
            <td class="py-2">
              <div
                class="cursor-move opacity-30 group-hover:opacity-60 transition flex items-center"
                @dragstart="onDragStart($event, index)"
                @dragover.prevent="onDragOver($event, index)"
                @dragend="onDragEnd"
              >
                <GripVertical class="w-4 h-4 text-gray-500" />
              </div>
            </td>

            <!-- Channel name -->
            <td class="py-2">
              <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-white">{{ index + 1 }}</span>
                <span class="text-sm text-gray-300">{{ channel.name }}</span>
              </div>
            </td>

            <!-- Category -->
            <td class="py-2">
              <span class="text-sm text-gray-400">
                {{ channel.categories?.[0]?.name || 'Uncategorized' }}
              </span>
            </td>

            <!-- Actions -->
            <td class="py-2">
              <div class="flex items-center gap-1">
                <button
                  @click="$emit('edit', channel)"
                  class="p-1 text-gray-400 hover:text-white rounded"
                  title="Edit"
                >
                  <Edit class="w-4 h-4" />
                </button>
                <button
                  @click="$emit('remove', channel)"
                  class="p-1 text-gray-400 hover:text-red-400 rounded"
                  title="Remove from bouquet"
                >
                  <Trash2 class="w-4 h-4" />
                </button>
                <button
                  v-if="index > 0"
                  @click="$emit('move-up', channel, index)"
                  class="p-1 text-gray-400 hover:text-white rounded"
                  title="Move Up"
                >
                  <ArrowUp class="w-4 h-4" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <p v-if="channels.length === 0" class="text-sm text-gray-500 py-8 text-center">
        No channels in this bouquet.
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { GripVertical, Edit, Trash2, ArrowUp } from 'lucide-vue-next'

const props = defineProps({
  channels: {
    type: Array,
    default: () => [],
  },
  selectedChannelIds: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits([
  'update:selectedChannelIds',
  'reorder',
  'edit',
  'remove',
  'move-up',
])

const selectedChannels = ref([...props.selectedChannelIds])
const dragStartIndex = ref(null)

// Sync with external selection
watch(
  () => props.selectedChannelIds,
  (newVal) => {
    selectedChannels.value = [...newVal]
  }
)

// Emit selection changes
watch(selectedChannels, (newVal) => {
  emit('update:selectedChannelIds', [...newVal])
})

const allSelected = computed(() => {
  if (props.channels.length === 0) return false
  return selectedChannels.value.length === props.channels.length
})

const someSelected = computed(() => {
  return selectedChannels.value.length > 0 && selectedChannels.value.length < props.channels.length
})

const selectedCount = computed(() => selectedChannels.value.length)

const toggleSelectAll = () => {
  if (allSelected.value) {
    selectedChannels.value = []
  } else {
    selectedChannels.value = props.channels.map((c) => c.id)
  }
}

// Drag & drop reordering
const onDragStart = (event, index) => {
  dragStartIndex.value = index
  event.dataTransfer.effectAllowed = 'move'
  // Add a transparent ghost image
  const ghost = document.createElement('div')
  ghost.style.width = '1px'
  ghost.style.height = '1px'
  document.body.appendChild(ghost)
  event.dataTransfer.setDragImage(ghost, 0, 0)
  setTimeout(() => document.body.removeChild(ghost), 0)
}

const onDragOver = (event, index) => {
  event.preventDefault()
  if (dragStartIndex.value === null || dragStartIndex.value === index) return

  const newChannels = [...props.channels]
  const [moved] = newChannels.splice(dragStartIndex.value, 1)
  newChannels.splice(index, 0, moved)

  emit('reorder', newChannels.map((c) => c.id))
  dragStartIndex.value = index
}

const onDragEnd = () => {
  dragStartIndex.value = null
}
</script>
