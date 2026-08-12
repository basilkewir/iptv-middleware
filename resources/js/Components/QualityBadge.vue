<template>
  <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold border', colorClass]" :title="tooltip">
    <span>{{ icon }}</span>
    <span v-if="showLabel">{{ label }}</span>
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  quality: { type: String, required: true, validator: v => ['4k','fhd','hd','sd','low'].includes(v) },
  showLabel: { type: Boolean, default: true },
  size: { type: String, default: 'md' }
})

const config = {
  '4k':  { icon: '🟣', label: '4K',  tooltip: 'Ultra HD - 3840x2160', color: 'border-purple-500 text-purple-400 bg-purple-500/10' },
  'fhd': { icon: '🔵', label: 'FHD', tooltip: 'Full HD - 1920x1080',   color: 'border-blue-500 text-blue-400 bg-blue-500/10' },
  'hd':  { icon: '🟢', label: 'HD',  tooltip: 'High Definition - 1280x720', color: 'border-green-500 text-green-400 bg-green-500/10' },
  'sd':  { icon: '🟡', label: 'SD',  tooltip: 'Standard Definition - 640x480', color: 'border-yellow-500 text-yellow-400 bg-yellow-500/10' },
  'low': { icon: '⚪', label: 'LOW', tooltip: 'Low Quality - <640x480', color: 'border-gray-500 text-gray-400 bg-gray-500/10' },
}

const icon = computed(() => config[props.quality]?.icon || '🟡')
const label = computed(() => config[props.quality]?.label || 'SD')
const tooltip = computed(() => config[props.quality]?.tooltip || 'Unknown')
const colorClass = computed(() => config[props.quality]?.color || config.sd.color)
</script>
