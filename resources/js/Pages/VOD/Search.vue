<script setup>
import { ref, watch, onMounted } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { debounce } from 'lodash'
import { route } from '@/Composables/useRoute'
import AppLayout from '@/Layouts/AppLayout.vue'
import ContentCard from '@/Components/VOD/ContentCard.vue'

const props = defineProps({
  results: Object,
  recentSearches: Array,
  filters: Object,
})

const searchInput = ref(props.filters?.q || '')
const selectedType = ref(props.filters?.type || '')
const selectedGenre = ref(props.filters?.genre || '')
const genres = ref([])
const isSearching = ref(false)
const hasSearched = ref(!!props.filters?.q)

const types = [
  { value: '', label: 'All Types' },
  { value: 'movie', label: 'Movies' },
  { value: 'series', label: 'Series' },
  { value: 'documentary', label: 'Documentaries' },
]

const performSearch = debounce(() => {
  if (!searchInput.value.trim()) {
    hasSearched.value = false
    return
  }
  isSearching.value = true
  hasSearched.value = true
  router.get(route('vod.search'), {
    q: searchInput.value.trim(),
    type: selectedType.value,
    genre: selectedGenre.value,
  }, {
    preserveState: true,
    replace: true,
    onFinish: () => { isSearching.value = false }
  })
}, 400)

watch([selectedType, selectedGenre], () => {
  if (hasSearched.value) performSearch()
})

const searchRecent = (term) => {
  searchInput.value = term
  performSearch()
}

const clearRecent = (term) => {
  router.delete(route('vod.search.recent.destroy', { term }), { preserveState: true })
}

const showContent = (item) => {
  router.visit(route('vod.show', item.slug))
}

onMounted(async () => {
  try {
    const res = await fetch(route('vod.genres'))
    const data = await res.json()
    genres.value = data
  } catch (e) {}
})
</script>

<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="mb-8">
        <div class="relative max-w-2xl">
          <input
            v-model="searchInput"
            @input="performSearch"
            type="text"
            placeholder="Search movies, series, documentaries..."
            class="w-full bg-gray-800 border border-gray-700 rounded-xl px-5 py-4 pl-12 text-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
          />
          <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <button v-if="searchInput" @click="searchInput = ''; hasSearched = false" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="flex gap-4 mt-4">
          <select v-model="selectedType" class="bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
          </select>
          <select v-model="selectedGenre" class="bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            <option value="">All Genres</option>
            <option v-for="g in genres" :key="g.id" :value="g.slug">{{ g.name }}</option>
          </select>
        </div>
      </div>

      <div v-if="!hasSearched && recentSearches?.length" class="mb-8">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-gray-300">Recent Searches</h2>
          <Link :href="route('vod.search.recent.destroyAll')" method="delete" as="button" class="text-sm text-gray-400 hover:text-white transition">Clear All</Link>
        </div>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="term in recentSearches"
            :key="term"
            @click="searchRecent(term)"
            class="flex items-center gap-2 bg-gray-800 hover:bg-gray-700 rounded-full px-4 py-2 text-sm transition"
          >
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            {{ term }}
            <span @click.stop="clearRecent(term)" class="text-gray-500 hover:text-white ml-1">&times;</span>
          </button>
        </div>
      </div>

      <div v-if="isSearching" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
        <div v-for="i in 12" :key="i" class="bg-gray-800 rounded-lg overflow-hidden animate-pulse">
          <div class="aspect-[2/3] bg-gray-700" />
          <div class="p-3 space-y-2">
            <div class="h-4 bg-gray-700 rounded w-3/4" />
            <div class="h-3 bg-gray-700 rounded w-1/2" />
          </div>
        </div>
      </div>

      <div v-else-if="hasSearched && results?.data?.length">
        <p class="text-gray-400 mb-4">{{ results.total }} results found</p>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
          <ContentCard
            v-for="item in results.data"
            :key="item.id"
            :item="item"
            @click="showContent(item)"
          />
        </div>
      </div>

      <div v-else-if="hasSearched" class="text-center py-20 text-gray-400">
        <svg class="w-16 h-16 mx-auto mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <p class="text-xl">No results found for "{{ searchInput }}"</p>
        <p class="text-sm mt-2">Try different keywords or filters</p>
      </div>

      <div v-else class="text-center py-20 text-gray-400">
        <p class="text-xl">Start typing to search</p>
      </div>
    </div>
  </AppLayout>
</template>
