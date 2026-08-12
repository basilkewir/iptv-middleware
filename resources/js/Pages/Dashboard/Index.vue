<template>
  <AppLayout>
    <div class="mb-6 sm:mb-8">
      <h2 class="text-xl sm:text-2xl font-bold text-white mb-1">
        Welcome back, {{ $page.props.auth?.user?.first_name || $page.props.auth?.user?.username || 'Guest' }}!
      </h2>
      <p class="text-gray-400 text-sm sm:text-base">Here's what's happening with your account today.</p>
    </div>

    <!-- Stats Cards -->
    <div v-if="$page.props.auth?.user?.is_admin" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6 sm:mb-8">
      <div v-for="stat in adminStats" :key="stat.label" class="bg-gray-900 rounded-xl p-4 sm:p-5 border border-gray-800">
        <div class="flex items-center justify-between mb-3">
          <span class="text-gray-400 text-xs sm:text-sm">{{ stat.label }}</span>
          <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg flex items-center justify-center" :class="stat.bgClass">
            <svg class="w-4 h-4 sm:w-5 sm:h-5" :class="stat.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="stat.icon" />
            </svg>
          </div>
        </div>
        <p class="text-xl sm:text-2xl font-bold text-white">{{ stat.value }}</p>
        <p class="text-xs text-gray-500 mt-1">{{ stat.change }}</p>
      </div>
    </div>

    <!-- Active Subscription -->
    <div v-if="activeSubscription" class="mb-6 sm:mb-8 bg-gradient-to-r from-purple-900/40 to-blue-900/40 rounded-xl sm:rounded-2xl p-4 sm:p-6 border border-purple-500/20">
      <div class="flex items-center justify-between flex-wrap gap-3 sm:gap-4">
        <div>
          <div class="flex items-center gap-1.5 sm:gap-2 mb-2">
            <span class="px-2 py-1 bg-purple-500/20 text-purple-300 text-xs font-medium rounded-full">
              {{ activeSubscription.plan.name }}
            </span>
            <span v-if="activeSubscription.is_trial" class="px-2 py-1 bg-yellow-500/20 text-yellow-300 text-xs font-medium rounded-full">
              Trial
            </span>
          </div>
          <h3 class="text-base sm:text-lg font-semibold text-white mb-1">{{ activeSubscription.plan.name }} Plan</h3>
          <p class="text-gray-400 text-sm">
            Expires {{ formatDate(activeSubscription.expires_at) }}
          </p>
        </div>
        <Link
          :href="route('subscriptions.index')"
          class="px-4 sm:px-5 py-2 sm:py-2.5 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition-colors tv-touch-target tv-focusable"
        >
          Manage Subscription
        </Link>
      </div>
    </div>

    <!-- No Subscription -->
    <div v-else class="mb-6 sm:mb-8 bg-gray-900 rounded-xl sm:rounded-2xl p-4 sm:p-6 border border-gray-800">
      <div class="flex items-center justify-between flex-wrap gap-3 sm:gap-4">
        <div>
          <h3 class="text-base sm:text-lg font-semibold text-white mb-1">No Active Subscription</h3>
          <p class="text-gray-400 text-sm">Subscribe to access live TV, movies, and series.</p>
        </div>
        <Link
          :href="route('subscriptions.index')"
          class="px-4 sm:px-5 py-2 sm:py-2.5 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition-colors tv-touch-target tv-focusable"
        >
          View Plans
        </Link>
      </div>
    </div>

    <!-- Quick Access -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4 mb-6 sm:mb-8">
      <Link
        :href="route('channels.index')"
        class="bg-gray-900 hover:bg-gray-800 rounded-xl p-4 sm:p-5 border border-gray-800 transition-all group tv-focusable"
      >
        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-blue-500/10 rounded-xl flex items-center justify-center mb-3 group-hover:bg-blue-500/20 transition-colors">
          <svg class="w-5 h-5 sm:w-6 sm:h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
          </svg>
        </div>
        <h3 class="text-white font-medium mb-1 text-sm sm:text-base">Live TV</h3>
        <p class="text-gray-500 text-xs">{{ channelCount }} channels</p>
      </Link>

      <Link
        :href="route('vod.index')"
        class="bg-gray-900 hover:bg-gray-800 rounded-xl p-4 sm:p-5 border border-gray-800 transition-all group tv-focusable"
      >
        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-purple-500/10 rounded-xl flex items-center justify-center mb-3 group-hover:bg-purple-500/20 transition-colors">
          <svg class="w-5 h-5 sm:w-6 sm:h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
          </svg>
        </div>
        <h3 class="text-white font-medium mb-1 text-sm sm:text-base">Movies</h3>
        <p class="text-gray-500 text-xs">{{ vodCount }} titles</p>
      </Link>

      <Link
        :href="route('series.index')"
        class="bg-gray-900 hover:bg-gray-800 rounded-xl p-4 sm:p-5 border border-gray-800 transition-all group tv-focusable"
      >
        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-green-500/10 rounded-xl flex items-center justify-center mb-3 group-hover:bg-green-500/20 transition-colors">
          <svg class="w-5 h-5 sm:w-6 sm:h-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
          </svg>
        </div>
        <h3 class="text-white font-medium mb-1 text-sm sm:text-base">Series</h3>
        <p class="text-gray-500 text-xs">{{ seriesCount }} shows</p>
      </Link>

      <Link
        :href="route('epg.index')"
        class="bg-gray-900 hover:bg-gray-800 rounded-xl p-4 sm:p-5 border border-gray-800 transition-all group tv-focusable"
      >
        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-yellow-500/10 rounded-xl flex items-center justify-center mb-3 group-hover:bg-yellow-500/20 transition-colors">
          <svg class="w-5 h-5 sm:w-6 sm:h-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
        </div>
        <h3 class="text-white font-medium mb-1 text-sm sm:text-base">TV Guide</h3>
        <p class="text-gray-500 text-xs">Browse EPG</p>
      </Link>
    </div>

    <!-- Featured Content Slider -->
    <div class="mb-6 sm:mb-8">
      <div class="flex items-center justify-between mb-3 sm:mb-4">
        <h3 class="text-base sm:text-lg font-semibold text-white">Featured</h3>
      </div>
      <div class="flex gap-3 sm:gap-4 overflow-x-auto pb-4 scrollbar-thin">
        <div
          v-for="item in featured"
          :key="item.id"
          class="flex-shrink-0 w-48 sm:w-56 md:w-64 bg-gray-900 rounded-xl overflow-hidden border border-gray-800 hover:border-gray-700 transition-all cursor-pointer group tv-focusable"
          @click="playContent(item)"
        >
          <div class="aspect-video bg-gray-800 relative overflow-hidden">
            <img
              v-if="item.poster_path"
              :src="item.poster_path"
              :alt="item.name"
              class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
            />
            <div v-else class="w-full h-full flex items-center justify-center">
              <svg class="w-8 h-8 sm:w-10 sm:h-10 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
              </svg>
            </div>
            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors flex items-center justify-center">
              <div class="w-10 h-10 sm:w-12 sm:h-12 bg-white/90 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-gray-900 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M8 5v14l11-7z" />
                </svg>
              </div>
            </div>
          </div>
          <div class="p-3">
            <h4 class="text-white font-medium text-sm truncate">{{ item.name }}</h4>
            <p class="text-gray-500 text-xs mt-1">{{ item.category || 'Featured' }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Recently Watched -->
    <div v-if="recentlyWatched.length" class="mb-6 sm:mb-8">
      <div class="flex items-center justify-between mb-3 sm:mb-4">
        <h3 class="text-base sm:text-lg font-semibold text-white">Recently Watched</h3>
        <Link :href="route('watch.history')" class="text-sm text-purple-400 hover:text-purple-300 transition-colors">
          View all
        </Link>
      </div>
      <div class="flex gap-3 sm:gap-4 overflow-x-auto pb-4 scrollbar-thin">
        <div
          v-for="item in recentlyWatched"
          :key="item.id"
          class="flex-shrink-0 w-40 sm:w-48 bg-gray-900 rounded-xl overflow-hidden border border-gray-800 hover:border-gray-700 transition-all cursor-pointer group tv-focusable"
        >
          <div class="aspect-video bg-gray-800 relative overflow-hidden">
            <img
              v-if="item.thumbnail"
              :src="item.thumbnail"
              :alt="item.name"
              class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
            />
            <div v-else class="w-full h-full flex items-center justify-center">
              <svg class="w-6 h-6 sm:w-8 sm:h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
              </svg>
            </div>
            <!-- Progress bar -->
            <div v-if="item.progress" class="absolute bottom-0 left-0 right-0 h-1 bg-gray-700">
              <div class="h-full bg-purple-500" :style="{ width: item.progress + '%' }" />
            </div>
          </div>
          <div class="p-3">
            <h4 class="text-white font-medium text-sm truncate">{{ item.name }}</h4>
            <p class="text-gray-500 text-xs mt-1">{{ item.duration || '' }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Continue Watching -->
    <div v-if="continueWatching.length" class="mb-6 sm:mb-8">
      <div class="flex items-center justify-between mb-3 sm:mb-4">
        <h3 class="text-base sm:text-lg font-semibold text-white">Continue Watching</h3>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
        <div
          v-for="item in continueWatching"
          :key="item.id"
          class="bg-gray-900 rounded-xl overflow-hidden border border-gray-800 hover:border-gray-700 transition-all cursor-pointer flex group tv-focusable"
        >
          <div class="w-24 sm:w-32 flex-shrink-0 bg-gray-800 relative overflow-hidden">
            <img
              v-if="item.thumbnail"
              :src="item.thumbnail"
              :alt="item.name"
              class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
            />
            <div v-else class="w-full h-full flex items-center justify-center">
              <svg class="w-6 h-6 sm:w-8 sm:h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
              </svg>
            </div>
          </div>
          <div class="p-3 sm:p-4 flex-1">
            <h4 class="text-white font-medium text-sm mb-1">{{ item.name }}</h4>
            <p class="text-gray-500 text-xs mb-2 sm:mb-3">S{{ item.season }}E{{ item.episode }} · {{ item.episode_name }}</p>
            <div class="w-full h-1.5 bg-gray-800 rounded-full overflow-hidden">
              <div class="h-full bg-purple-500 rounded-full" :style="{ width: item.progress + '%' }" />
            </div>
            <p class="text-gray-500 text-xs mt-1">{{ item.time_left }} left</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  activeSubscription: Object,
  recentlyWatched: { type: Array, default: () => [] },
  continueWatching: { type: Array, default: () => [] },
  featured: { type: Array, default: () => [] },
  channelCount: { type: Number, default: 0 },
  vodCount: { type: Number, default: 0 },
  seriesCount: { type: Number, default: 0 },
})

const adminStats = computed(() => [
  {
    label: 'Active Users',
    value: '—',
    change: 'Currently online',
    icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z',
    bgClass: 'bg-blue-500/10',
    iconClass: 'text-blue-400',
  },
  {
    label: 'Total Channels',
    value: props.channelCount || '—',
    change: 'Live streams available',
    icon: 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z',
    bgClass: 'bg-purple-500/10',
    iconClass: 'text-purple-400',
  },
  {
    label: 'VOD Library',
    value: props.vodCount || '—',
    change: 'Movies & series',
    icon: 'M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z',
    bgClass: 'bg-green-500/10',
    iconClass: 'text-green-400',
  },
  {
    label: 'Subscriptions',
    value: '—',
    change: 'Active plans',
    icon: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
    bgClass: 'bg-yellow-500/10',
    iconClass: 'text-yellow-400',
  },
])

const formatDate = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

const playContent = (item) => {
  if (item.type === 'channel') {
    router.visit('/channels/' + item.id)
  } else {
    router.visit('/vod/' + item.id)
  }
}
</script>
