<template>
  <Dropdown align="right" width="40">
    <template #trigger>
      <button class="px-2 py-1 text-xs font-medium bg-white/10 rounded hover:bg-white/20 transition-colors">
        {{ currentLabel }}
      </button>
    </template>
    <template #content>
      <button
        @click="$emit('change', -1)"
        :class="[
          'w-full text-left px-3 py-2 text-sm hover:bg-gray-700 transition-colors',
          currentQuality === -1 ? 'text-indigo-400' : 'text-gray-300'
        ]"
      >
        Auto
      </button>
      <button
        v-for="quality in qualities"
        :key="quality.index"
        @click="$emit('change', quality.index)"
        :class="[
          'w-full text-left px-3 py-2 text-sm hover:bg-gray-700 transition-colors',
          currentQuality === quality.index ? 'text-indigo-400' : 'text-gray-300'
        ]"
      >
        {{ quality.label }}
      </button>
    </template>
  </Dropdown>
</template>

<script setup>
import { computed } from 'vue'
import Dropdown from '../Common/Dropdown.vue'

const props = defineProps({
  qualities: {
    type: Array,
    default: () => [],
  },
  currentQuality: {
    type: Number,
    default: -1,
  },
})

defineEmits(['change'])

const currentLabel = computed(() => {
  if (props.currentQuality === -1) return 'Auto'
  const quality = props.qualities.find(q => q.index === props.currentQuality)
  return quality?.label || 'Auto'
})
</script>
