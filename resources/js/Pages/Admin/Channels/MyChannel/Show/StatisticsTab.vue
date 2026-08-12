<template>
  <div class="space-y-6">
    <h3 class="text-lg font-semibold text-white">Statistics & Analytics</h3>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <StatCard label="Total Views" :value="stats.total_views" />
      <StatCard label="Subscribers" :value="stats.total_subscribers" color="text-blue-400" />
      <StatCard label="Peak Viewers" :value="stats.peak_viewers" color="text-red-400" />
      <StatCard label="Total Watch Time" :value="formatDuration(stats.total_watch_time)" color="text-green-400" />
    </div>

    <!-- Viewership bar chart -->
    <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
      <h4 class="text-sm font-medium text-gray-300 mb-4">Viewership (Last 24h)</h4>
      <div class="h-40 flex items-end gap-0.5">
        <div v-for="(v, i) in timeline" :key="i"
          class="flex-1 flex flex-col justify-end group relative"
          style="min-height: 4px">
          <div class="bg-indigo-500/60 hover:bg-indigo-400 rounded-t transition-all"
            :style="{ height: maxTimeline > 0 ? Math.max((v / maxTimeline) * 100, 2) + '%' : '2%' }"
            :title="`${v} viewers`">
          </div>
        </div>
      </div>
      <div class="flex justify-between text-xs text-gray-500 mt-2">
        <span>24h ago</span>
        <span>Now</span>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        <h4 class="text-sm font-medium text-gray-300 mb-4">Device Breakdown</h4>
        <div class="space-y-3">
          <div v-for="d in deviceData" :key="d.name" class="flex items-center gap-3">
            <span class="w-3 h-3 rounded-full shrink-0" :class="d.color"></span>
            <span class="text-gray-300 flex-1 text-sm">{{ d.name }}</span>
            <div class="w-24 bg-gray-700 rounded-full h-1.5">
              <div class="h-1.5 rounded-full" :class="d.color" :style="{ width: d.value + '%' }"></div>
            </div>
            <span class="text-white text-sm w-10 text-right">{{ d.value }}%</span>
          </div>
        </div>
      </div>

      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        <h4 class="text-sm font-medium text-gray-300 mb-4">Quality Distribution</h4>
        <div class="space-y-3">
          <div v-for="q in qualityData" :key="q.name" class="flex items-center gap-3">
            <span class="w-3 h-3 rounded-full shrink-0" :class="q.color"></span>
            <span class="text-gray-300 flex-1 text-sm">{{ q.name }}</span>
            <div class="w-24 bg-gray-700 rounded-full h-1.5">
              <div class="h-1.5 rounded-full" :class="q.color" :style="{ width: q.value + '%' }"></div>
            </div>
            <span class="text-white text-sm w-10 text-right">{{ q.value }}%</span>
          </div>
        </div>
      </div>
    </div>

    <div v-if="dailyAnalytics.length" class="bg-gray-800 rounded-xl p-6 border border-gray-700">
      <h4 class="text-sm font-medium text-gray-300 mb-4">Recent Daily Stats</h4>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-gray-500 border-b border-gray-700">
              <th class="pb-2">Date</th>
              <th class="pb-2">Views</th>
              <th class="pb-2">Unique Viewers</th>
              <th class="pb-2">Watch Time</th>
              <th class="pb-2">Peak</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="day in dailyAnalytics" :key="day.date" class="border-b border-gray-700/30">
              <td class="py-2 text-gray-300">{{ day.date }}</td>
              <td class="py-2 text-white">{{ day.views }}</td>
              <td class="py-2 text-white">{{ day.unique_viewers }}</td>
              <td class="py-2 text-white">{{ formatDuration(day.total_watch_time_seconds) }}</td>
              <td class="py-2 text-white">{{ day.peak_concurrent_viewers }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="loading" class="text-center py-8">
      <Loader2 class="w-8 h-8 animate-spin text-gray-500 mx-auto" />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { route } from '@/Composables/useRoute'
import { useApiFetch } from '@/Composables/useApiFetch'
import { Loader2 } from 'lucide-vue-next'
import StatCard from '@/Components/StatCard.vue'

const props = defineProps({
  channel: { type: Object, required: true },
})

const { apiFetch } = useApiFetch()
const loading = ref(false)

const stats = ref({
  total_views: 0,
  total_subscribers: 0,
  peak_viewers: 0,
  total_watch_time: 0,
})

const dailyAnalytics = ref([])

// Placeholder timeline — 24 zero-bars until real data arrives
const timeline = ref(Array(24).fill(0))
const maxTimeline = computed(() => Math.max(...timeline.value, 1))

const deviceData = ref([
  { name: 'Mobile', value: 45, color: 'bg-blue-500' },
  { name: 'Desktop', value: 35, color: 'bg-purple-500' },
  { name: 'Tablet', value: 15, color: 'bg-green-500' },
  { name: 'TV', value: 5, color: 'bg-red-500' },
])

const qualityData = ref([
  { name: 'HD (720p)', value: 50, color: 'bg-green-500' },
  { name: 'FHD (1080p)', value: 30, color: 'bg-blue-500' },
  { name: 'SD (480p)', value: 20, color: 'bg-yellow-500' },
])

const fetchStats = async () => {
  loading.value = true
  try {
    const res = await apiFetch(route('admin.admin.channels.analytics', props.channel.channel_slug))
    const json = await res.json()
    if (json?.analytics) {
      stats.value = {
        total_views: json.analytics.total_views || 0,
        total_subscribers: json.analytics.total_subscribers || 0,
        peak_viewers: json.analytics.peak_viewers || 0,
        total_watch_time: json.analytics.total_watch_time || 0,
      }
      if (json.analytics.daily_data) {
        const days = Object.values(json.analytics.daily_data)
        dailyAnalytics.value = days.slice(-30)
        // Build 24-slot timeline from last 24 daily entries
        timeline.value = days.slice(-24).map(d => d.views || 0)
        if (timeline.value.length < 24) {
          timeline.value = [...Array(24 - timeline.value.length).fill(0), ...timeline.value]
        }
      }
    }
  } finally {
    loading.value = false
  }
}

function formatDuration(seconds) {
  if (!seconds) return '—'
  const h = Math.floor(seconds / 3600)
  const m = Math.floor((seconds % 3600) / 60)
  if (h > 0) return `${h}h ${m}m`
  return `${m}m`
}

onMounted(fetchStats)
</script>
