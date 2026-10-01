<template>
  <AdminLayout>
    <div class="p-6 space-y-6">
      <!-- Header -->
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-white">Server Monitoring</h1>
          <p class="text-gray-400 mt-1">
            {{ system.hostname }} · {{ system.os }} · up {{ system.uptime }}
            <span v-if="system.php_version" class="text-gray-600">· PHP {{ system.php_version }}</span>
          </p>
        </div>
        <div class="flex items-center gap-3">
          <span class="text-xs text-gray-500">{{ collectedAgo ? `updated ${collectedAgo} ago` : 'waiting for sample' }}</span>
          <button
            @click="autoRefresh = !autoRefresh"
            class="px-3 py-2 rounded-lg text-sm transition flex items-center gap-2"
            :class="autoRefresh ? 'bg-green-600/20 text-green-300 border border-green-600/40' : 'bg-gray-700 text-gray-300'"
          >
            <span class="w-2 h-2 rounded-full" :class="autoRefresh ? 'bg-green-400 animate-pulse' : 'bg-gray-500'" />
            {{ autoRefresh ? `Live · ${refreshSeconds}s` : 'Paused' }}
          </button>
          <button
            @click="load"
            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition flex items-center gap-2"
          >
            <RefreshCw :class="{ 'animate-spin': loading }" class="w-4 h-4" />
            Refresh
          </button>
        </div>
      </div>

      <!-- KPI cards -->
      <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
        <div v-for="kpi in kpis" :key="kpi.label" class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center gap-2 text-gray-400 text-xs uppercase tracking-wide">
            <component :is="kpi.icon" class="w-4 h-4" :class="kpi.iconClass" />
            {{ kpi.label }}
          </div>
          <div class="mt-2 flex items-baseline gap-1">
            <span class="text-2xl font-semibold text-white">{{ kpi.value }}</span>
            <span v-if="kpi.unit" class="text-gray-500 text-sm">{{ kpi.unit }}</span>
          </div>
          <div class="mt-2 h-1.5 bg-gray-700 rounded-full overflow-hidden">
            <div class="h-full rounded-full" :class="kpi.barClass" :style="{ width: `${kpi.bar}%` }" />
          </div>
        </div>
      </div>

      <!-- CPU / Memory history -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-white font-semibold flex items-center gap-2">
              <Cpu class="w-4 h-4 text-indigo-400" /> CPU
            </h2>
            <span class="text-xs text-gray-500">{{ system.cores || '?' }} cores · {{ system.load?.join(' / ') }} load</span>
          </div>
          <div class="flex items-end gap-1 h-32">
            <div
              v-for="(point, i) in cpuHistory"
              :key="i"
              class="flex-1 bg-indigo-500/80 rounded-t hover:bg-indigo-400 transition-all"
              :style="{ height: `${point}%` }"
              :title="`${point}%`"
            />
            <div v-if="!cpuHistory.length" class="text-gray-600 text-sm self-center">No samples yet</div>
          </div>
          <div class="mt-3 flex justify-between text-[10px] text-gray-600">
            <span>{{ historyStartLabel }}</span>
            <span>now</span>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-white font-semibold flex items-center gap-2">
              <MemoryStick class="w-4 h-4 text-green-400" /> Memory
            </h2>
            <span class="text-xs text-gray-500">{{ system.memory_used_mb }} MB / {{ system.memory_total_mb }} MB</span>
          </div>
          <div class="flex items-end gap-1 h-32">
            <div
              v-for="(point, i) in memoryHistory"
              :key="i"
              class="flex-1 bg-green-500/80 rounded-t hover:bg-green-400 transition-all"
              :style="{ height: `${point}%` }"
              :title="`${point}%`"
            />
            <div v-if="!memoryHistory.length" class="text-gray-600 text-sm self-center">No samples yet</div>
          </div>
          <div class="mt-3 flex justify-between text-[10px] text-gray-600">
            <span>{{ historyStartLabel }}</span>
            <span>now</span>
          </div>
        </div>
      </div>

      <!-- Disks -->
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        <h2 class="text-white font-semibold flex items-center gap-2 mb-4">
          <HardDrive class="w-4 h-4 text-orange-400" /> Disks
          <span class="text-xs text-gray-500 font-normal">{{ disks.length }} mounted</span>
        </h2>
        <div class="space-y-4">
          <div v-for="disk in disks" :key="disk.mount">
            <div class="flex items-center justify-between text-sm mb-1">
              <div class="flex items-center gap-2">
                <span class="text-white font-medium">{{ disk.mount }}</span>
                <span class="text-gray-600 text-xs">{{ disk.device }} · {{ disk.fstype || '' }}</span>
              </div>
              <div class="text-gray-400 text-xs">
                {{ disk.used_gb }} GB used of {{ disk.total_gb }} GB · {{ disk.free_gb }} GB free
                <span class="ml-2 font-semibold" :class="usageClass(disk.usage)">{{ disk.usage }}%</span>
              </div>
            </div>
            <div class="h-2.5 bg-gray-700 rounded-full overflow-hidden">
              <div class="h-full rounded-full transition-all" :class="usageBarClass(disk.usage)" :style="{ width: `${disk.usage}%` }" />
            </div>
          </div>
          <div v-if="!disks.length" class="text-gray-600 text-sm">No mounted filesystems detected.</div>
        </div>
      </div>

      <!-- Network -->
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-white font-semibold flex items-center gap-2">
            <Activity class="w-4 h-4 text-blue-400" /> Network interfaces
          </h2>
          <span class="text-xs text-gray-500">{{ nics.filter(n => n.up).length }}/{{ nics.length }} up · live throughput</span>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
          <div v-for="nic in nics" :key="nic.name" class="p-4 bg-gray-700/40 rounded-lg">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full shrink-0" :class="nic.up ? 'bg-green-400' : 'bg-red-400'" />
                <span class="text-white font-medium">{{ nic.name }}</span>
                <span v-if="nic.ip" class="text-gray-500 text-xs">{{ nic.ip }}</span>
              </div>
              <span class="text-[10px] uppercase tracking-wide" :class="nic.up ? 'text-green-400' : 'text-red-400'">
                {{ nic.up ? 'up' : 'down' }}
              </span>
            </div>

            <div class="grid grid-cols-2 gap-3 mt-3">
              <div>
                <div class="text-[10px] text-gray-500 uppercase">Receive</div>
                <div class="text-blue-300 font-semibold">{{ nic.rx_mbps }} Mbps</div>
                <div class="text-gray-600 text-[10px]">{{ nic.rx_total_gb }} GB total</div>
              </div>
              <div>
                <div class="text-[10px] text-gray-500 uppercase">Transmit</div>
                <div class="text-green-300 font-semibold">{{ nic.tx_mbps }} Mbps</div>
                <div class="text-gray-600 text-[10px]">{{ nic.tx_total_gb }} GB total</div>
              </div>
            </div>

            <!-- sparkline: rx over the sample window -->
            <div class="flex items-end gap-[2px] h-10 mt-3">
              <div
                v-for="(point, i) in nicHistory(nic.name, 'rx')"
                :key="'rx' + i"
                class="flex-1 bg-blue-500/70 rounded-t"
                :style="{ height: `${point}%` }"
                :title="`${point}%`"
              />
              <div
                v-for="(point, i) in nicHistory(nic.name, 'tx')"
                :key="'tx' + i"
                class="flex-1 bg-green-500/50 rounded-t"
                :style="{ height: `${point}%` }"
              />
            </div>
          </div>
          <div v-if="!nics.length" class="text-gray-600 text-sm">No interfaces detected.</div>
        </div>
      </div>

      <!-- Channels -->
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
          <h2 class="text-white font-semibold flex items-center gap-2">
            <Radio class="w-4 h-4 text-purple-400" /> My Channels
            <span class="text-xs text-gray-500 font-normal">
              {{ channelsSummary.running }} running · {{ channelsSummary.stalled }} stalled · {{ channelsSummary.stopped }} stopped
              · {{ channelsSummary.playlist_items }} playlist items
            </span>
          </h2>
          <Link :href="route('admin.channels.index')" class="text-sm text-indigo-400 hover:text-indigo-300">
            Manage channels →
          </Link>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-gray-500 text-xs uppercase tracking-wide border-b border-gray-700">
                <th class="pb-2 pr-4">#</th>
                <th class="pb-2 pr-4">Channel</th>
                <th class="pb-2 pr-4">State</th>
                <th class="pb-2 pr-4">Broadcast</th>
                <th class="pb-2 pr-4">Playlist</th>
                <th class="pb-2 pr-4">Playout</th>
                <th class="pb-2">Last broadcast</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="channel in channels" :key="channel.id" class="border-b border-gray-700/50">
                <td class="py-3 pr-4 text-gray-500">{{ channel.channel_number }}</td>
                <td class="py-3 pr-4">
                  <div class="text-white font-medium">{{ channel.name }}</div>
                  <div class="text-gray-600 text-xs">{{ channel.slug }}</div>
                </td>
                <td class="py-3 pr-4">
                  <span
                    class="px-2 py-1 rounded-full text-xs font-medium"
                    :class="stateClass(channel.state)"
                  >
                    {{ channel.state }}
                  </span>
                </td>
                <td class="py-3 pr-4 text-gray-400">{{ channel.broadcast_status || '—' }}</td>
                <td class="py-3 pr-4">
                  <div class="text-gray-300">
                    {{ channel.active_playlist_items }}/{{ channel.playlist_items }} active
                  </div>
                  <div class="text-gray-600 text-xs">
                    {{ channel.playlist_type || 'playlist' }}<span v-if="channel.loop_playlist"> · looping</span>
                  </div>
                </td>
                <td class="py-3 pr-4 text-gray-400">{{ channel.playout_mode || '—' }}</td>
                <td class="py-3 text-gray-500 text-xs">{{ formatDate(channel.last_broadcast) }}</td>
              </tr>
              <tr v-if="!channels.length">
                <td colspan="7" class="py-6 text-center text-gray-600">
                  No My Channels yet — create one and upload a playlist to see it running here.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Channel ingests (HLS freshness) -->
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        <h2 class="text-white font-semibold flex items-center gap-2 mb-4">
          <Tv class="w-4 h-4 text-cyan-400" /> Channel ingests
          <span class="text-xs text-gray-500 font-normal">{{ ingestSummary }}</span>
        </h2>
        <div class="space-y-2">
          <div v-for="ing in ingests" :key="ing.id" class="flex items-center gap-3 p-2.5 bg-gray-700/40 rounded-lg">
            <span class="w-2 h-2 rounded-full shrink-0" :class="ingestDot(ing.status)" />
            <span class="text-white text-sm flex-1">
              {{ ing.name || 'Channel ' + ing.channel_number }}
              <span class="text-gray-600 text-xs">#{{ ing.channel_number }}</span>
            </span>
            <span class="text-xs" :class="ingestText(ing.status)">{{ ing.status }}</span>
            <span class="text-gray-600 text-xs w-24 text-right">
              {{ ing.last_segment_age != null ? ing.last_segment_age + 's ago' : 'no segments' }}
            </span>
          </div>
          <div v-if="!ingests.length" class="text-gray-600 text-sm">No active channel ingests.</div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  RefreshCw,
  Cpu,
  MemoryStick,
  HardDrive,
  Gauge,
  Clock,
  Activity,
  Radio,
  Tv,
} from 'lucide-vue-next'
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  monitoring: {
    type: Object,
    default: () => ({ system: {}, channels: [], channels_summary: {} }),
  },
})

