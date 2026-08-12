<template>
  <AdminLayout>
    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-white">Reports & Analytics</h1>
          <p class="text-gray-400 mt-1">Platform overview and insights</p>
        </div>
        <button @click="exportCSV" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition flex items-center gap-2">
          <Download class="w-4 h-4" />
          Export CSV
        </button>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-400 text-sm">Total Users</p>
              <p class="text-2xl font-bold text-white mt-1">{{ stats.total_users ?? 0 }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-blue-500/20 flex items-center justify-center">
              <Users class="w-5 h-5 text-blue-400" />
            </div>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-400 text-sm">Total Channels</p>
              <p class="text-2xl font-bold text-white mt-1">{{ stats.total_channels ?? 0 }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-purple-500/20 flex items-center justify-center">
              <Tv class="w-5 h-5 text-purple-400" />
            </div>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-400 text-sm">Total VOD</p>
              <p class="text-2xl font-bold text-white mt-1">{{ stats.total_vod ?? 0 }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-pink-500/20 flex items-center justify-center">
              <Film class="w-5 h-5 text-pink-400" />
            </div>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-400 text-sm">Revenue</p>
              <p class="text-2xl font-bold text-green-400 mt-1">${{ stats.revenue ?? 0 }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-green-500/20 flex items-center justify-center">
              <DollarSign class="w-5 h-5 text-green-400" />
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Revenue Chart -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h2 class="text-lg font-semibold text-white mb-4">Revenue (Last 7 Days)</h2>
          <div class="flex items-end gap-2 h-48">
            <div v-for="(bar, i) in revenueBars" :key="i" class="flex-1 flex flex-col items-center gap-1">
              <span class="text-gray-400 text-xs">${{ bar.value }}</span>
              <div class="w-full bg-indigo-600 rounded-t transition-all" :style="{ height: bar.height + 'px' }"></div>
              <span class="text-gray-500 text-xs">{{ bar.label }}</span>
            </div>
          </div>
        </div>

        <!-- User Registration Trend -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h2 class="text-lg font-semibold text-white mb-4">User Registrations (Last 30 Days)</h2>
          <div class="flex items-end gap-1 h-48">
            <div v-for="(bar, i) in registrationBars" :key="i" class="flex-1 flex flex-col items-center gap-1 min-w-0">
              <div class="w-full bg-green-600 rounded-t transition-all" :style="{ height: bar.height + 'px' }"></div>
              <span v-if="i % 5 === 0" class="text-gray-500 text-[10px]">{{ bar.label }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Top Channels -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h2 class="text-lg font-semibold text-white mb-4">Top Channels</h2>
          <div v-if="topChannels?.length" class="space-y-3">
            <div v-for="(ch, i) in topChannels" :key="ch.id" class="flex items-center gap-3">
              <span class="text-gray-500 text-sm w-6">{{ i + 1 }}.</span>
              <div class="flex-1 min-w-0">
                <p class="text-white text-sm truncate">{{ ch.name }}</p>
                <div class="w-full bg-gray-700 rounded-full h-1.5 mt-1">
                  <div class="bg-indigo-500 h-1.5 rounded-full" :style="{ width: Math.min((ch.viewers / maxViewers) * 100, 100) + '%' }"></div>
                </div>
              </div>
              <span class="text-gray-400 text-xs">{{ ch.viewers }} viewers</span>
            </div>
          </div>
          <p v-else class="text-gray-500 text-center py-4">No data</p>
        </div>

        <!-- Server Health -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h2 class="text-lg font-semibold text-white mb-4">Server Health</h2>
          <div v-if="serverHealth" class="space-y-4">
            <div v-for="item in healthItems" :key="item.label" class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <div class="w-2.5 h-2.5 rounded-full" :class="item.ok ? 'bg-green-500' : 'bg-red-500'"></div>
                <span class="text-gray-300 text-sm">{{ item.label }}</span>
              </div>
              <span class="text-white text-sm font-medium">{{ item.value }}</span>
            </div>
          </div>
          <p v-else class="text-gray-500 text-center py-4">No health data available</p>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Download, Users, Tv, Film, DollarSign } from 'lucide-vue-next'

const props = defineProps({
  stats: { type: Object, default: () => ({}) },
  revenueChart: { type: Array, default: () => [] },
  registrationChart: { type: Array, default: () => [] },
  topChannels: { type: Array, default: () => [] },
  serverHealth: { type: Object, default: null },
})

const revenueBars = computed(() => {
  const data = props.revenueChart.length ? props.revenueChart : [
    { label: 'Mon', value: 120 }, { label: 'Tue', value: 250 },
    { label: 'Wed', value: 180 }, { label: 'Thu', value: 310 },
    { label: 'Fri', value: 220 }, { label: 'Sat', value: 400 },
    { label: 'Sun', value: 350 },
  ]
  const max = Math.max(...data.map(d => d.value), 1)
  return data.map(d => ({
    ...d,
    height: Math.max((d.value / max) * 160, 4),
  }))
})

const registrationBars = computed(() => {
  const data = props.registrationChart.length ? props.registrationChart : Array.from({ length: 30 }, (_, i) => ({
    label: `${i + 1}`,
    value: Math.floor(Math.random() * 20),
  }))
  const max = Math.max(...data.map(d => d.value), 1)
  return data.map(d => ({
    ...d,
    height: Math.max((d.value / max) * 160, 4),
  }))
})

const maxViewers = computed(() => Math.max(...(props.topChannels || []).map(c => c.viewers || 0), 1))

const healthItems = computed(() => {
  if (!props.serverHealth) return []
  const h = props.serverHealth
  return [
    { label: 'CPU Usage', value: `${h.cpu ?? 0}%`, ok: (h.cpu ?? 0) < 85 },
    { label: 'Memory Usage', value: `${h.memory ?? 0}%`, ok: (h.memory ?? 0) < 85 },
    { label: 'Disk Usage', value: `${h.disk ?? 0}%`, ok: (h.disk ?? 0) < 90 },
    { label: 'Active Connections', value: h.active_connections ?? 0, ok: true },
    { label: 'Uptime', value: h.uptime || 'N/A', ok: true },
  ]
})

const exportCSV = () => {
  router.get(route('admin.reports.export'), {}, { preserveScroll: true })
}
</script>
