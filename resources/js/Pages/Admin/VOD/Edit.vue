<template>
  <AdminLayout>
    <div class="p-6 max-w-3xl mx-auto">
      <div class="mb-6">
        <Link :href="route('admin.vod.index')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
          <ArrowLeft class="w-4 h-4" /> Back to VOD
        </Link>
        <h1 class="text-2xl font-bold text-white">Edit: {{ vod?.title }}</h1>
      </div>

      <!-- TMDB Import -->
      <div class="mb-6 bg-gradient-to-br from-blue-900/40 to-purple-900/40 rounded-xl p-6 border border-blue-700/30">
        <div class="flex items-center justify-between mb-1">
          <h2 class="text-lg font-semibold text-white flex items-center gap-2">
            <Film class="w-5 h-5 text-blue-400" />
            TMDB Import
          </h2>
          <button
            v-if="vod?.tmdb_id"
            @click.prevent="refetchTMDB"
            :disabled="refetching"
            class="px-3 py-1.5 bg-purple-600 hover:bg-purple-500 text-white text-sm rounded-lg transition disabled:opacity-50 flex items-center gap-1.5"
          >
            <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': refetching }" />
            {{ refetching ? 'Re-fetching...' : 'Re-fetch from TMDB' }}
          </button>
        </div>
        <p v-if="vod?.tmdb_id" class="text-gray-400 text-sm mb-3">
          Currently linked to TMDB ID: <span class="text-blue-400 font-mono">{{ vod.tmdb_id }}</span>
        </p>
        <p v-else class="text-gray-400 text-sm mb-4">Search and import metadata from The Movie Database</p>
        <TMDBSearch v-model="tmdbSelected" :content-type="isTVShow ? 'tv' : 'movie'" @import="applyTMDBData" />
      </div>

      <form @submit.prevent="submit" class="bg-gray-800 rounded-xl p-6 border border-gray-700 space-y-6">
        <div class="grid grid-cols-2 gap-4">
          <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-300 mb-2">Title *</label>
            <input v-model="form.title" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Type</label>
            <select v-model="form.type" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white">
              <option value="movie">Movie</option><option value="series">Series</option><option value="documentary">Documentary</option><option value="tv_show">TV Show</option><option value="anime">Anime</option><option value="kids">Kids</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Year</label>
            <input v-model="form.year" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
          <textarea v-model="form.description" rows="3" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white" />
        </div>

        <!-- Stream Source -->
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-3">Stream Source</label>
          <div class="flex gap-4 mb-4">
            <label class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
              <input type="radio" v-model="sourceMode" value="url" class="text-indigo-600" /> Stream URL
            </label>
            <label class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
              <input type="radio" v-model="sourceMode" value="upload" class="text-indigo-600" /> Upload New File
            </label>
          </div>

          <div v-if="sourceMode === 'url'">
            <input v-model="form.stream_url" type="url" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white" placeholder="http://..." />
            <p v-if="vod.stream_url" class="text-gray-500 text-xs mt-1">Current: <a :href="vod.stream_url" target="_blank" class="text-indigo-400 hover:underline">{{ vod.stream_url }}</a></p>
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
              <p class="text-gray-300 mb-1">{{ uploadFile ? uploadFile.name : (vod.stream_url ? vod.stream_url.split('/').pop() : 'Drag & drop file or click to browse') }}</p>
              <p class="text-gray-500 text-xs">{{ vod.stream_url && !uploadFile ? 'Current file — drop a new one to replace' : 'MP4, MKV, AVI, MOV (max 2GB)' }}</p>
            </div>
            <input ref="fileInput" type="file" accept=".mp4,.mkv,.avi,.mov" class="hidden" @change="handleFileSelect" />
            <div v-if="uploadProgress > 0 && uploadProgress < 100" class="mt-3">
              <div class="w-full bg-gray-700 rounded-full h-2">
                <div class="bg-indigo-500 h-2 rounded-full transition-all" :style="{ width: uploadProgress + '%' }"></div>
              </div>
              <p class="text-gray-400 text-xs mt-1">Uploading... {{ uploadProgress }}%</p>
            </div>
          </div>
        </div>

        <!-- TV Show Notice -->
        <div v-if="isTVShow" class="bg-gray-700/30 rounded-lg p-4 border border-gray-600">
          <p class="text-gray-400 text-sm">
            <Tv class="w-4 h-4 inline mr-2" />
            This is a TV show. Stream URLs are managed per episode in the Seasons & Episodes section below. A VOD-level stream URL is also available above for trailers or metadata.
          </p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Poster URL</label>
            <input v-model="form.poster_url" type="url" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white" />
            <img v-if="form.poster_url" :src="form.poster_url" class="mt-2 h-24 rounded object-cover" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Backdrop URL</label>
            <input v-model="form.backdrop_url" type="url" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white" />
            <img v-if="form.backdrop_url" :src="form.backdrop_url" class="mt-2 h-24 rounded object-cover w-full" />
          </div>
          <div><label class="block text-sm font-medium text-gray-300 mb-2">Duration (min)</label><input v-model="form.duration" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white" /></div>
          <div><label class="block text-sm font-medium text-gray-300 mb-2">Trailer URL</label><input v-model="form.trailer_url" type="url" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white" /></div>
        </div>
        <div class="grid grid-cols-3 gap-4">
          <div><label class="block text-sm font-medium text-gray-300 mb-2">Rating</label><input v-model="form.rating" type="number" step="any" min="0" max="10" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white" /></div>
          <div><label class="block text-sm font-medium text-gray-300 mb-2">TMDB ID</label><input v-model="form.tmdb_id" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white" /></div>
          <div><label class="block text-sm font-medium text-gray-300 mb-2">IMDB ID</label><input v-model="form.imdb_id" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white" /></div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Genre (comma-separated)</label>
          <input v-model="genreInput" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Cast (comma-separated)</label>
          <input v-model="castInput" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Categories</label>
            <div class="space-y-2 max-h-40 overflow-y-auto bg-gray-700 rounded-lg p-3">
              <label v-for="cat in categories" :key="cat.id" class="flex items-center gap-2 text-gray-300 text-sm">
                <input type="checkbox" :value="cat.id" v-model="form.category_ids" class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600" /> {{ cat.name }}
              </label>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Bouquets</label>
            <div class="space-y-2 max-h-40 overflow-y-auto bg-gray-700 rounded-lg p-3">
              <label v-for="b in bouquets" :key="b.id" class="flex items-center gap-2 text-gray-300 text-sm">
                <input type="checkbox" :value="b.id" v-model="form.bouquet_ids" class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600" /> {{ b.name }}
              </label>
            </div>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <label class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer"><input v-model="form.is_active" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600" /> Active</label>
          <label class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer"><input v-model="form.is_featured" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600" /> Featured</label>
        </div>
        <div class="flex justify-end gap-3 pt-4 border-t border-gray-700">
          <Link :href="route('admin.vod.index')" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">Cancel</Link>
          <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50">
            {{ form.processing ? 'Updating...' : 'Update VOD' }}
          </button>
        </div>
      </form>

      <!-- Seasons & Episodes -->
      <div v-if="showSeasonsSection" class="mt-6 bg-gray-800 rounded-xl p-6 border border-gray-700">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-white flex items-center gap-2">
            <Tv class="w-5 h-5 text-indigo-400" />
            Seasons & Episodes
          </h2>
          <div class="flex items-center gap-2">
            <div v-if="vod?.tmdb_id" class="flex items-center gap-2">
              <input v-model.number="tmdbSeasonInput" type="number" min="1" placeholder="Season #"
                class="w-24 px-2 py-1.5 bg-gray-700 border border-gray-600 rounded text-white text-sm" />
              <button
                @click="loadTMDBSeasonDirect"
                :disabled="loadingEpisodes || !tmdbSeasonInput"
                class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-sm rounded-lg transition disabled:opacity-50 flex items-center gap-1.5"
              >
                <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': loadingEpisodes }" />
                Import from TMDB
              </button>
            </div>
            <button @click="showAddSeason = !showAddSeason" class="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded-lg transition flex items-center gap-1.5">
              <Plus class="w-3.5 h-3.5" /> Add Season
            </button>
          </div>
        </div>

        <!-- Add Season Form -->
        <div v-if="showAddSeason" class="mb-4 p-4 bg-gray-700/50 rounded-lg border border-gray-600 space-y-3">
          <h3 class="text-sm font-medium text-gray-300">New Season</h3>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-gray-400 mb-1">Season Number *</label>
              <input v-model.number="newSeason.season_number" type="number" min="1" class="w-full px-3 py-1.5 bg-gray-700 border border-gray-600 rounded text-white text-sm" />
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">Title</label>
              <input v-model="newSeason.title" type="text" class="w-full px-3 py-1.5 bg-gray-700 border border-gray-600 rounded text-white text-sm" placeholder="Season 1" />
            </div>
          </div>
          <div class="flex gap-2 justify-end">
            <button @click="showAddSeason = false" class="px-3 py-1.5 bg-gray-600 hover:bg-gray-500 text-white text-sm rounded-lg transition">Cancel</button>
            <button @click="addSeason" :disabled="!newSeason.season_number" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-sm rounded-lg transition disabled:opacity-50">Add Season</button>
          </div>
        </div>

        <div v-if="!allSeasons.length" class="text-center py-6 text-gray-500 text-sm">
          No seasons yet. Add a season or import from TMDB.
        </div>

        <!-- Season panels -->
        <div v-for="seasonNum in allSeasons" :key="seasonNum" class="mb-4 border border-gray-700 rounded-lg overflow-hidden">
          <!-- Season header -->
          <div
            class="flex items-center justify-between px-4 py-3 bg-gray-700/60 cursor-pointer select-none"
            @click="toggleSeason(seasonNum)"
          >
            <span class="text-white font-medium text-sm flex items-center gap-2">
              <ChevronDown class="w-4 h-4 transition-transform" :class="{ 'rotate-180': !collapsedSeasons.has(seasonNum) }" />
              Season {{ seasonNum }}
              <span class="text-gray-400 text-xs">({{ episodesBySeason[seasonNum]?.length ?? 0 }} episodes)</span>
            </span>
            <div class="flex items-center gap-2" @click.stop>
              <button
                v-if="vod?.tmdb_id"
                @click="loadTMDBForSeason(seasonNum)"
                :disabled="loadingSeasons.has(seasonNum)"
                class="px-2 py-1 bg-indigo-700 hover:bg-indigo-600 text-white text-xs rounded transition disabled:opacity-50 flex items-center gap-1"
              >
                <RefreshCw class="w-3 h-3" :class="{ 'animate-spin': loadingSeasons.has(seasonNum) }" />
                Sync TMDB
              </button>
              <button
                @click="openAddEpisode(seasonNum)"
                class="px-2 py-1 bg-gray-600 hover:bg-gray-500 text-white text-xs rounded transition flex items-center gap-1"
              >
                <Plus class="w-3 h-3" /> Add Episode
              </button>
            </div>
          </div>

          <!-- Add Episode inline form -->
          <div v-if="addEpisodeForSeason === seasonNum" class="p-4 bg-gray-700/30 border-b border-gray-700 space-y-3">
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs text-gray-400 mb-1">Episode Number *</label>
                <input v-model.number="newEpisode.episode_number" type="number" min="1" class="w-full px-3 py-1.5 bg-gray-700 border border-gray-600 rounded text-white text-sm" />
              </div>
              <div>
                <label class="block text-xs text-gray-400 mb-1">Title</label>
                <input v-model="newEpisode.episode_title" type="text" class="w-full px-3 py-1.5 bg-gray-700 border border-gray-600 rounded text-white text-sm" />
              </div>
              <div class="col-span-2">
                <label class="block text-xs text-gray-400 mb-2">Stream Source</label>
                <div class="flex gap-3 mb-2">
                  <label class="flex items-center gap-1.5 text-gray-300 text-xs cursor-pointer"><input type="radio" v-model="newEpisode.sourceMode" value="url" /> URL</label>
                  <label class="flex items-center gap-1.5 text-gray-300 text-xs cursor-pointer"><input type="radio" v-model="newEpisode.sourceMode" value="upload" /> Upload</label>
                </div>
                <input v-if="newEpisode.sourceMode === 'url'" v-model="newEpisode.stream_url" type="text"
                  class="w-full px-3 py-1.5 bg-gray-700 border border-gray-600 rounded text-white text-sm" placeholder="Leave blank to add later" />
                <div v-else>
                  <input ref="newEpFileInput" type="file" accept=".mp4,.mkv,.avi,.mov,.webm" class="hidden" @change="e => newEpisode.file = e.target.files[0]" />
                  <div @click="$refs.newEpFileInput.click()" class="border border-dashed border-gray-500 rounded px-3 py-2 text-xs text-gray-400 cursor-pointer hover:border-indigo-500 transition">
                    {{ newEpisode.file ? newEpisode.file.name : 'Click to select video file' }}
                  </div>
                </div>
              </div>
              <div>
                <label class="block text-xs text-gray-400 mb-1">Air Date</label>
                <input v-model="newEpisode.air_date" type="date" class="w-full px-3 py-1.5 bg-gray-700 border border-gray-600 rounded text-white text-sm" />
              </div>
              <div>
                <label class="block text-xs text-gray-400 mb-1">Duration (min)</label>
                <input v-model.number="newEpisode.duration" type="number" min="0" class="w-full px-3 py-1.5 bg-gray-700 border border-gray-600 rounded text-white text-sm" />
              </div>
            </div>
            <p v-if="episodeError" class="text-red-400 text-xs">{{ episodeError }}</p>
            <div class="flex gap-2 justify-end">
              <button @click="addEpisodeForSeason = null; episodeError = ''" class="px-3 py-1.5 bg-gray-600 hover:bg-gray-500 text-white text-sm rounded-lg transition">Cancel</button>
              <button @click="saveNewEpisode" :disabled="!newEpisode.episode_number || savingEpisode"
                class="px-3 py-1.5 bg-green-600 hover:bg-green-500 text-white text-sm rounded-lg transition disabled:opacity-50">
                {{ savingEpisode ? 'Saving...' : 'Save Episode' }}
              </button>
            </div>
          </div>

          <!-- Episodes table -->
          <div v-if="!collapsedSeasons.has(seasonNum)">
            <div v-if="episodesBySeason[seasonNum]?.length" class="overflow-x-auto">
              <table class="w-full text-sm text-gray-300">
                <thead class="bg-gray-700/30">
                  <tr>
                    <th class="px-3 py-2 text-left text-xs text-gray-400">Ep</th>
                    <th class="px-3 py-2 text-left text-xs text-gray-400">Title</th>
                    <th class="px-3 py-2 text-left text-xs text-gray-400">Stream URL / File</th>
                    <th class="px-3 py-2 text-left text-xs text-gray-400">Air Date</th>
                    <th class="px-3 py-2 text-left text-xs text-gray-400">Dur</th>
                    <th class="px-3 py-2 text-left text-xs text-gray-400">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-700/50">
                  <tr v-for="ep in episodesBySeason[seasonNum]" :key="`${ep.season_number}_${ep.episode_number}`">
                    <td class="px-3 py-2">{{ ep.episode_number }}</td>
                    <td class="px-3 py-2">{{ ep.title || '-' }}</td>
                    <td class="px-3 py-2 min-w-[200px]">
                      <div v-if="!ep.uploadFile" class="flex gap-1">
                        <input v-model="ep.stream_url" type="text"
                          class="flex-1 px-2 py-1 bg-gray-700 border border-gray-600 rounded text-white text-xs"
                          placeholder="Add URL later" />
                        <label class="cursor-pointer px-2 py-1 bg-gray-600 hover:bg-gray-500 rounded text-xs text-gray-300 flex items-center gap-1">
                          <Upload class="w-3 h-3" />
                          <input type="file" accept=".mp4,.mkv,.avi,.mov,.webm" class="hidden"
                            @change="e => { ep.uploadFile = e.target.files[0]; ep.stream_url = '' }" />
                        </label>
                      </div>
                      <div v-else class="flex gap-1 items-center">
                        <span class="text-xs text-green-400 truncate max-w-[140px]">{{ ep.uploadFile.name }}</span>
                        <button @click="ep.uploadFile = null" class="text-gray-500 hover:text-red-400 text-xs">✕</button>
                      </div>
                    </td>
                    <td class="px-3 py-2 text-xs">{{ ep.air_date || '-' }}</td>
                    <td class="px-3 py-2 text-xs">{{ ep.duration || '-' }}</td>
                    <td class="px-3 py-2">
                      <button @click="deleteEpisode(ep)" class="text-red-400 hover:text-red-300 text-xs">Delete</button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-else class="px-4 py-3 text-gray-500 text-sm">No episodes yet for Season {{ seasonNum }}.</div>
          </div>
        </div>

        <div v-if="episodeError && !addEpisodeForSeason" class="text-red-500 text-sm mt-2">{{ episodeError }}</div>

        <div v-if="hasUnsavedEpisodeChanges" class="mt-4 flex justify-end">
          <button
            @click="saveEpisodes"
            :disabled="savingEpisodes"
            class="px-4 py-2 bg-green-600 hover:bg-green-500 text-white text-sm rounded-lg transition disabled:opacity-50"
          >
            {{ savingEpisodes ? 'Saving...' : 'Save Episode Changes' }}
          </button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, watch, computed, onMounted, reactive } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import TMDBSearch from '@/Components/TMDBSearch.vue'