const loading = ref(false)
const autoRefresh = ref(true)
const refreshSeconds = 5
const data = ref(props.monitoring || { system: {}, channels: [], channels_summary: {} })
let timer = null

const system = computed(() => data.value.system || {})
const disks = computed(() => system.value.disks || [])
const nics = computed(() => system.value.nics || [])
const ingests = computed(() => system.value.ingests || [])
const history = computed(() => system.value.history || [])
const channels = computed(() => data.value.channels || [])
const channelsSummary = computed(() => data.value.channels_summary || {})

const kpis = computed(() => {
  const s = system.value
  return [
    {
      label: 'CPU',
      value: s.cpu_usage ?? 0,
      unit: '%',
      icon: Cpu,
      iconClass: 'text-indigo-400',
      bar: s.cpu_usage ?? 0,
      barClass: 'bg-indigo-500',
    },
    {
      label: 'Memory',
      value: s.memory_usage ?? 0,
      unit: '%',
      icon: MemoryStick,
      iconClass: 'text-green-400',
      bar: s.memory_usage ?? 0,
      barClass: 'bg-green-500',
    },
    {
      label: 'Disk',
      value: s.disk_usage ?? 0,
      unit: '%',
      icon: HardDrive,
      iconClass: 'text-orange-400',
      bar: s.disk_usage ?? 0,
      barClass: 'bg-orange-500',
    },
    {
      label: 'Load 1m',
      value: s.load?.[0] ?? 0,
      unit: '',
      icon: Gauge,
      iconClass: 'text-cyan-400',
      bar: Math.min(100, ((s.load?.[0] ?? 0) / Math.max(1, s.cores || 1)) * 100),
      barClass: 'bg-cyan-500',
    },
    {
      label: 'Cores',
      value: s.cores ?? 0,
      unit: '',
      icon: Cpu,
      iconClass: 'text-gray-400',
      bar: 100,
      barClass: 'bg-gray-600',
    },
    {
      label: 'Uptime',
      value: s.uptime || '—',
      unit: '',
      icon: Clock,
      iconClass: 'text-purple-400',
      bar: 100,
      barClass: 'bg-purple-500',
    },
  ]
})

