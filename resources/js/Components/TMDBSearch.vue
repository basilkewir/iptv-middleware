<template>
  <div class="space-y-4">
    <div class="flex gap-3">
      <div class="flex-1 relative">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search TMDB..."
          class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500 pr-10"
          @input="debouncedSearch"
        />
        <Loader2 v-if="searching" class="absolute right-3 top-2.5 w-4 h-4 text-gray-400 animate-spin" />
        <Search v-else class="absolute right-3 top-2.5 w-4 h-4 text-gray-400" />
      </div>
      <div class="flex rounded-lg overflow-hidden border border-gray-600">
        <button
          @click="setType('movie')"
          class="px-4 py-2 text-sm font-medium transition"
          :class="contentType === 'movie' ? 'bg-indigo-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'"
        >Movies</button>
        <button
          @click="setType('tv')"
          class="px-4 py-2 text-sm font-medium transition"
          :class="contentType === 'tv' ? 'bg-indigo-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'"
        >TV Shows</button>
      </div>
    </div>

    <div v-if="error" class="text-red-400 text-sm bg-red-400/10 px-4 py-2 rounded-lg">{{ error }}</div>

    <div v-if="results.length" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 max-h-80 overflow-y-auto">
      <button
        v-for="item in results"
        :key="item.id"
        @click="selectResult(item)"
        class="relative rounded-lg overflow-hidden border-2 transition text-left"
        :class="modelValue?.id === item.id ? 'border-indigo-500 bg-indigo-500/10' : 'border-gray-700 hover:border-gray-500 bg-gray-800'"
      >
        <img
          v-if="item.poster_url"
          :src="item.poster_url"
          :alt="item.title"
          class="w-full aspect-[2/3] object-cover"
        />
        <div v-else class="w-full aspect-[2/3] bg-gray-700 flex items-center justify-center">
          <Film class="w-8 h-8 text-gray-500" />
        </div>
        <div class="p-2">
          <p class="text-white text-xs font-medium truncate">{{ item.title }}</p>
          <p class="text-gray-400 text-xs">{{ item.year || 'N/A' }}</p>
          <div v-if="item.rating" class="flex items-center gap-1 mt-1">
            <Star class="w-3 h-3 text-yellow-400 fill-yellow-400" />
            <span class="text-yellow-400 text-xs">{{ Number(item.rating).toFixed(1) }}</span>
          </div>
          <p v-if="contentType === 'tv' && item.episode_count" class="text-gray-500 text-xs mt-1">{{ item.episode_count }} episodes</p>
        </div>
      </button>
    </div>

    <div v-else-if="searched && !searching" class="text-center py-8 text-gray-500 text-sm">
      No results found
    </div>

    <div v-if="modelValue" class="flex items-center justify-between bg-gray-700/50 rounded-lg px-4 py-3 border border-gray-600">
      <div class="flex items-center gap-3">
        <img v-if="modelValue.poster_url" :src="modelValue.poster_url" class="w-10 h-14 object-cover rounded" />
        <div>
          <p class="text-white text-sm font-medium">{{ modelValue.title }}</p>
          <p class="text-gray-400 text-xs">{{ modelValue.year }} &middot; {{ contentType === 'movie' ? 'Movie' : 'TV Show' }}</p>
        </div>
      </div>
      <button
        @click="$emit('import')"
        class="px-4 py-2 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-500 hover:to-purple-500 text-white text-sm font-medium rounded-lg transition flex items-center gap-2"
      >
        <Download class="w-4 h-4" />
        Import from TMDB
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useUiStore } from '@/Stores/ui'
import { route } from '@/Composables/useRoute'
import { Search, Loader2, Film, Star, Download } from 'lucide-vue-next'

const props = defineProps({
  modelValue: { type: Object, default: null },
  contentType: { type: String, default: 'movie' },
})

const emit = defineEmits(['update:modelValue', 'import'])

const uiStore = useUiStore()

const searchQuery = ref('')
const results = ref([])
const searching = ref(false)
const searched = ref(false)
const error = ref(null)
const contentType = ref(props.contentType)

let debounceTimer = null

function debouncedSearch() {
  clearTimeout(debounceTimer)
  error.value = null
  if (searchQuery.value.trim().length < 2) {
    results.value = []
    searched.value = false
    return
  }
  debounceTimer = setTimeout(() => doSearch(), 300)
}

async function doSearch() {
  searching.value = true
  searched.value = true
  error.value = null
  try {
    const xsrf = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))
    const token = xsrf ? decodeURIComponent(xsrf.split('=')[1]) : ''
    const res = await fetch(route('admin.vod.search-tmdb'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'X-XSRF-TOKEN': token,
      },
      body: JSON.stringify({ query: searchQuery.value, type: contentType.value }),
      credentials: 'same-origin',
    })
    const json = await res.json()
    if (!res.ok) {
      error.value = json.message || json.error || 'Search failed'
      results.value = []
    } else {
      results.value = json.data || []
    }
  } catch (e) {
    error.value = 'Network error. Please try again.'
    results.value = []
  } finally {
    searching.value = false
  }
}

function setType(type) {
  contentType.value = type
  if (searchQuery.value.trim().length >= 2) doSearch()
}

function selectResult(item) {
  emit('update:modelValue', item)
}
</script>