import { ArrowLeft, Upload, Film, RefreshCw, Tv, Plus, ChevronDown } from 'lucide-vue-next'

const props = defineProps({ vod: { type: Object, required: true }, categories: { type: Array, default: () => [] }, bouquets: { type: Array, default: () => [] } })

const isTVShow = computed(() => ['series', 'tv_show', 'tv', 'anime'].includes(props.vod?.type))
const showSeasonsSection = computed(() => isTVShow.value)

// ── Season list ──────────────────────────────────────────────────────────────
const manualSeasons = ref([])
const availableSeasons = computed(() => {
  const s = new Set()
  const media = props.vod?.vod_media ?? props.vod?.vodMedia ?? []
  media.forEach(m => { if (m.season_number > 0) s.add(Number(m.season_number)) })
  manualSeasons.value.forEach(n => s.add(n))
  return Array.from(s).sort((a, b) => a - b)
})
const allSeasons = availableSeasons

// ── Episodes keyed by season ─────────────────────────────────────────────────
// episodesBySeason[n] = array of episode objects for season n
// Use ref+plain object so numeric and string key access is consistent
const episodesBySeason = reactive({})

const setSeasonEpisodes = (seasonNum, episodes) => {
  episodesBySeason[seasonNum] = episodes
}

const initEpisodesFromProps = () => {
  const media = props.vod?.vod_media ?? props.vod?.vodMedia ?? []
  media.forEach(m => {
    if (!m.season_number || m.season_number < 1) return
    const s = Number(m.season_number)
    if (!episodesBySeason[s]) setSeasonEpisodes(s, [])
    if (!episodesBySeason[s].find(e => Number(e.episode_number) === Number(m.episode_number))) {
      episodesBySeason[s].push({
        season_number: s,
        episode_number: Number(m.episode_number),
        title: m.episode_title || '',
        stream_url: m.stream_url || '',
        air_date: m.air_date ? m.air_date.substring(0, 10) : '',
        duration: m.duration || '',
        media_id: m.id,
        uploadFile: null,
      })
    }
  })
  Object.keys(episodesBySeason).forEach(s => {
    episodesBySeason[s].sort((a, b) => a.episode_number - b.episode_number)
  })
}

