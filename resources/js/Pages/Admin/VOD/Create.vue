<template>
  <AdminLayout>
    <div class="p-6 max-w-3xl mx-auto">
      <div class="mb-6">
        <Link :href="route('admin.vod.index')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
          <ArrowLeft class="w-4 h-4" /> Back to VOD
        </Link>
        <h1 class="text-2xl font-bold text-white">Add VOD Content</h1>
      </div>

      <!-- TMDB Import -->
      <div class="mb-6 bg-gradient-to-br from-blue-900/40 to-purple-900/40 rounded-xl p-6 border border-blue-700/30">
        <h2 class="text-lg font-semibold text-white mb-1 flex items-center gap-2">
          <Film class="w-5 h-5 text-blue-400" /> TMDB Import
        </h2>
        <p class="text-gray-400 text-sm mb-4">Search and import metadata from The Movie Database</p>
        <TMDBSearch v-model="tmdbSelected" :content-type="isTVShow ? 'tv' : 'movie'" @import="applyTMDBData" />
      </div>

      <form @submit.prevent="submit" class="bg-gray-800 rounded-xl p-6 border border-gray-700 space-y-6">
        <!-- Basic Info -->
        <div class="grid grid-cols-2 gap-4">
          <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-300 mb-2">Title *</label>
            <input v-model="form.title" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            <p v-if="form.errors.title" class="text-red-400 text-sm mt-1">{{ form.errors.title }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Type *</label>
            <select v-model="form.type" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
              <option value="movie">Movie</option>
              <option value="series">Series</option>
              <option value="documentary">Documentary</option>
              <option value="tv_show">TV Show</option>
              <option value="anime">Anime</option>
              <option value="kids">Kids</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Year</label>
            <input v-model="form.year" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
          <textarea v-model="form.description" rows="3" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
        </div>

        <!-- Stream Source (movies/docs only) -->
        <div v-if="!isTVShow">
          <label class="block text-sm font-medium text-gray-300 mb-3">Stream Source</label>
          <div class="flex gap-4 mb-4">
            <label class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
              <input type="radio" v-model="sourceMode" value="url" class="text-indigo-600" /> Stream URL
            </label>
            <label class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
              <input type="radio" v-model="sourceMode" value="upload" class="text-indigo-600" /> Upload File
            </label>
          </div>
          <div v-if="sourceMode === 'url'">
            <input v-model="form.stream_url" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" placeholder="http://..." />
            <p v-if="form.errors.stream_url" class="text-red-400 text-sm mt-1">{{ form.errors.stream_url }}</p>
          </div>
          <div v-else>
            <div
              class="border-2 border-dashed border-gray-600 rounded-lg p-8 text-center hover:border-indigo-500 transition cursor-pointer"
              @click="triggerFileInput"
              @dragover.prevent="dragging = true"
              @dragleave="dragging = false"
              @drop.prevent="handleDrop"
              :class="{ 'border-indigo-500 bg-indigo-500/10': dragging }"
            >
              <Upload class="w-10 h-10 mx-auto text-gray-400 mb-3" />
              <p class="text-gray-300 mb-1">{{ uploadFile ? uploadFile.name : 'Drag & drop file or click to browse' }}</p>
              <p class="text-gray-500 text-xs">MP4, MKV, AVI, MOV (max 2GB)</p>
            </div>
            <input ref="fileInput" type="file" accept=".mp4,.mkv,.avi,.mov" class="hidden" @change="handleFileSelect" />
            <div v-if="uploadProgress > 0 && uploadProgress < 100" class="mt-3">
              <div class="w-full bg-gray-700 rounded-full h-2">
                <div class="bg-indigo-500 h-2 rounded-full transition-all" :style="{ width: uploadProgress + '%' }"></div>
              </div>
              <p class="text-gray-400 text-xs mt-1">Uploading... {{ uploadProgress }}%</p>
            </div>
            <p v-if="uploadError" class="text-red-400 text-sm mt-2">{{ uploadError }}</p>
          </div>
        </div>

        <!-- Series notice -->
        <div v-if="isTVShow" class="flex items-start gap-3 bg-indigo-900/20 border border-indigo-700/40 rounded-lg p-4">
          <Film class="w-5 h-5 text-indigo-400 mt-0.5 shrink-0" />
          <p class="text-indigo-300 text-sm">Seasons &amp; episodes are managed after saving. Save the series first, then add seasons and episodes from the edit page.</p>
        </div>

        <!-- Media URLs -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Poster URL</label>
            <input v-model="form.poster_url" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            <img v-if="form.poster_url" :src="form.poster_url" class="mt-2 h-24 rounded object-cover" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Backdrop URL</label>
            <input v-model="form.backdrop_url" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            <img v-if="form.backdrop_url" :src="form.backdrop_url" class="mt-2 h-24 rounded object-cover w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Trailer URL</label>
            <input v-model="form.trailer_url" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Duration (minutes)</label>
            <input v-model="form.duration" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>
        </div>

        <!-- Metadata -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Rating (/10)</label>
            <input v-model="form.rating" type="number" step="any" min="0" max="10" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Director</label>
            <input v-model="form.director" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Genre (comma-separated)</label>
          <input v-model="genreInput" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" placeholder="Action, Sci-Fi, Thriller" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Cast (comma-separated)</label>
          <input v-model="castInput" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" placeholder="Actor 1, Actor 2" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">TMDB ID</label>
            <input v-model="form.tmdb_id" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">IMDB ID</label>
            <input v-model="form.imdb_id" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" placeholder="tt0000000" />
          </div>
        </div>

        <!-- Categories & Bouquets — always shown for all types -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Categories <span v-if="!isTVShow">*</span></label>
            <div class="space-y-2 max-h-40 overflow-y-auto bg-gray-700 rounded-lg p-3">
              <label v-for="cat in categories" :key="cat.id" class="flex items-center gap-2 text-gray-300 text-sm">
                <input type="checkbox" :value="cat.id" v-model="form.category_ids" class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600" />
                {{ cat.name }}
              </label>
            </div>
            <p v-if="form.errors.category_ids" class="text-red-400 text-sm mt-1">{{ form.errors.category_ids }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Bouquets</label>
            <div class="space-y-2 max-h-40 overflow-y-auto bg-gray-700 rounded-lg p-3">
              <p v-if="!bouquets.length" class="text-gray-500 text-xs">No bouquets available</p>
              <label v-for="b in bouquets" :key="b.id" class="flex items-center gap-2 text-gray-300 text-sm">
                <input type="checkbox" :value="b.id" v-model="form.bouquet_ids" class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600" />
                {{ b.name }}
              </label>
            </div>
          </div>
        </div>

        <!-- Status -->
        <div class="flex gap-6">
          <label class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
            <input v-model="form.is_active" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600" /> Active
          </label>
          <label class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
            <input v-model="form.is_featured" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600" /> Featured
          </label>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-gray-700">
          <Link :href="route('admin.vod.index')" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">Cancel</Link>
          <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50">
            {{ form.processing ? 'Creating...' : 'Create VOD' }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import TMDBSearch from '@/Components/TMDBSearch.vue'
import { ArrowLeft, Upload, Film } from 'lucide-vue-next'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  bouquets: { type: Array, default: () => [] },
})

const isTVShow = computed(() => ['series', 'tv_show', 'tv', 'anime'].includes(form.type))

const sourceMode = ref('url')
const genreInput = ref('')
const castInput = ref('')
const uploadFile = ref(null)
const dragging = ref(false)
const uploadProgress = ref(0)
const fileInput = ref(null)
const tmdbSelected = ref(null)

const form = useForm({
  title: '', type: 'movie', year: null, description: '', stream_url: '',
  poster_url: '', backdrop_url: '', trailer_url: '', duration: null,
  rating: null, director: '', genre: [], cast: [], tmdb_id: null, imdb_id: '',
  category_ids: [], bouquet_ids: [], is_active: true, is_featured: false,
})

watch(genreInput, (v) => { form.genre = v.split(',').map(s => s.trim()).filter(Boolean) })
watch(castInput, (v) => { form.cast = v.split(',').map(s => s.trim()).filter(Boolean) })

const applyTMDBData = async () => {
  if (!tmdbSelected.value) return
  const t = tmdbSelected.value
  const tmdbId = t.tmdb_id || t.id
  const type = t.type === 'tv' ? 'tv' : (isTVShow.value ? 'tv' : 'movie')

  if (tmdbId) {
    try {
      const xsrf = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))
      const token = xsrf ? decodeURIComponent(xsrf.split('=')[1]) : ''
      const res = await fetch(route('admin.vod.tmdb-details'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-XSRF-TOKEN': token },
        credentials: 'same-origin',
        body: JSON.stringify({ tmdb_id: Number(tmdbId), type }),
      })
      if (res.ok) {
        const json = await res.json()
        const d = json.data
        if (d) {
          form.title = d.title || t.title || ''
          form.description = d.overview || ''
          form.year = d.release_year || null
          form.rating = d.vote_average || null
          form.tmdb_id = d.tmdb_id || tmdbId || null
          form.imdb_id = d.imdb_id || ''
          form.poster_url = d.poster_url || t.poster_url || ''
          form.backdrop_url = d.backdrop_url || t.backdrop_url || ''
          form.director = d.director || ''
          form.duration = d.runtime || null
          form.trailer_url = d.trailer_url || ''
          if (Array.isArray(d.genres) && d.genres.length) { genreInput.value = d.genres.join(', '); form.genre = d.genres }
          if (Array.isArray(d.cast) && d.cast.length) { castInput.value = d.cast.join(', '); form.cast = d.cast }
          if (type === 'tv') form.type = 'series'
          tmdbSelected.value = null
          return
        }
      }
    } catch (e) { /* fall through */ }
  }

  form.title = t.title || ''
  form.description = t.overview || t.description || ''
  form.year = t.release_year || t.year || null
  form.rating = t.vote_average || t.rating || null
  form.tmdb_id = t.tmdb_id || t.id || null
  form.poster_url = t.poster_url || ''
  form.backdrop_url = t.backdrop_url || ''
  tmdbSelected.value = null
}

