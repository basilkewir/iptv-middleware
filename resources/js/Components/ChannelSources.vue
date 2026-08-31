<template>
  <div class="flex flex-wrap items-center gap-1.5">
    <button
      v-for="src in sources"
      :key="src.index"
      type="button"
      @click="$emit('switch', src.index)"
      :disabled="disabled || src.index === activeIndex"
      :class="chipClass(src)"
      :title="tooltip(src)"
      class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium border transition select-none"
    >
      <span :class="['w-1.5 h-1.5 rounded-full shrink-0', dotClass(src)]"></span>
      <span class="shrink-0">{{ src.label }}</span>
      <span :class="['uppercase tracking-wide', src.index === activeIndex ? 'text-indigo-200' : 'opacity-80']">
        {{ statusText(src) }}
      </span>
      <span v-if="src.index === activeIndex" class="px-1 py-0.5 rounded bg-indigo-500/30 text-indigo-100 text-[10px] leading-none" title="Currently playing">ACTIVE</span>
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  channel: { type: Object, required: true },
  disabled: { type: Boolean, default: false },
})

defineEmits(['switch'])

const activeIndex = computed(() => props.channel?.active_source_index ?? 0)

const statusMap = computed(() => {
  const list = props.channel?.source_statuses
  if (!Array.isArray(list)) return {}
  return list.reduce((acc, s) => {
    acc[s.index] = s
    return acc
  }, {})
})

const configured = computed(() => [props.channel?.stream_url, props.channel?.backup_url_1, props.channel?.backup_url_2])

const sources = computed(() => {
  const labels = ['Primary', 'Backup 1', 'Backup 2']
  const result = []
  for (let i = 0; i < 3; i++) {
    if (!configured.value[i]) continue
    const status = statusMap.value[i] || {}
    result.push({
      index: i,
      label: labels[i],
      status: status.status || 'unknown',
      error: status.error || null,
      last_checked_at: status.last_checked_at || null,
      last_online_at: status.last_online_at || null,
    })
  }
  return result
})

const statusText = (src) => {
  if (src.status === 'online') return 'LIVE'
  if (src.status === 'offline') return 'OFFLINE'
  if (src.status === 'unconfigured') return '—'
  return '…'
}

const dotClass = (src) => {
  if (src.status === 'online') return 'bg-emerald-400 shadow-[0_0_6px_rgba(52,211,153,0.9)]'
  if (src.status === 'offline') return 'bg-red-500'
  return 'bg-gray-500'
}

const chipClass = (src) => {
  const isActive = src.index === activeIndex.value
  const base = 'shrink-0 focus:outline-none'
  const text = isActive ? 'text-indigo-100 border-indigo-400/50 bg-indigo-500/20' : 'text-gray-300 border-gray-600 bg-gray-700/50 hover:bg-gray-600/60'
  const offline = src.status === 'offline' && !isActive ? 'opacity-60' : ''
  return `${base} ${text} ${offline}`
}

const tooltip = (src) => {
  const parts = [`${src.label} — ${statusText(src)}`]
  if (src.status === 'online') parts.push(`Last online: ${formatDate(src.last_online_at)}`)
  if (src.error) parts.push(`Error: ${src.error}`)
  if (src.last_checked_at) parts.push(`Checked: ${formatDate(src.last_checked_at)}`)
  parts.push(src.index === activeIndex.value ? 'Currently active source' : 'Click to switch to this source')
  return parts.join('\n')
}

const formatDate = (d) => {
  if (!d) return 'never'
  const date = new Date(d)
  if (isNaN(date.getTime())) return 'never'
  return date.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>