// ── Collapse state ───────────────────────────────────────────────────────────
const collapsedSeasons = ref(new Set())
const toggleSeason = (n) => {
  const s = new Set(collapsedSeasons.value)
  s.has(n) ? s.delete(n) : s.add(n)
  collapsedSeasons.value = s
}

// ── Add Season ───────────────────────────────────────────────────────────────
const showAddSeason = ref(false)
const newSeason = ref({ season_number: null, title: '' })
const addSeason = () => {
  const n = newSeason.value.season_number
  if (!n) return
  if (!manualSeasons.value.includes(n)) manualSeasons.value.push(n)
  if (!episodesBySeason[n]) setSeasonEpisodes(n, [])
  newSeason.value = { season_number: null, title: '' }
  showAddSeason.value = false
  // Expand the new season
  const s = new Set(collapsedSeasons.value)
  s.delete(n)
  collapsedSeasons.value = s
}

// ── Add Episode ──────────────────────────────────────────────────────────────
const addEpisodeForSeason = ref(null) // which season's form is open
const savingEpisode = ref(false)
const episodeError = ref('')
const newEpisode = ref({ episode_number: null, episode_title: '', stream_url: '', air_date: '', duration: null, sourceMode: 'url', file: null })

const openAddEpisode = (seasonNum) => {
  addEpisodeForSeason.value = addEpisodeForSeason.value === seasonNum ? null : seasonNum
  episodeError.value = ''
  newEpisode.value = { episode_number: null, episode_title: '', stream_url: '', air_date: '', duration: null, sourceMode: 'url', file: null }
  // Ensure season is expanded
  const s = new Set(collapsedSeasons.value)
  s.delete(seasonNum)
  collapsedSeasons.value = s
}

