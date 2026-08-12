<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
      <!-- Search & Filters Bar -->
      <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 mb-4 sm:mb-6">
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
        <select
          v-model="selectedCategory"
          class="px-4 py-2.5 bg-gray-900 border border-gray-800 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-purple-500 tv-focusable"
          @change="filterByCategory"
        >
          <option value="">All Categories</option>
          <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
        </select>
      </div>

      <div class="flex flex-col lg:flex-row gap-6 lg:gap-8">
        <!-- Category Sidebar -->
        <aside class="hidden lg:block w-56 flex-shrink-0">
          <div class="bg-gray-900 rounded-xl border border-gray-800 p-4 sticky top-24">
            <h3 class="text-sm font-semibold text-white mb-3">Categories</h3>
            <ul class="space-y-1">
              <li>
                <button
                  @click="selectedCategory = ''; fetchChannels()"
                  class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors tv-focusable"
                  :class="selectedCategory === '' ? 'bg-purple-600/20 text-purple-400' : 'text-gray-400 hover:text-white hover:bg-gray-800'"
                >
                  All Channels
                </button>
              </li>
              <li v-for="cat in categories" :key="cat.id">
                <button
                  @click="selectedCategory = cat.id; fetchChannels()"
                  class="w-full text-left px-3 py-2 rounded-lg text-sm transition-colors tv-focusable"
                  :class="selectedCategory === cat.id ? 'bg-purple-600/20 text-purple-400' : 'text-gray-400 hover:text-white hover:bg-gray-800'"
                >
                  {{ cat.name }}
                  <span class="text-gray-600 ml-1">{{ cat.channels_count }}</span>
                </button>
              </li>
            </ul>
          </div>
        </aside>

        <!-- Channel Grid -->
        <div class="flex-1">
          <!-- Loading State -->
          <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
            <div v-for="i in 8" :key="i" class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden animate-pulse">
              <div class="aspect-video bg-gray-800" />
              <div class="p-3 space-y-2">
                <div class="h-4 bg-gray-800 rounded w-3/4" />
                <div class="h-3 bg-gray-800 rounded w-1/2" />
              </div>
            </div>
          </div>

          <!-- Empty State -->
          <div v-else-if="channels.length === 0" class="text-center py-12 sm:py-16">
            <svg class="w-12 h-12 sm:w-16 sm:h-16 text-gray-700 mx-auto mb-3 sm:mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
            </svg>
            <h3 class="text-lg font-medium text-white mb-1">No channels found</h3>
            <p class="text-gray-500 text-sm">Try adjusting your search or filters.</p>
          </div>

          <!-- Channel Cards -->
          <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
            <Link
              v-for="channel in channels"
              :key="channel.id"
              :href="route('channels.show', channel.id)"
              class="bg-gray-900 rounded-xl border border-gray-800 hover:border-gray-700 overflow-hidden transition-all group cursor-pointer tv-focusable"
            >
              <div class="aspect-video bg-gray-800 relative overflow-hidden">
                <img
                  v-if="channel.logo"
                  :src="channel.logo"
                  :alt="channel.name"
                  class="w-full h-full object-contain p-2 group-hover:scale-105 transition-transform duration-300"
                />
                <div v-else class="w-full h-full flex items-center justify-center">
                  <svg class="w-8 h-8 sm:w-10 sm:h-10 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                  </svg>
                </div>
                <!-- Live badge -->
                <span v-if="channel.is_live" class="absolute top-1.5 left-1.5 px-1.5 py-0.5 bg-red-600 text-white text-[8px] sm:text-[10px] font-bold rounded uppercase">Live</span>
                <!-- Now playing indicator -->
                <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-1.5 sm:p-2 opacity-0 group-hover:opacity-100 transition-opacity">
                  <div class="flex items-center gap-1.5">
                    <div class="w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse" />
                    <span class="text-white text-[10px] sm:text-xs font-medium truncate">{{ channel.current_program || 'No program info' }}</span>
                  </div>
                </div>
              </div>
              <div class="p-2 sm:p-3">
                <h4 class="text-white font-medium text-sm truncate group-hover:text-purple-400 transition-colors">{{ channel.name }}</h4>
                <p class="text-gray-500 text-xs mt-1 truncate">{{ channel.category?.name || 'Uncategorized' }}</p>
              </div>
            </Link>
          </div>

          <!-- Pagination -->
          <div v-if="channels.length > 0 && totalPages > 1" class="mt-6 sm:mt-8 flex items-center justify-center gap-1 sm:gap-2 flex-wrap">
            <button
              @click="goToPage(currentPage - 1)"
              :disabled="currentPage === 1"
              class="px-2 sm:px-3 py-1.5 sm:py-2 rounded-lg text-sm font-medium transition-colors tv-focusable"
              :class="currentPage === 1 ? 'text-gray-600 cursor-not-allowed' : 'text-gray-400 hover:text-white hover:bg-gray-800'"
            >
              Previous
            </button>
            <template v-for="page in visiblePages" :key="page">
              <span v-if="page === '...'" class="px-2 sm:px-3 py-1.5 sm:py-2 text-gray-600 text-sm">...</span>
              <button
                v-else
                @click="goToPage(page)"
                class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg text-sm font-medium transition-colors tv-focusable"
                :class="page === currentPage ? 'bg-purple-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800'"
              >
                {{ page }}
              </button>
            </template>
            <button
              @click="goToPage(currentPage + 1)"
              :disabled="currentPage === totalPages"
              class="px-2 sm:px-3 py-1.5 sm:py-2 rounded-lg text-sm font-medium transition-colors tv-focusable"
              :class="currentPage === totalPages ? 'text-gray-600 cursor-not-allowed' : 'text-gray-400 hover:text-white hover:bg-gray-800'"
            >
              Next
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  channels: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, per_page: 20, total: 0 }) },
  filters: { type: Object, default: () => ({ search: '', category: '' }) },
})

const search = ref(props.filters.search || '')
const selectedCategory = ref(props.filters.category || '')
const loading = ref(false)
const currentPage = ref(props.pagination.current_page)
const totalPages = ref(props.pagination.last_page)

let searchTimeout = null

const debouncedSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    fetchChannels()
  }, 300)
}

const fetchChannels = () => {
  loading.value = true
  router.get(route('channels.index'), {
    search: search.value,
    category: selectedCategory.value,
    page: currentPage.value,
  }, {
    preserveState: true,
    onFinish: () => {
      loading.value = false
    },
  })
}

const filterByCategory = () => {
  currentPage.value = 1
  fetchChannels()
}

const goToPage = (page) => {
  if (page < 1 || page > totalPages.value) return
  currentPage.value = page
  fetchChannels()
}

const visiblePages = computed(() => {
  const pages = []
  const total = totalPages.value
  const current = currentPage.value
  if (total <= 7) {
    for (let i = 1; i <= total; i++) pages.push(i)
    return pages
  }
  pages.push(1)
  if (current > 3) pages.push('...')
  const start = Math.max(2, current - 1)
  const end = Math.min(total - 1, current + 1)
  for (let i = start; i <= end; i++) pages.push(i)
  if (current < total - 2) pages.push('...')
  pages.push(total)
  return pages
})

onMounted(() => {
  if (props.pagination.current_page) {
    currentPage.value = props.pagination.current_page
  }
})
</script>
