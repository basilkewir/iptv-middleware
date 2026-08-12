<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-4 sm:space-y-6">
          <!-- Player -->
          <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
            <div class="aspect-video bg-black relative">
              <ChannelPlayer
                v-if="channel.stream_url"
                :src="channel.stream_url"
                :channel="channel"
              />
              <div v-else class="w-full h-full flex items-center justify-center">
                <div class="text-center">
                  <svg class="w-12 h-12 sm:w-16 sm:h-16 text-gray-700 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                  </svg>
                  <p class="text-gray-500 text-sm">Stream unavailable</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Channel Info -->
          <div class="bg-gray-900 rounded-xl border border-gray-800 p-4 sm:p-6">
            <div class="flex items-start gap-3 sm:gap-4">
              <div class="w-12 h-12 sm:w-16 sm:h-16 bg-gray-800 rounded-xl flex items-center justify-center flex-shrink-0 overflow-hidden">
                <img v-if="channel.logo" :src="channel.logo" :alt="channel.name" class="w-full h-full object-contain p-1" />
                <svg v-else class="w-6 h-6 sm:w-8 sm:h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
              </div>
              <div class="flex-1 min-w-0">
                <h2 class="text-lg sm:text-xl font-bold text-white mb-1">{{ channel.name }}</h2>
                <div class="flex items-center gap-2 sm:gap-3 mb-2 sm:mb-3 flex-wrap">
                  <span v-if="channel.category" class="px-2 py-0.5 bg-purple-500/20 text-purple-300 text-xs font-medium rounded-full">
                    {{ channel.category.name }}
                  </span>
                  <span v-if="channel.is_live" class="flex items-center gap-1.5 text-red-400 text-xs font-medium">
                    <div class="w-2 h-2 bg-red-500 rounded-full animate-pulse" />
                    Live Now
                  </span>
                </div>
                <p v-if="channel.description" class="text-gray-400 text-sm leading-relaxed">{{ channel.description }}</p>
              </div>
            </div>
          </div>

          <!-- EPG Section -->
          <div class="bg-gray-900 rounded-xl border border-gray-800 p-4 sm:p-6">
            <div class="flex items-center justify-between mb-3 sm:mb-4">
              <h3 class="text-base sm:text-lg font-semibold text-white">Today's Schedule</h3>
              <Link :href="route('channels.epg', channel.id)" class="text-sm text-purple-400 hover:text-purple-300 transition-colors">
                Full Guide
              </Link>
            </div>
            <div v-if="epgLoading" class="space-y-3">
              <div v-for="i in 4" :key="i" class="h-14 bg-gray-800 rounded-lg animate-pulse" />
            </div>
            <div v-else-if="epg.length === 0" class="text-center py-6 sm:py-8">
              <p class="text-gray-500 text-sm">No schedule available for today.</p>
            </div>
            <div v-else class="space-y-2">
              <div
                v-for="program in epg"
                :key="program.id"
                class="flex items-center gap-3 sm:gap-4 p-2 sm:p-3 rounded-lg transition-colors"
                :class="program.is_current ? 'bg-purple-600/10 border border-purple-500/20' : 'hover:bg-gray-800'"
              >
                <div class="w-16 flex-shrink-0 text-right">
                  <span class="text-sm font-mono" :class="program.is_current ? 'text-purple-400' : 'text-gray-400'">
                    {{ formatTime(program.start_time) }}
                  </span>
                </div>
                <div class="w-px h-6 sm:h-8 bg-gray-700 relative">
                  <div v-if="program.is_current" class="absolute top-1/2 -translate-y-1/2 -left-1 w-2 h-2 sm:w-2.5 sm:h-2.5 bg-purple-500 rounded-full" />
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium truncate" :class="program.is_current ? 'text-white' : 'text-gray-300'">
                    {{ program.title }}
                  </p>
                  <p v-if="program.description" class="text-xs text-gray-500 truncate mt-0.5">{{ program.description }}</p>
                </div>
                <span class="text-xs text-gray-600 flex-shrink-0">
                  {{ formatDuration(program.start_time, program.end_time) }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-4 sm:space-y-6">
          <!-- Quick Actions -->
          <div class="bg-gray-900 rounded-xl border border-gray-800 p-3 sm:p-4">
            <Link
              :href="route('channels.player', channel.id)"
              class="w-full flex items-center justify-center gap-2 px-4 py-2.5 sm:py-3 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors tv-touch-target tv-focusable"
            >
              <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Full Screen Player
            </Link>
          </div>

          <!-- Related Channels -->
          <div class="bg-gray-900 rounded-xl border border-gray-800 p-3 sm:p-4">
            <h3 class="text-sm font-semibold text-white mb-3">Related Channels</h3>
            <div v-if="related.length === 0" class="text-center py-4">
              <p class="text-gray-500 text-xs">No related channels</p>
            </div>
            <div v-else class="space-y-2">
              <Link
                v-for="related_channel in related"
                :key="related_channel.id"
                :href="route('channels.show', related_channel.id)"
                class="flex items-center gap-2 sm:gap-3 p-2 rounded-lg hover:bg-gray-800 transition-colors group tv-focusable"
              >
                <div class="w-8 h-8 sm:w-10 sm:h-10 bg-gray-800 rounded-lg flex items-center justify-center flex-shrink-0 overflow-hidden">
                  <img v-if="related_channel.logo" :src="related_channel.logo" :alt="related_channel.name" class="w-full h-full object-contain p-0.5" />
                  <svg v-else class="w-4 h-4 sm:w-5 sm:h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                  </svg>
                </div>
                <div class="min-w-0">
                  <p class="text-sm font-medium text-gray-300 truncate group-hover:text-white transition-colors">{{ related_channel.name }}</p>
                  <p class="text-xs text-gray-500 truncate">{{ related_channel.category?.name || '' }}</p>
                </div>
              </Link>
            </div>
          </div>

          <!-- Channel Details -->
          <div class="bg-gray-900 rounded-xl border border-gray-800 p-3 sm:p-4">
            <h3 class="text-sm font-semibold text-white mb-3">Details</h3>
            <dl class="space-y-3">
              <div class="flex justify-between">
                <dt class="text-xs text-gray-500">Category</dt>
                <dd class="text-xs text-gray-300">{{ channel.category?.name || 'N/A' }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-xs text-gray-500">Quality</dt>
                <dd class="text-xs text-gray-300">{{ channel.quality || 'HD' }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-xs text-gray-500">Status</dt>
                <dd class="text-xs" :class="channel.is_live ? 'text-green-400' : 'text-gray-500'">
                  {{ channel.is_live ? 'Online' : 'Offline' }}
                </dd>
              </div>
            </dl>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AppLayout from '@/Layouts/AppLayout.vue'
import ChannelPlayer from '@/Components/Player/ChannelPlayer.vue'

const props = defineProps({
  channel: { type: Object, required: true },
  epg: { type: Array, default: () => [] },
  related: { type: Array, default: () => [] },
  isFavorite: { type: Boolean, default: false },
})

const isFavorite = ref(props.isFavorite)
const epgLoading = ref(false)

const toggleFavorite = () => {
  router.post(route('channels.favorite', props.channel.id), {}, {
    preserveState: true,
    onSuccess: () => {
      isFavorite.value = !isFavorite.value
    },
  })
}

const formatTime = (dateStr) => {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  })
}

const formatDuration = (start, end) => {
  if (!start || !end) return ''
  const diff = new Date(end) - new Date(start)
  const mins = Math.round(diff / 60000)
  if (mins >= 60) {
    const h = Math.floor(mins / 60)
    const m = mins % 60
    return `${h}h ${m}m`
  }
  return `${mins}m`
}
</script>
