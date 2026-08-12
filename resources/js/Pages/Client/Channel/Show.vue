<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
      <!-- Back link -->
      <Link
        :href="route('client.channels.index')"
        class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-4"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Back to Channels
      </Link>

      <!-- Channel Header -->
      <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden mb-6">
        <div class="aspect-video bg-black relative">
          <img
            v-if="channel.banner_url"
            :src="channel.banner_url"
            :alt="channel.channel_name"
            class="w-full h-full object-cover"
          />
          <div v-else class="w-full h-full flex items-center justify-center bg-gray-900">
            <svg class="w-16 h-16 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
            </svg>
          </div>
          <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent"></div>
          <div class="absolute bottom-4 left-4 right-4">
            <div class="flex items-end gap-4">
              <img
                v-if="channel.logo_url"
                :src="channel.logo_url"
                :alt="channel.channel_name"
                class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl object-contain bg-black p-1 border border-gray-700"
              />
              <div class="flex-1">
                <h1 class="text-2xl font-bold text-white">{{ channel.channel_name }}</h1>
                <p class="text-gray-400 text-sm mt-1">{{ channel.description }}</p>
                <div class="flex items-center gap-3 mt-2 text-xs text-gray-500">
                  <span v-if="channel.is_live" class="flex items-center gap-1 text-red-400">
                    <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span> LIVE
                  </span>
                  <span v-if="channel.genre">{{ channel.genre }}</span>
                  <span v-if="channel.category">{{ channel.category }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Stream Player -->
          <div v-if="channel.stream_url" class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
            <div class="p-4 border-b border-gray-800 flex items-center justify-between">
              <h2 class="text-white font-semibold">Stream</h2>
              <span class="text-xs text-gray-500">{{ channel.stream_type }}</span>
            </div>
            <video
              v-if="channel.stream_type === 'hls'"
              :src="channel.stream_url"
              controls
              class="w-full aspect-video bg-black"
            />
            <div v-else class="p-6 text-center">
              <a
                :href="channel.stream_url"
                target="_blank"
                class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium transition-colors"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Open Stream
              </a>
            </div>
          </div>

          <!-- Playlist -->
          <div v-if="channel.playlistItems && channel.playlistItems.length > 0" class="bg-gray-900 rounded-xl border border-gray-800">
            <div class="p-4 border-b border-gray-800">
              <h2 class="text-white font-semibold">Playlist</h2>
            </div>
            <div class="divide-y divide-gray-800">
              <div
                v-for="item in channel.playlistItems"
                :key="item.id"
                class="p-4 flex items-center gap-4 hover:bg-gray-800/50 transition-colors"
              >
                <div class="w-10 h-10 bg-gray-800 rounded-lg flex items-center justify-center flex-shrink-0">
                  <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <h3 class="text-white text-sm font-medium truncate">{{ item.content_title }}</h3>
                  <p v-if="item.content_description" class="text-gray-500 text-xs mt-0.5 line-clamp-1">{{ item.content_description }}</p>
                </div>
                <span class="text-xs text-gray-500">{{ item.content_type }}</span>
              </div>
            </div>
          </div>

          <!-- Schedules -->
          <div v-if="channel.schedules && channel.schedules.length > 0" class="bg-gray-900 rounded-xl border border-gray-800">
            <div class="p-4 border-b border-gray-800">
              <h2 class="text-white font-semibold">Schedule</h2>
            </div>
            <div class="p-4">
              <div class="grid grid-cols-7 gap-2 text-center text-xs">
                <div class="text-gray-500 py-1">Sun</div>
                <div class="text-gray-500 py-1">Mon</div>
                <div class="text-gray-500 py-1">Tue</div>
                <div class="text-gray-500 py-1">Wed</div>
                <div class="text-gray-500 py-1">Thu</div>
                <div class="text-gray-500 py-1">Fri</div>
                <div class="text-gray-500 py-1">Sat</div>
              </div>
              <div class="mt-2 space-y-1">
                <div
                  v-for="schedule in channel.schedules"
                  :key="schedule.id"
                  class="flex items-center gap-2 text-xs"
                >
                  <span class="w-16 text-gray-500 flex-shrink-0">{{ schedule.start_time }}-{{ schedule.end_time }}</span>
                  <span class="text-gray-400 truncate">{{ getDayName(schedule.day_of_week) }}</span>
                  <span class="text-gray-600 ml-auto">{{ schedule.content_type }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Comments -->
          <div class="bg-gray-900 rounded-xl border border-gray-800">
            <div class="p-4 border-b border-gray-800">
              <h2 class="text-white font-semibold">Comments</h2>
            </div>
            <div class="p-4 space-y-4">
              <div v-if="channel.comments && channel.comments.length === 0" class="text-gray-500 text-sm text-center py-4">
                No comments yet
              </div>
              <div
                v-for="comment in channel.comments"
                :key="comment.id"
                class="flex gap-3"
              >
                <div class="w-8 h-8 bg-gray-800 rounded-full flex items-center justify-center flex-shrink-0">
                  <span class="text-gray-400 text-xs font-semibold">{{ comment.user?.name?.charAt(0)?.toUpperCase() || '?' }}</span>
                </div>
                <div class="flex-1">
                  <div class="flex items-center gap-2">
                    <span class="text-white text-sm font-medium">{{ comment.user?.name }}</span>
                    <span class="text-gray-600 text-xs">{{ formatDate(comment.created_at) }}</span>
                  </div>
                  <p class="text-gray-400 text-sm mt-1">{{ comment.comment }}</p>
                </div>
              </div>
              <form @submit.prevent="submitComment" class="flex gap-2 mt-4">
                <input
                  v-model="newComment"
                  type="text"
                  placeholder="Add a comment..."
                  class="flex-1 px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                />
                <button
                  type="submit"
                  class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition-colors"
                >
                  Post
                </button>
              </form>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Subscribe -->
          <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
            <h3 class="text-white font-semibold mb-3">Subscription</h3>
            <div v-if="isSubscribed" class="flex items-center gap-2 text-green-400 text-sm">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
              Subscribed
            </div>
            <div v-else class="space-y-3">
              <button
                @click="subscribe('free')"
                class="w-full px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition-colors"
              >
                Subscribe (Free)
              </button>
              <button
                @click="subscribe('premium')"
                class="w-full px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white text-sm font-medium rounded-lg transition-colors"
              >
                Subscribe (Premium)
              </button>
            </div>
          </div>

          <!-- Stats -->
          <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
            <h3 class="text-white font-semibold mb-3">Statistics</h3>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-500">Views</span>
                <span class="text-white">{{ channel.views }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Subscribers</span>
                <span class="text-white">{{ channel.subscriptions_count }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Playlist Items</span>
                <span class="text-white">{{ channel.playlistItems?.length }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Comments</span>
                <span class="text-white">{{ channel.comments?.length }}</span>
              </div>
            </div>
          </div>

          <!-- Channel Info -->
          <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
            <h3 class="text-white font-semibold mb-3">Channel Info</h3>
            <div class="space-y-2 text-sm">
              <div v-if="channel.channel_number" class="flex justify-between">
                <span class="text-gray-500">Channel Number</span>
                <span class="text-white">{{ channel.channel_number }}</span>
              </div>
              <div v-if="channel.language" class="flex justify-between">
                <span class="text-gray-500">Language</span>
                <span class="text-white">{{ channel.language }}</span>
              </div>
              <div v-if="channel.genre" class="flex justify-between">
                <span class="text-gray-500">Genre</span>
                <span class="text-white">{{ channel.genre }}</span>
              </div>
              <div v-if="channel.category" class="flex justify-between">
                <span class="text-gray-500">Category</span>
                <span class="text-white">{{ channel.category }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Stream Type</span>
                <span class="text-white">{{ channel.stream_type }}</span>
              </div>
              <div v-if="channel.output_resolution" class="flex justify-between">
                <span class="text-gray-500">Resolution</span>
                <span class="text-white">{{ channel.output_resolution }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Status</span>
                <span :class="channel.is_active ? 'text-green-400' : 'text-red-400'">
                  {{ channel.is_active ? 'Active' : 'Inactive' }}
                </span>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div v-if="isOwner" class="bg-gray-900 rounded-xl border border-gray-800 p-4">
            <h3 class="text-white font-semibold mb-3">Manage</h3>
            <div class="space-y-2">
              <Link
                :href="route('client.channels.edit', channel.id)"
                class="block w-full text-center px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white text-sm font-medium rounded-lg transition-colors"
              >
                Edit Channel
              </Link>
              <button
                @click="toggleStatus"
                class="w-full px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white text-sm font-medium rounded-lg transition-colors"
              >
                {{ channel.is_active ? 'Deactivate' : 'Activate' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  channel: Object,
})

const page = usePage()
const isSubscribed = ref(false)

const isOwner = computed(() => {
  return props.channel.user_id === page.props.auth?.user?.id
})

const subscribe = (type) => {
  router.post(route('client.channels.subscribe', props.channel.id), {
    subscription_type: type,
  }, {
    onSuccess: () => {
      isSubscribed.value = true
    },
  })
}

const toggleStatus = () => {
  router.post(route('client.channels.toggle-status', props.channel.id), {}, {
    onSuccess: () => {
      props.channel.is_active = !props.channel.is_active
    },
  })
}

const submitComment = () => {
  if (!newComment.value.trim()) return
  router.post(route('client.channels.comments.store', props.channel.id), {
    comment: newComment.value,
  }, {
    onSuccess: () => {
      newComment.value = ''
      router.reload({ only: ['channel'] })
    },
  })
}

const getDayName = (day) => {
  const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
  return days[day] || ''
}

const formatDate = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString()
}
</script>