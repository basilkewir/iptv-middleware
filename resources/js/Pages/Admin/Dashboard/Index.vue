<template>
  <AdminLayout>
    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-white">Admin Dashboard</h1>
          <p class="text-gray-400 mt-1">Overview of your IPTV platform</p>
        </div>
        <div class="flex gap-3">
          <button @click="refreshData" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition flex items-center gap-2">
            <RefreshCw :class="{ 'animate-spin': loading }" class="w-4 h-4" />
            Refresh
          </button>
          <Link :href="route('admin.settings.general')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition">
            Settings
          </Link>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 hover:border-gray-600 transition">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-400 text-sm">Total Users</p>
              <p class="text-2xl font-bold text-white mt-1">{{ formatNumber(stats.total_users) }}</p>
            </div>
            <div class="p-3 rounded-lg bg-indigo-500/20">
              <Users class="w-6 h-6 text-indigo-400" />
            </div>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 hover:border-gray-600 transition">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-400 text-sm">Revenue Today</p>
              <p class="text-2xl font-bold text-white mt-1">${{ formatNumber(stats.revenue_today) }}</p>
            </div>
            <div class="p-3 rounded-lg bg-green-500/20">
              <DollarSign class="w-6 h-6 text-green-400" />
            </div>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 hover:border-gray-600 transition">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-400 text-sm">Active Streams</p>
              <p class="text-2xl font-bold text-white mt-1">{{ formatNumber(stats.active_streams) }}</p>
            </div>
            <div class="p-3 rounded-lg bg-blue-500/20">
              <Radio class="w-6 h-6 text-blue-400" />
            </div>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 hover:border-gray-600 transition">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-400 text-sm">Servers Online</p>
              <p class="text-2xl font-bold text-white mt-1">{{ stats.servers_online }}</p>
            </div>
            <div class="p-3 rounded-lg bg-purple-500/20">
              <Server class="w-6 h-6 text-purple-400" />
            </div>
          </div>
        </div>
      </div>

      <!-- System Monitoring -->
      <div v-if="system" class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h3 class="text-lg font-semibold text-white">System Monitoring</h3>
            <p class="text-gray-500 text-xs mt-0.5">{{ system.hostname }} · {{ system.os }} · up {{ system.uptime }}</p>
          </div>
          <span class="text-xs text-gray-500">updated {{ collectedAgo }}</span>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
          <div v-for="gauge in systemGauges" :key="gauge.label" class="p-4 bg-gray-700/50 rounded-lg">
            <div class="flex items-center justify-between mb-2">
              <span class="text-gray-400 text-sm">{{ gauge.label }}</span>
              <component :is="gauge.icon" class="w-4 h-4" :class="gauge.tone === 'ok' ? 'text-green-400' : gauge.tone === 'warn' ? 'text-yellow-400' : 'text-red-400'" />
            </div>
            <p class="text-xl font-bold text-white">{{ gauge.value }}</p>
            <div class="mt-2 h-1.5 bg-gray-600/60 rounded-full overflow-hidden">
              <div class="h-full rounded-full transition-all" :class="gauge.barClass" :style="{ width: gauge.pct + '%' }" />
            </div>
          </div>
        </div>

        <div>
          <div class="flex items-center justify-between mb-2">
            <span class="text-gray-400 text-sm">HLS Ingests</span>
            <span class="text-xs text-gray-500">{{ ingestSummary }}</span>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2">
            <div v-for="ing in ingests" :key="ing.id" class="flex items-center gap-3 p-2.5 bg-gray-700/40 rounded-lg">
              <span class="w-2 h-2 rounded-full shrink-0" :class="ing.status === 'live' ? 'bg-green-400 animate-pulse' : ing.status === 'stale' ? 'bg-yellow-400' : ing.status === 'starting' ? 'bg-blue-400' : 'bg-red-400'" />
              <span class="text-white text-sm truncate flex-1">#{{ ing.channel_number }} {{ ing.name || 'Unnamed' }}</span>
              <span class="text-xs uppercase tracking-wide" :class="ing.status === 'live' ? 'text-green-400' : ing.status === 'stale' ? 'text-yellow-400' : 'text-red-400'">{{ ing.status }}</span>
            </div>
          </div>
          <div v-if="!ingests.length" class="text-gray-500 text-sm text-center py-3">No active channel ingests</div>
        </div>
      </div>

      <!-- Top Channels -->
      <div v-if="stats.top_channels?.length" class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        <h3 class="text-lg font-semibold text-white mb-4">Top Channels</h3>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="border-b border-gray-700">
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-400 uppercase">#</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-400 uppercase">Channel</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-400 uppercase">Category</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-400 uppercase">Viewers</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
              <tr v-for="(channel, idx) in stats.top_channels" :key="channel.id" class="hover:bg-gray-700/50">
                <td class="px-4 py-3 text-gray-500 text-sm">{{ idx + 1 }}</td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-3">
                    <img v-if="channel.logo_url" :src="channel.logo_url" class="w-8 h-8 rounded object-cover bg-gray-700" />
                    <div v-else class="w-8 h-8 rounded bg-gray-700 flex items-center justify-center">
                      <Tv class="w-4 h-4 text-gray-500" />
                    </div>
                    <span class="text-white font-medium">{{ channel.name }}</span>
                  </div>
                </td>
                <td class="px-4 py-3 text-gray-400 text-sm">{{ channel.category || '-' }}</td>
                <td class="px-4 py-3 text-white text-sm">{{ formatNumber(channel.view_count) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Charts Row -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">User Growth</h3>
          <div v-if="userGrowthChartData.length" class="h-64 flex items-end gap-2">
            <div v-for="(bar, i) in userGrowthChartData" :key="i" class="flex-1 flex flex-col items-center">
              <div class="w-full bg-indigo-600 rounded-t-md hover:bg-indigo-500 transition-all" :style="{ height: bar.height + '%' }" />
              <span class="text-xs text-gray-500 mt-2">{{ bar.label }}</span>
            </div>
          </div>
          <div v-else class="h-64 flex items-center justify-center text-gray-500 text-sm">No data available</div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Bandwidth Usage</h3>
          <div v-if="bandwidthChartData.length" class="h-64 flex items-end gap-2">
            <div v-for="(bar, i) in bandwidthChartData" :key="i" class="flex-1 flex flex-col items-center">
              <div class="w-full bg-green-600 rounded-t-md hover:bg-green-500 transition-all" :style="{ height: bar.height + '%' }" />
              <span class="text-xs text-gray-500 mt-2">{{ bar.label }}</span>
            </div>
          </div>
          <div v-else class="h-64 flex items-center justify-center text-gray-500 text-sm">No data available</div>
        </div>
      </div>

      <!-- Recent Activity & Server Health -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-gray-800 rounded-xl p-6 border border-gray-700">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-white">Recent Activity</h3>
            <Link :href="route('admin.users.index')" class="text-indigo-400 hover:text-indigo-300 text-sm">View All</Link>
          </div>
          <div class="space-y-3">
            <div v-for="activity in recentActivityList" :key="activity.id" class="flex items-center gap-3 p-3 bg-gray-700/50 rounded-lg">
              <div class="p-2 rounded-full" :class="activityIconClass(activity.type)">
                <component :is="activityIcon(activity.type)" class="w-4 h-4" :class="activityIconTextColor(activity.type)" />
              </div>
              <div class="flex-1">
                <p class="text-white text-sm">{{ activity.title }}</p>
                <p class="text-gray-400 text-xs">{{ activity.message }}</p>
                <p class="text-gray-500 text-xs mt-0.5">{{ activity.time }}</p>
              </div>
            </div>
            <div v-if="!recentActivityList.length" class="text-gray-500 text-sm text-center py-4">No recent activity</div>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Streaming Usage</h3>
          <div class="space-y-4">
            <div class="flex justify-between text-sm mb-1">
              <span class="text-gray-400">Active Streams</span>
              <span class="text-white">{{ stats.active_streams || 0 }}</span>
            </div>
            <div class="flex justify-between text-sm mb-1">
              <span class="text-gray-400">Total Channels</span>
              <span class="text-white">{{ stats.total_channels || 0 }}</span>
            </div>
            <div class="flex justify-between text-sm mb-1">
              <span class="text-gray-400">Total VOD</span>
              <span class="text-white">{{ stats.total_vod || 0 }}</span>
            </div>
            <div class="flex justify-between text-sm mb-1">
              <span class="text-gray-400">Active Subscriptions</span>
              <span class="text-white">{{ stats.active_subscriptions || 0 }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Server Health & Quick Actions -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Server Health</h3>
          <div class="grid grid-cols-2 gap-4">
            <div v-for="server in serverHealthList" :key="server.id" class="p-4 bg-gray-700/50 rounded-lg">
              <div class="flex items-center justify-between mb-2">
                <span class="text-gray-400 text-sm">{{ server.name }}</span>
                <span class="w-2 h-2 rounded-full" :class="server.status === 'active' ? 'bg-green-400' : 'bg-red-400'" />
              </div>
              <div class="space-y-1 text-xs">
                <div class="flex justify-between">
                  <span class="text-gray-500">CPU</span>
                  <span class="text-white">{{ server.cpu }}%</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-500">Memory</span>
                  <span class="text-white">{{ server.memory }}%</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-500">Disk</span>
                  <span class="text-white">{{ server.disk }}%</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-500">Streams</span>
                  <span class="text-white">{{ server.current_streams }}/{{ server.max_streams }}</span>
                </div>
              </div>
            </div>
            <div v-if="!serverHealthList.length" class="col-span-2 text-gray-500 text-sm text-center py-4">No servers</div>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Quick Actions</h3>
          <div class="grid grid-cols-2 gap-3">
            <Link v-for="action in quickActions" :key="action.label" :href="action.href" class="p-4 bg-gray-700/50 rounded-lg hover:bg-gray-700 transition flex flex-col items-center gap-2 text-center">
              <component :is="action.icon" class="w-8 h-8 text-indigo-400" />
              <span class="text-white text-sm">{{ action.label }}</span>
            </Link>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { RefreshCw, Users, DollarSign, Radio, Server, Tv, UserPlus, Film, Calendar, Settings, CreditCard, Cpu, MemoryStick, HardDrive, Gauge } from 'lucide-vue-next'

const props = defineProps({
  stats: {
    type: Object,
    default: () => ({
      total_users: 0,
      revenue_today: 0,
      active_streams: 0,
      servers_online: 0,
      total_channels: 0,
      total_vod: 0,
      active_subscriptions: 0,
      top_channels: [],
      recent_activity: [],
      server_health: [],
      system: null,
      user_growth: [],
      bandwidth_usage: [],
    }),
  },
})

const loading = ref(false)

const formatNumber = (num) => {
  if (num === null || num === undefined) return '0'
  return Number(num).toLocaleString('en-US', { maximumFractionDigits: 2 })
}

const userGrowthChartData = computed(() => {
  const data = props.stats.user_growth || []
  if (!data.length) return []
  const maxCount = Math.max(...data.map(d => d.count), 1)
  return data.map(d => ({
    label: d.date,
    height: Math.round((d.count / maxCount) * 100),
  }))
})

const bandwidthChartData = computed(() => {
  const data = props.stats.bandwidth_usage || []
  if (!data.length) return []
  const maxBytes = Math.max(...data.map(d => d.bandwidth_mb), 1)
  return data.map(d => ({
    label: d.date,
    height: Math.round((d.bandwidth_mb / maxBytes) * 100),
  }))
})

const recentActivityList = computed(() => props.stats.recent_activity || [])
const serverHealthList = computed(() => props.stats.server_health || [])

const system = computed(() => props.stats.system || null)
const ingests = computed(() => system.value?.ingests || [])
const collectedAgo = computed(() => {
  if (!system.value?.collected_at) return ''
  const secs = Math.max(0, Math.round((Date.now() - new Date(system.value.collected_at).getTime()) / 1000))
  return secs < 60 ? `${secs}s ago` : `${Math.round(secs / 60)}m ago`
})
const ingestSummary = computed(() => {
  const list = ingests.value
  if (!list.length) return '0 total'
  const live = list.filter(i => i.status === 'live').length
  return `${live}/${list.length} live`
})

const toneForPct = (pct) => (pct < 60 ? 'ok' : pct < 85 ? 'warn' : 'critical')

const systemGauges = computed(() => {
  const s = system.value
  if (!s) return []
  const gauges = [
    { label: 'CPU', value: `${s.cpu_usage}%`, pct: s.cpu_usage, icon: Cpu },
    { label: 'Memory', value: `${s.memory_usage}%`, pct: s.memory_usage, icon: MemoryStick },
    { label: 'Disk', value: `${s.disk_usage}%`, pct: s.disk_usage, icon: HardDrive },
  ]
  return gauges.map(g => {
    const tone = toneForPct(g.pct)
    return {
      ...g,
      tone,
      barClass: tone === 'ok' ? 'bg-green-500' : tone === 'warn' ? 'bg-yellow-400' : 'bg-red-500',
    }
  }).concat([
    {
      label: `Load (${(s.load || []).join(' / ')})`,
      value: `${(s.load || [0])[0]}`,
      pct: Math.min(100, ((s.load || [0])[0] / Math.max(1, 4)) * 100),
      icon: Gauge,
      tone: 'ok',
      barClass: 'bg-indigo-500',
    },
  ])
})

const activityIcon = (type) => {
  const icons = { user: UserPlus, subscription: CreditCard, system: Server }
  return icons[type] || Server
}

const activityIconClass = (type) => {
  const classes = { user: 'bg-green-500/20', subscription: 'bg-purple-500/20', system: 'bg-red-500/20' }
  return classes[type] || 'bg-gray-500/20'
}

const activityIconTextColor = (type) => {
  const classes = { user: 'text-green-400', subscription: 'text-purple-400', system: 'text-red-400' }
  return classes[type] || 'text-gray-400'
}

const quickActions = ref([
  { label: 'Add User', href: route('admin.users.create'), icon: UserPlus },
  { label: 'Add Channel', href: route('admin.channels.create'), icon: Tv },
  { label: 'Add VOD', href: route('admin.vod.create'), icon: Film },
  { label: 'Manage EPG', href: route('admin.epg.index'), icon: Calendar },
  { label: 'Server Monitor', href: route('admin.servers.monitor'), icon: Server },
  { label: 'Settings', href: route('admin.settings.general'), icon: Settings },
])

const refreshData = () => {
  loading.value = true
  window.location.reload()
}
</script>