const saveNewEpisode = async () => {
  const seasonNum = addEpisodeForSeason.value
  if (!newEpisode.value.episode_number || !seasonNum) return
  savingEpisode.value = true
  episodeError.value = ''
  const vodId = Number(props.vod?.id)
  try {
    const xsrf = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))
    const token = xsrf ? decodeURIComponent(xsrf.split('=')[1]) : ''
    let streamUrl = newEpisode.value.stream_url || null

    if (newEpisode.value.sourceMode === 'upload' && newEpisode.value.file) {
      const fd = new FormData()
      fd.append('file', newEpisode.value.file)
      fd.append('season_number', seasonNum)
      fd.append('episode_number', newEpisode.value.episode_number)
      const upRes = await fetch(`/admin/vod/${vodId}/episodes/upload`, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-XSRF-TOKEN': token },
        credentials: 'same-origin',
        body: fd,
      })
      const upJson = await upRes.json()
      if (!upRes.ok) { episodeError.value = upJson.error || 'Upload failed.'; return }
      streamUrl = upJson.stream_url
    }

    const res = await fetch(`/admin/vod/${vodId}/episodes`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-XSRF-TOKEN': token },
      credentials: 'same-origin',
      body: JSON.stringify({
        season_number: seasonNum,
        episode_number: newEpisode.value.episode_number,
        episode_title: newEpisode.value.episode_title || null,
        stream_url: streamUrl,
        air_date: newEpisode.value.air_date || null,
        duration: newEpisode.value.duration || null,
      }),
    })
    const json = await res.json()
    if (!res.ok) { episodeError.value = json.error || 'Failed to save episode.'; return }

    if (!episodesBySeason[seasonNum]) setSeasonEpisodes(seasonNum, [])
    episodesBySeason[seasonNum].push({
      season_number: seasonNum,
      episode_number: newEpisode.value.episode_number,
      title: newEpisode.value.episode_title || '',
      stream_url: streamUrl || '',
      air_date: newEpisode.value.air_date || '',
      duration: newEpisode.value.duration || '',
      media_id: json.data?.id,
      uploadFile: null,
    })
    episodesBySeason[seasonNum].sort((a, b) => a.episode_number - b.episode_number)
    newEpisode.value = { episode_number: null, episode_title: '', stream_url: '', air_date: '', duration: null, sourceMode: 'url', file: null }
    addEpisodeForSeason.value = null
  } catch (e) {
    episodeError.value = 'An error occurred.'
  } finally {
    savingEpisode.value = false
  }
}

