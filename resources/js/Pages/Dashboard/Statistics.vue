<template>
  <div class="min-h-screen bg-gray-950">
    <header class="bg-gray-900/80 backdrop-blur-sm border-b border-gray-800">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          <div class="flex items-center gap-3">
            <Link :href="route('dashboard')" class="text-gray-400 hover:text-white transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
            </Link>
            <h1 class="text-lg font-bold text-white">Statistics</h1>
          </div>

          <div class="flex items-center gap-3">
            <select
              v-model="period"
              class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white focus:outline-none focus:ring-2 focus:ring-purple-500"
            >
              <option value="24h">Last 24 hours</option>
              <option value="7d">Last 7 days</option>
              <option value="30d">Last 30 days</option>
              <option value="90d">Last 90 days</option>
            </select>
          </div>
        </div>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Overview Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div
          v-for="stat in overviewStats"
          :key="stat.label"
          class="bg-gray-900 rounded-xl p-5 border border-gray-800"
        >
          <div class="flex items-center justify-between mb-3">
            <span class="text-gray-400 text-sm">{{ stat.label }}</span>
            <div class="w-10 h-10 rounded-lg flex items-center justify-center" :class="stat.bgClass">
              <svg class="w-5 h-5" :class="stat.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="stat.icon" />
              </svg>
            </div>
          </div>
          <p class="text-2xl font-bold text-white">{{ stat.value }}</p>
          <p class="text-xs mt-1" :class="stat.trendClass">{{ stat.trend }}</p>
        </div>
      </div>

      <!-- Charts Row -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Active Users Chart -->
        <div class="bg-gray-900 rounded-xl p-6 border border-gray-800">
          <h3 class="text-white font-semibold mb-4">Active Users</h3>
          <div class="h-64 flex items-end gap-1">
            <div
              v-for="(point, i) in activeUsersData"
              :key="i"
              class="flex-1 bg-purple-500/80 rounded-t hover:bg-purple-400 transition-colors relative group"
              :style="{ height: point.height + '%' }"
            >
              <div class="absolute -top-8 left-1/2 -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
                {{ point.value }} users
              </div>
            </div>
          </div>
          <div class="flex justify-between mt-2 text-xs text-gray-500">
            <span>{{ activeUsersData[0]?.label }}</span>
            <span>{{ activeUsersData[activeUsersData.length - 1]?.label }}</span>
          </div>
        </div>

        <!-- Bandwidth Usage -->
        <div class="bg-gray-900 rounded-xl p-6 border border-gray-800">
          <h3 class="text-white font-semibold mb-4">Bandwidth Usage</h3>
          <div class="h-64 flex items-end gap-1">
            <div
              v-for="(point, i) in bandwidthData"
              :key="i"
              class="flex-1 bg-blue-500/80 rounded-t hover:bg-blue-400 transition-colors relative group"
              :style="{ height: point.height + '%' }"
            >
              <div class="absolute -top-8 left-1/2 -translate-x-1/2 bg-gray-800 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
                {{ point.value }} Mbps
              </div>
            </div>
          </div>
          <div class="flex justify-between mt-2 text-xs text-gray-500">
            <span>{{ bandwidthData[0]?.label }}</span>
            <span>{{ bandwidthData[bandwidthData.length - 1]?.label }}</span>
          </div>
        </div>
      </div>

      <!-- Bottom Row -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Top Channels -->
        <div class="bg-gray-900 rounded-xl p-6 border border-gray-800">
          <h3 class="text-white font-semibold mb-4">Top Channels</h3>
          <div class="space-y-3">
            <div
              v-for="(channel, i) in topChannels"
              :key="channel.id"
              class="flex items-center gap-3 p-3 bg-gray-800/50 rounded-lg"
            >
              <span class="text-gray-500 text-sm font-mono w-5">{{ i + 1 }}</span>
              <div class="flex-1 min-w-0">
                <p class="text-white text-sm truncate">{{ channel.name }}</p>
                <p class="text-gray-500 text-xs">{{ channel.viewers }} viewers</p>
              </div>
              <div class="w-16 h-1.5 bg-gray-700 rounded-full overflow-hidden">
                <div
                  class="h-full bg-purple-500 rounded-full"
                  :style="{ width: (channel.viewers / topChannels[0].viewers * 100) + '%' }"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Top VOD -->
        <div class="bg-gray-900 rounded-xl p-6 border border-gray-800">
          <h3 class="text-white font-semibold mb-4">Top VOD Content</h3>
          <div class="space-y-3">
            <div
              v-for="(item, i) in topVod"
              :key="item.id"
              class="flex items-center gap-3 p-3 bg-gray-800/50 rounded-lg"
            >
              <span class="text-gray-500 text-sm font-mono w-5">{{ i + 1 }}</span>
              <div class="flex-1 min-w-0">
                <p class="text-white text-sm truncate">{{ item.name }}</p>
                <p class="text-gray-500 text-xs">{{ item.views }} views</p>
              </div>
              <div class="w-16 h-1.5 bg-gray-700 rounded-full overflow-hidden">
                <div
                  class="h-full bg-green-500 rounded-full"
                  :style="{ width: (item.views / topVod[0].views * 100) + '%' }"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-gray-900 rounded-xl p-6 border border-gray-800">
          <h3 class="text-white font-semibold mb-4">Recent Activity</h3>
          <div class="space-y-3">
            <div
              v-for="activity in recentActivity"
              :key="activity.id"
              class="flex items-start gap-3 p-3 bg-gray-800/50 rounded-lg"
            >
              <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0" :class="activity.bgClass">
                <svg class="w-4 h-4" :class="activity.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="activity.icon" />
                </svg>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-white text-sm">{{ activity.message }}</p>
                <p class="text-gray-500 text-xs mt-1">{{ activity.time }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'

defineProps({
  overviewStats: { type: Array, default: () => [] },
  activeUsersData: { type: Array, default: () => [] },
  bandwidthData: { type: Array, default: () => [] },
  topChannels: { type: Array, default: () => [] },
  topVod: { type: Array, default: () => [] },
  recentActivity: { type: Array, default: () => [] },
})

const period = ref('7d')
</script>
