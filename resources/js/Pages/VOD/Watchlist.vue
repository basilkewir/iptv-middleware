<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AppLayout from '@/Layouts/AppLayout.vue'
import ContentCard from '@/Components/VOD/ContentCard.vue'

const props = defineProps({
  items: Array,
  filters: Object,
})

const sortBy = ref(props.filters?.sort || 'added')
const viewMode = ref(props.filters?.view || 'grid')

const sortOptions = [
  { value: 'added', label: 'Recently Added' },
  { value: 'title', label: 'Title A-Z' },
  { value: 'year', label: 'Year' },
  { value: 'rating', label: 'Rating' },
]

const sortedItems = computed(() => {
  if (!props.items) return []
  const sorted = [...props.items]
  switch (sortBy.value) {
    case 'title':
      return sorted.sort((a, b) => a.title.localeCompare(b.title))
    case 'year':
      return sorted.sort((a, b) => b.year - a.year)
    case 'rating':
      return sorted.sort((a, b) => (b.rating || 0) - (a.rating || 0))
    case 'added':
    default:
      return sorted.sort((a, b) => new Date(b.pivot?.created_at) - new Date(a.pivot?.created_at))
  }
})

const removeFromWatchlist = (item) => {
  router.delete(route('vod.watchlist.destroy', item.slug), { preserveState: true })
}

const markAsWatched = (item) => {
  router.post(route('vod.watchlist.watched', item.slug), {}, { preserveState: true })
}

const showContent = (item) => {
  router.visit(route('vod.show', item.slug))
}
</script>

<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="flex items-center justify-between mb-8">
        <h1 class="text-3xl font-bold">My Watchlist</h1>
        <span class="text-gray-400 text-sm">{{ items?.length || 0 }} titles</span>
      </div>

      <div v-if="items?.length" class="mb-6 flex flex-wrap items-center gap-4">
        <select v-model="sortBy" class="bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option v-for="s in sortOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
        </select>

        <div class="flex border border-gray-700 rounded-lg overflow-hidden">
          <button @click="viewMode = 'grid'" :class="['px-3 py-2', viewMode === 'grid' ? 'bg-indigo-600' : 'bg-gray-800']">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
          </button>
          <button @click="viewMode = 'list'" :class="['px-3 py-2', viewMode === 'list' ? 'bg-indigo-600' : 'bg-gray-800']">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" /></svg>
          </button>
        </div>
      </div>

      <div v-if="sortedItems.length" :class="[
        viewMode === 'grid'
          ? 'grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4'
          : 'space-y-4'
      ]">
        <div v-for="item in sortedItems" :key="item.id" :class="[viewMode === 'list' && 'flex gap-4 bg-gray-800 rounded-lg overflow-hidden']">
          <ContentCard
            v-if="viewMode === 'grid'"
            :item="item"
            @click="showContent(item)"
          />

          <template v-else>
            <img :src="item.poster" :alt="item.title" class="w-24 h-36 object-cover flex-shrink-0" />
            <div class="flex-1 p-4 flex flex-col justify-between">
              <div>
                <h3 class="font-semibold">{{ item.title }}</h3>
                <div class="flex items-center gap-2 text-sm text-gray-400 mt-1">
                  <span v-if="item.year">{{ item.year }}</span>
                  <span v-if="item.rating" class="text-yellow-400">&#9733; {{ item.rating }}</span>
                  <span v-if="item.type" class="px-2 py-0.5 bg-gray-700 rounded text-xs">{{ item.type }}</span>
                </div>
                <p v-if="item.description" class="text-sm text-gray-400 mt-2 line-clamp-2">{{ item.description }}</p>
              </div>
              <div class="flex gap-2 mt-3">
                <button @click="showContent(item)" class="text-sm text-indigo-400 hover:text-indigo-300 transition">View</button>
                <button @click="markAsWatched(item)" class="text-sm text-gray-400 hover:text-white transition">Mark Watched</button>
                <button @click="removeFromWatchlist(item)" class="text-sm text-red-400 hover:text-red-300 transition">Remove</button>
              </div>
            </div>
          </template>
        </div>
      </div>

      <div v-else class="text-center py-20 text-gray-400">
        <svg class="w-16 h-16 mx-auto mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
        </svg>
        <p class="text-xl mb-2">Your watchlist is empty</p>
        <p class="text-sm mb-6">Browse and add content to your list</p>
        <Link :href="route('vod.browse')" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 px-6 py-3 rounded-lg font-medium transition">
          Browse Content
        </Link>
      </div>
    </div>
  </AppLayout>
</template>