const deleteEpisode = async (ep) => {
  if (!confirm(`Delete S${ep.season_number}E${ep.episode_number}?`)) return
  const vodId = Number(props.vod?.id)
  try {
    const xsrf = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))
    const token = xsrf ? decodeURIComponent(xsrf.split('=')[1]) : ''
    const url = ep.media_id
      ? `/admin/vod/${vodId}/episodes/${ep.media_id}`
      : null
    if (url) {
      await fetch(url, {
        method: 'DELETE',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-XSRF-TOKEN': token },
        credentials: 'same-origin',
      })
    }
    const s = ep.season_number
    if (episodesBySeason[s]) {
      episodesBySeason[s] = episodesBySeason[s].filter(e => e !== ep)
    }
  } catch (e) { /* silent */ }
}

// ── TMDB per-season sync ─────────────────────────────────────────────────────
const loadingEpisodes = ref(false)
const loadingSeasons = ref(new Set())
const tmdbSeasonInput = ref(1)
const savingEpisodes = ref(false)

const loadTMDBForSeason = async (seasonNum) => {
  if (!props.vod?.tmdb_id) return
  const s = new Set(loadingSeasons.value)
  s.add(seasonNum)
  loadingSeasons.value = s
  const vodId = Number(props.vod?.id)
  try {
    const xsrf = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))
    const token = xsrf ? decodeURIComponent(xsrf.split('=')[1]) : ''
    const res = await fetch(`/admin/vod/${vodId}/tmdb-episodes`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-XSRF-TOKEN': token },
      credentials: 'same-origin',
      body: JSON.stringify({ season: seasonNum }),
    })
    const json = await res.json()
    if (res.ok && json.data) {
      episodesBySeason[seasonNum] = json.data.map(ep => ({
        season_number: seasonNum,
        episode_number: Number(ep.episode_number),
        title: ep.title || '',
        stream_url: ep.stream_url || '',
        air_date: ep.air_date ? ep.air_date.substring(0, 10) : '',
        duration: ep.duration || '',
        media_id: ep.media_id || null,
        uploadFile: null,
      })).sort((a, b) => a.episode_number - b.episode_number)
    }
  } catch (e) { /* silent */ } finally {
    const s2 = new Set(loadingSeasons.value)
    s2.delete(seasonNum)
    loadingSeasons.value = s2
  }
}

