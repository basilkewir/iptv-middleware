<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">My Channels</h1>
        <p class="text-gray-400 text-sm mt-1">Create and manage your personal channels</p>
      </div>

      <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 mb-6">
        <div class="relative flex-1">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <input
            v-model="search"
            type="text"
            placeholder="Search channels..."
            class="w-full pl-10 pr-4 py-2.5 bg-gray-900 border border-gray-800 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent tv-focusable"
            @input="debouncedSearch"
          />
        </div>
        <Link
          :href="route('client.channels.create')"
          class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors tv-focusable"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          New Channel
        </Link>
      </div>

      <div v-if="channels.data.length === 0" class="text-center py-12 sm:py-16">
        <svg class="w-12 h-12 sm:w-16 sm:h-16 text-gray-700 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
        </svg>
        <p class="text-gray-500 text-lg">No channels yet</p>
        <p class="text-gray-600 text-sm mt-1">Create your first channel to get started</p>
        <Link
          :href="route('client.channels.create')"
          class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium transition-colors tv-focusable"
        >
          Create a Channel
        </Link>
      </div>

      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
        <Link
          v-for="channel in channels.data"
          :key="channel.id"
          :href="route('client.channels.show', channel.id)"
          class="group bg-gray-900 rounded-xl border border-gray-800 overflow-hidden hover:border-gray-700 transition-colors"
        >
          <div class="aspect-video bg-gray-800 relative overflow-hidden">
            <img
              v-if="channel.logo_url"
              :src="channel.logo_url"
              :alt="channel.channel_name"
              class="w-full h-full object-contain p-3 group-hover:scale-105 transition-transform duration-300"
            />
            <div v-else class="w-full h-full flex items-center justify-center">
              <svg class="w-10 h-10 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
              </svg>
            </div>
            <div v-if="channel.is_live" class="absolute top-2 left-2 px-2 py-0.5 bg-red-600 text-white text-xs font-semibold rounded">
              LIVE
            </div>
            <div v-if="channel.is_featured" class="absolute top-2 right-2 px-2 py-0.5 bg-yellow-500 text-black text-xs font-semibold rounded">
              Featured
            </div>
          </div>
          <div class="p-4">
            <h3 class="text-white font-semibold text-sm truncate">{{ channel.channel_name }}</h3>
            <p class="text-gray-500 text-xs mt-1 line-clamp-2">{{ channel.description }}</p>
            <div class="flex items-center gap-3 mt-3 text-xs text-gray-500">
              <span class="flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                {{ channel.subscriptions_count }}
              </span>
              <span class="flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                {{ channel.view_logs_count }}
              </span>
            </div>
          </div>
        </Link>
      </div>

      <div v-if="channels.last_page > 1" class="mt-6 sm:mt-8 flex items-center justify-center gap-1 sm:gap-2 flex-wrap">
        <button
          v-for="page in channels.last_page"
          :key="page"
          @click="goToPage(page)"
          :class="[
            'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors tv-focusable',
            page === channels.current_page
              ? 'bg-purple-600 text-white'
              : 'bg-gray-800 text-gray-400 hover:text-white hover:bg-gray-700'
          ]"
        >
          {{ page }}
        </button>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  channels: Object,
  search: String,
})

const search = ref(props.search || '')

const debouncedSearch = ref(null)

onMounted(() => {
  fetchChannels()
})

watch(search, () => {
  clearTimeout(debouncedSearch.value)
  debouncedSearch.value = setTimeout(() => {
    fetchChannels()
  }, 300)
})

const fetchChannels = () => {
  router.get(route('client.channels.index'), {
    search: search.value,
  }, {
    preserveState: true,
    preserveScroll: true,
  })
}

const goToPage = (page) => {
  router.get(route('client.channels.index'), {
    search: search.value,
    page,
  }, {
    preserveState: true,
    preserveScroll: true,
  })
}
</script>