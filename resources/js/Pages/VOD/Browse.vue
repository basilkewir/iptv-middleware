<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { debounce } from 'lodash'
import { route } from '@/Composables/useRoute'
import AppLayout from '@/Layouts/AppLayout.vue'
import LoadingSkeleton from '@/Components/VOD/LoadingSkeleton.vue'
import ContentCard from '@/Components/VOD/ContentCard.vue'

const props = defineProps({
  items: Object,
  categories: Array,
  genres: Array,
  filters: Object,
})

const selectedCategory = ref(props.filters?.category || 'all')
const selectedGenre = ref(props.filters?.genre || '')
const selectedYear = ref(props.filters?.year || '')
const selectedRating = ref(props.filters?.rating || '')
const sortBy = ref(props.filters?.sort || 'latest')
const viewMode = ref(props.filters?.view || 'grid')
const isLoading = ref(false)
const page = ref(1)
const observerRef = ref(null)
const showFilters = ref(false)

const years = computed(() => {
  const currentYear = new Date().getFullYear()
  return Array.from({ length: 30 }, (_, i) => currentYear - i)
})

const ratings = [
  { value: '', label: 'All Ratings' },
  { value: '9', label: '9+ Excellent' },
  { value: '8', label: '8+ Very Good' },
  { value: '7', label: '7+ Good' },
  { value: '6', label: '6+ Above Average' },
  { value: '5', label: '5+ Average' },
]

const sortOptions = [
  { value: 'latest', label: 'Latest' },
  { value: 'title', label: 'Title A-Z' },
  { value: 'title_desc', label: 'Title Z-A' },
  { value: 'year', label: 'Year (Newest)' },
  { value: 'year_asc', label: 'Year (Oldest)' },
  { value: 'rating', label: 'Rating (Highest)' },
]

const updateFilters = debounce(() => {
  router.get(route('vod.browse'), {
    category: selectedCategory.value,
    genre: selectedGenre.value,
    year: selectedYear.value,
    rating: selectedRating.value,
    sort: sortBy.value,
    view: viewMode.value,
    page: 1,
  }, { preserveState: true, replace: true })
}, 300)

watch([selectedCategory, selectedGenre, selectedYear, selectedRating, sortBy, viewMode], updateFilters)

const loadMore = () => {
  if (isLoading.value || !props.items?.next_page_url) return
  isLoading.value = true
  page.value++
  router.get(route('vod.browse'), {
    ...props.filters,
    page: page.value,
  }, {
    preserveState: true,
    replace: true,
    onFinish: () => { isLoading.value = false }
  })
}

onMounted(() => {
  observerRef.value = new IntersectionObserver((entries) => {
    if (entries[0].isIntersecting) loadMore()
  }, { threshold: 0.1 })

  const sentinel = document.getElementById('infinite-scroll-sentinel')
  if (sentinel) observerRef.value.observe(sentinel)
})

onUnmounted(() => {
  if (observerRef.value) observerRef.value.disconnect()
})

const showContent = (item) => {
  router.visit(route('vod.show', item.slug))
}
</script>

<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
      <div class="flex items-center justify-between mb-4 sm:mb-8">
        <h1 class="text-2xl sm:text-3xl font-bold">Browse</h1>
        <button
          @click="showFilters = !showFilters"
          class="lg:hidden px-4 py-2 bg-gray-800 rounded-lg text-sm font-medium tv-focusable"
        >
          Filters
        </button>
      </div>

      <div class="mb-4 sm:mb-8 space-y-3 sm:space-y-4" :class="{ 'hidden lg:block': !showFilters }">
        <div class="flex flex-wrap gap-2 sm:gap-3">
          <button
            @click="selectedCategory = 'all'"
            :class="[
              'px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg text-sm font-medium transition tv-focusable',
              selectedCategory === 'all' ? 'bg-indigo-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'
            ]"
          >
            All
          </button>
          <button
            v-for="cat in categories"
            :key="cat.slug"
            @click="selectedCategory = cat.slug"
            :class="[
              'px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg text-sm font-medium transition tv-focusable',
              selectedCategory === cat.slug ? 'bg-indigo-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'
            ]"
          >
            {{ cat.name }}
          </button>
        </div>

        <div class="flex flex-wrap gap-2 sm:gap-3">
          <select v-model="selectedGenre" class="bg-gray-800 border border-gray-700 rounded-lg px-3 sm:px-4 py-1.5 sm:py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent tv-focusable">
            <option value="">All Genres</option>
            <option v-for="g in genres" :key="g.id" :value="g.slug">{{ g.name }}</option>
          </select>

          <select v-model="selectedYear" class="bg-gray-800 border border-gray-700 rounded-lg px-3 sm:px-4 py-1.5 sm:py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent tv-focusable">
            <option value="">All Years</option>
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>

          <select v-model="selectedRating" class="bg-gray-800 border border-gray-700 rounded-lg px-3 sm:px-4 py-1.5 sm:py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent tv-focusable">
            <option v-for="r in ratings" :key="r.value" :value="r.value">{{ r.label }}</option>
          </select>

          <select v-model="sortBy" class="bg-gray-800 border border-gray-700 rounded-lg px-3 sm:px-4 py-1.5 sm:py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent tv-focusable">
            <option v-for="s in sortOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>

          <div class="flex border border-gray-700 rounded-lg overflow-hidden">
            <button @click="viewMode = 'grid'" :class="['px-2.5 sm:px-3 py-1.5 sm:py-2', viewMode === 'grid' ? 'bg-indigo-600' : 'bg-gray-800', 'tv-focusable']">
              <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
            </button>
            <button @click="viewMode = 'list'" :class="['px-2.5 sm:px-3 py-1.5 sm:py-2', viewMode === 'list' ? 'bg-indigo-600' : 'bg-gray-800', 'tv-focusable']">
              <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" /></svg>
            </button>
          </div>
        </div>
      </div>

      <LoadingSkeleton v-if="isLoading && page === 1" :count="12" :view="viewMode" />

      <div v-else-if="items?.data?.length" :class="[
        viewMode === 'grid'
          ? 'grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 sm:gap-4'
          : 'space-y-3 sm:space-y-4'
      ]">
        <ContentCard
          v-for="item in items.data"
          :key="item.id"
          :item="item"
          :view="viewMode"
          @click="showContent(item)"
        />
      </div>

      <div v-else class="text-center py-16 sm:py-20 text-gray-400">
        <p class="text-lg sm:text-xl">No content found matching your filters.</p>
      </div>

      <div id="infinite-scroll-sentinel" ref="observerRef" class="h-10" />

      <div v-if="isLoading && page > 1" class="flex justify-center py-8">
        <div class="w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin" />
      </div>
    </div>
  </AppLayout>
</template>