const cpuHistory = computed(() => history.value.map((p) => Math.round(p.cpu || 0)))
const memoryHistory = computed(() => history.value.map((p) => Math.round(p.memory || 0)))

const historyStartLabel = computed(() => {
  if (!history.value.length) return ''
  const first = history.value[0]?.t
  if (!first) return ''
  return `${history.value.length} samples · ${Math.max(0, Math.round((Date.now() / 1000) - first))}s window`
})

/**
 * Per-NIC history series scaled to the interface's own peak so low-traffic
 * NICs still get a readable sparkline.
 */
const nicHistory = (name, dir) => {
  const series = history.value.map((p) => Number(p.nics?.[name]?.[dir] || 0))
  const peak = Math.max(1, ...series)
  return series.map((v) => Math.round((v / peak) * 100))
}

const collectedAgo = computed(() => {
  const at = system.value.collected_at
  if (!at) return ''
  const secs = Math.max(0, Math.round((Date.now() - new Date(at).getTime()) / 1000))
  return secs < 2 ? 'just now' : `${secs}s`
})

const ingestSummary = computed(() => {
  const list = ingests.value
  if (!list.length) return ''
  const live = list.filter((i) => i.status === 'live').length
  return `${live}/${list.length} live`
})

const usageClass = (pct) => (pct >= 90 ? 'text-red-400' : pct >= 75 ? 'text-yellow-400' : 'text-green-400')
const usageBarClass = (pct) => (pct >= 90 ? 'bg-red-500' : pct >= 75 ? 'bg-yellow-500' : 'bg-green-500')