const loadTMDBSeasonDirect = async () => {
  if (!tmdbSeasonInput.value) return
  const n = tmdbSeasonInput.value
  if (!manualSeasons.value.includes(n) && !availableSeasons.value.includes(n)) {
    manualSeasons.value.push(n)
  }
  if (!episodesBySeason[n]) setSeasonEpisodes(n, [])
  await loadTMDBForSeason(n)
}

// ── Unsaved changes detection ────────────────────────────────────────────────
const hasUnsavedEpisodeChanges = computed(() => {
  return Object.values(episodesBySeason).some(eps =>
    eps.some(ep => ep.uploadFile)
  )
})

// ── Save episode URL changes (bulk PUT) ──────────────────────────────────────
const saveEpisodes = async () => {
  savingEpisodes.value = true
  episodeError.value = ''
  const vodId = Number(props.vod?.id)
  if (!vodId) { savingEpisodes.value = false; return }
  const xsrf = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))
  const token = xsrf ? decodeURIComponent(xsrf.split('=')[1]) : ''

  // Upload any pending files first
  for (const eps of Object.values(episodesBySeason)) {
    for (const ep of eps) {
      if (!ep.uploadFile) continue
      try {
        const fd = new FormData()
        fd.append('file', ep.uploadFile)
        fd.append('season_number', ep.season_number)
        fd.append('episode_number', ep.episode_number)
        const upRes = await fetch(`/admin/vod/${vodId}/episodes/upload`, {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-XSRF-TOKEN': token },
          credentials: 'same-origin',
          body: fd,
        })
        const upJson = await upRes.json()
        if (upRes.ok) { ep.stream_url = upJson.stream_url; ep.uploadFile = null }
      } catch (e) { /* continue */ }
    }
  }

  const episodesData = Object.values(episodesBySeason).flat().map(ep => ({
    season_number: ep.season_number,
    episode_number: ep.episode_number,
    episode_title: ep.title || '',
    stream_url: ep.stream_url || '',
    air_date: ep.air_date || null,
    duration: ep.duration || null,
    media_id: ep.media_id || null,
  }))

  try {
    const res = await fetch(`/admin/vod/${vodId}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-XSRF-TOKEN': token },
      credentials: 'same-origin',
      body: JSON.stringify({ ...form.data(), episodes_data: episodesData }),
    })
    if (!res.ok) {
      const json = await res.json()
      episodeError.value = json?.message || 'Failed to save episodes.'
    }
  } catch (e) {
    episodeError.value = 'An error occurred while saving.'
  } finally {
    savingEpisodes.value = false
  }
}

// ── Form & media ─────────────────────────────────────────────────────────────────
const sourceMode = ref(props.vod?.stream_url?.startsWith('/storage/') ? 'upload' : 'url')
const genreArr = Array.isArray(props.vod?.genre) ? props.vod.genre : (typeof props.vod?.genre === 'string' && props.vod.genre ? JSON.parse(props.vod.genre) : [])
const castArr = Array.isArray(props.vod?.cast) ? props.vod.cast : (typeof props.vod?.cast === 'string' && props.vod.cast ? JSON.parse(props.vod.cast) : [])
const genreInput = ref(genreArr.join(', '))
const castInput = ref(castArr.join(', '))
const uploadFile = ref(null)
const dragging = ref(false)
const uploadProgress = ref(0)
const fileInput = ref(null)
const tmdbSelected = ref(null)
const refetching = ref(false)

const applyTMDBData = async () => {
  if (!tmdbSelected.value) return
  const t = tmdbSelected.value
  const tmdbId = t.tmdb_id || t.id
  const vodType = props.vod.type || 'movie'
  const type = t.type === 'tv' ? 'tv' : (vodType === 'series' || vodType === 'tv_show' || vodType === 'tv' || vodType === 'anime' ? 'tv' : 'movie')

  if (tmdbId) {
    try {
      const xsrf = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))
      const token = xsrf ? decodeURIComponent(xsrf.split('=')[1]) : ''
      const res = await fetch(route('admin.vod.tmdb-details'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
          'X-XSRF-TOKEN': token,
        },
        credentials: 'same-origin',
        body: JSON.stringify({ tmdb_id: Number(tmdbId), type }),
      })
      if (res.ok) {
        const json = await res.json()
        const d = json.data
        if (d) {
          form.title = d.title || t.title || ''
          form.description = d.overview || ''
          form.year = d.release_year || ''
          form.rating = d.vote_average || ''
          form.tmdb_id = d.tmdb_id || tmdbId || ''
          form.imdb_id = d.imdb_id || ''
          form.poster_url = d.poster_url || t.poster_url || ''
          form.backdrop_url = d.backdrop_url || t.backdrop_url || ''
          form.director = d.director || ''
          form.duration = d.runtime || ''
          form.trailer_url = d.trailer_url || ''
          if (Array.isArray(d.genres) && d.genres.length) {
            genreInput.value = d.genres.join(', ')
            form.genre = d.genres
          }
          if (Array.isArray(d.cast) && d.cast.length) {
            castInput.value = d.cast.join(', ')
            form.cast = d.cast
          }
          tmdbSelected.value = null
          return
        }
      }
    } catch (e) { /* fall through to search result data */ }
  }

  form.title = t.title || ''
  form.description = t.overview || t.description || ''
  form.year = t.release_year || t.year || ''
  form.rating = t.vote_average || t.rating || ''
  form.tmdb_id = t.tmdb_id || t.id || ''
  form.poster_url = t.poster_url || ''
  form.backdrop_url = t.backdrop_url || ''
  tmdbSelected.value = null
}

const refetchTMDB = async () => {
  refetching.value = true
  try {
    const xsrf = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))
    const token = xsrf ? decodeURIComponent(xsrf.split('=')[1]) : ''
    const res = await fetch(route('admin.vod.auto-tmdb', props.vod.id), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'X-XSRF-TOKEN': token,
      },
      credentials: 'same-origin',
    })
    const json = await res.json()
    if (res.ok && json.data) {
      const t = json.data
      form.title = t.title || form.title
      form.description = t.overview || form.description
      form.year = t.release_year || form.year
      form.rating = t.vote_average || form.rating
      form.tmdb_id = t.tmdb_id || form.tmdb_id
      form.imdb_id = t.imdb_id || form.imdb_id
      form.poster_url = t.poster_url || form.poster_url
      form.backdrop_url = t.backdrop_url || form.backdrop_url
      form.director = t.director || form.director
      form.duration = t.runtime || form.duration
      form.trailer_url = t.trailer_url || form.trailer_url
      if (Array.isArray(t.genres) && t.genres.length) {
        genreInput.value = t.genres.join(', ')
        form.genre = t.genres
      }
      if (Array.isArray(t.cast) && t.cast.length) {
        castInput.value = t.cast.join(', ')
        form.cast = t.cast
      }
    }
  } catch (e) {
    // silent
  } finally {
    refetching.value = false
  }
}

  
const form = useForm({
  title: props.vod?.title || '', type: props.vod?.type || 'movie', year: props.vod?.year || '',
  description: props.vod?.description || '', stream_url: props.vod?.stream_url || '',
  poster_url: props.vod?.poster_url || '', backdrop_url: props.vod?.backdrop_url || '',
  trailer_url: props.vod?.trailer_url || '', duration: props.vod?.duration || '',
  rating: props.vod?.rating || '', director: props.vod?.director || '',
  genre: props.vod?.genre || [], cast: props.vod?.cast || [],
  tmdb_id: props.vod?.tmdb_id || '', imdb_id: props.vod?.imdb_id || '',
  season_count: props.vod?.season_count || '', episode_count: props.vod?.episode_count || '',
  category_ids: (props.vod?.categories || []).map(c => c.id),
  bouquet_ids: (props.vod?.bouquets || []).map(b => b.id),
   is_active: props.vod?.is_active ?? true, is_featured: props.vod?.is_featured ?? false,
   episode_urls: {},
   episodes_data: [],
 })

onMounted(() => {
  if (isTVShow.value) initEpisodesFromProps()
})

watch(genreInput, (v) => { form.genre = v.split(',').map(s => s.trim()).filter(Boolean) })
watch(castInput, (v) => { form.cast = v.split(',').map(s => s.trim()).filter(Boolean) })

const triggerFileInput = () => fileInput.value?.click()
const handleFileSelect = (e) => { uploadFile.value = e.target.files[0] }
const handleDrop = (e) => {
  dragging.value = false
  uploadFile.value = e.dataTransfer.files[0]
}

  const submit = () => {
  const allEpisodes = Object.values(episodesBySeason).flat()
  form.episodes_data = allEpisodes.map(ep => ({
    season_number: ep.season_number,
    episode_number: ep.episode_number,
    episode_title: ep.title || '',
    stream_url: ep.stream_url || '',
    air_date: ep.air_date || null,
    duration: ep.duration || null,
    media_id: ep.media_id || null,
  }))

  const vodId = Number(props.vod?.id)
  if (!vodId) return

  if (sourceMode.value === 'upload' && uploadFile.value) {
    // Browser can't send PUT with multipart — use POST + _method spoofing
    form.transform(data => ({ ...data, _method: 'PUT', file: uploadFile.value, episodes_data: JSON.stringify(data.episodes_data) }))
      .post(`/admin/vod/${vodId}`, {
        forceFormData: true,
        onStart: () => { uploadProgress.value = 1 },
        onProgress: (progress) => { uploadProgress.value = progress.percentage },
        onFinish: () => { uploadProgress.value = 0; form.transform(data => data) },
      })
  } else {
    form.put(`/admin/vod/${vodId}`)
  }
}
</script>