const triggerFileInput = () => fileInput.value?.click()
const handleFileSelect = (e) => { uploadFile.value = e.target.files[0] }
const handleDrop = (e) => { dragging.value = false; uploadFile.value = e.dataTransfer.files[0] }

const uploadError = ref('')

const submit = async () => {
  uploadError.value = ''

  if (sourceMode.value === 'upload' && uploadFile.value) {
    const fd = new FormData()
    fd.append('file', uploadFile.value)
    fd.append('title', form.title)
    fd.append('type', form.type)
    if (form.description) fd.append('description', form.description)
    if (form.year) fd.append('year', form.year)
    if (form.duration) fd.append('duration', form.duration)
    if (form.rating != null) fd.append('rating', form.rating)
    if (form.director) fd.append('director', form.director)
    if (form.poster_url) fd.append('poster_url', form.poster_url)
    if (form.backdrop_url) fd.append('backdrop_url', form.backdrop_url)
    if (form.trailer_url) fd.append('trailer_url', form.trailer_url)
    if (form.tmdb_id) fd.append('tmdb_id', form.tmdb_id)
    if (form.imdb_id) fd.append('imdb_id', form.imdb_id)
    if (form.genre && form.genre.length) form.genre.forEach(g => fd.append('genre[]', g))
    if (form.cast && form.cast.length) form.cast.forEach(c => fd.append('cast[]', c))
    form.category_ids.forEach(id => fd.append('category_ids[]', id))
    form.bouquet_ids.forEach(id => fd.append('bouquet_ids[]', id))
    fd.append('is_active', form.is_active ? '1' : '0')
    fd.append('is_featured', form.is_featured ? '1' : '0')

    try {
      uploadProgress.value = 1
      form.processing = true

      const xsrf = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))
      const token = xsrf ? decodeURIComponent(xsrf.split('=')[1]) : ''

      const res = await fetch(route('admin.vod.upload'), {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'text/html, application/xhtml+xml',
          'X-XSRF-TOKEN': token,
        },
        credentials: 'same-origin',
        body: fd,
        onUploadProgress: (e) => {
          if (e.lengthComputable) uploadProgress.value = Math.round((e.loaded / e.total) * 100)
        },
      })

      uploadProgress.value = 100

      if (res.redirected || res.ok) {
        window.location.href = res.url || route('admin.vod.index')
      } else {
        const text = await res.text()
        uploadError.value = `Upload failed (${res.status}). ${text.substring(0, 200)}`
        uploadProgress.value = 0
        form.processing = false
      }
    } catch (e) {
      uploadError.value = 'Upload failed: ' + e.message
      uploadProgress.value = 0
      form.processing = false
    }
  } else {
    form.post(route('admin.vod.store'))
  }
}
</script>