const stateClass = (state) =>
  ({
    running: 'bg-green-500/20 text-green-300',
    stalled: 'bg-yellow-500/20 text-yellow-300',
    stopped: 'bg-red-500/20 text-red-300',
  }[state] || 'bg-gray-500/20 text-gray-300')

const ingestDot = (status) =>
  ({ live: 'bg-green-400', stale: 'bg-yellow-400', starting: 'bg-blue-400', down: 'bg-red-400' }[status] || 'bg-gray-500')

const ingestText = (status) =>
  ({ live: 'text-green-400', stale: 'text-yellow-400', starting: 'text-blue-400', down: 'text-red-400' }[status] || 'text-gray-400')

const formatDate = (value) => {
  if (!value) return '—'
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? '—' : date.toLocaleString()
}

const load = async () => {
  loading.value = true
  try {
    const response = await window.axios.get(route('admin.monitoring.metrics'))
    if (response.data?.data) {
      data.value = response.data.data
    }
  } catch (error) {
    // keep the last good sample on a failed poll
    console.error('monitoring poll failed', error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  timer = setInterval(() => {
    if (autoRefresh.value) {
      load()
    }
  }, refreshSeconds * 1000)
})

onUnmounted(() => {
  if (timer) {
    clearInterval(timer)
  }
})
</script>
