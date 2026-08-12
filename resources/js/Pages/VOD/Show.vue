<script setup>
import { ref, computed } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AppLayout from '@/Layouts/AppLayout.vue'
import ContentCard from '@/Components/VOD/ContentCard.vue'

const props = defineProps({
  item: Object,
  seasons: Array,
  episodes: Array,
  similar: Array,
  reviews: Array,
  isInWatchlist: Boolean,
  userReview: Object,
})

const selectedSeason = ref(props.seasons?.[0]?.id || null)
const showReviewForm = ref(false)

const form = useForm({
  rating: props.userReview?.rating || 5,
  comment: props.userReview?.comment || '',
})

const isSeries = computed(() => props.item?.type === 'series')

const filteredEpisodes = computed(() => {
  if (!selectedSeason.value) return props.episodes
  return props.episodes?.filter(ep => ep.season_id === selectedSeason.value) || []
})

const play = () => {
  if (isSeries.value && filteredEpisodes.value?.length) {
    router.visit(route('vod.player', { item: props.item.slug, episode: filteredEpisodes.value[0].id }))
  } else {
    router.visit(route('vod.player', props.item.slug))
  }
}

const playEpisode = (episode) => {
  router.visit(route('vod.player', { item: props.item.slug, episode: episode.id }))
}

const toggleWatchlist = () => {
  if (props.isInWatchlist) {
    router.delete(route('vod.watchlist.destroy', props.item.slug), { preserveState: true })
  } else {
    router.post(route('vod.watchlist.store', props.item.slug), {}, { preserveState: true })
  }
}

const submitReview = () => {
  form.post(route('vod.reviews.store', props.item.slug), {
    onSuccess: () => { showReviewForm.value = false }
  })
}

const formatDuration = (minutes) => {
  if (!minutes) return ''
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  return h > 0 ? `${h}h ${m}m` : `${m}m`
}
</script>

<template>
  <AppLayout>
    <div class="relative h-[50vh] min-h-[300px] sm:min-h-[400px] md:min-h-[500px]">
      <img :src="item?.backdrop_url || item?.poster_url" :alt="item?.title" class="w-full h-full object-cover" />
      <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-gray-900/50 to-transparent" />
      <div class="absolute inset-0 bg-gradient-to-r from-gray-900/80 to-transparent" />

      <div class="absolute bottom-0 left-0 right-0 p-4 sm:p-6 md:p-8 max-w-7xl mx-auto">
        <div class="flex flex-col md:flex-row gap-4 sm:gap-6 md:gap-8">
          <img :src="item?.poster_url" :alt="item?.title" class="w-32 sm:w-40 md:w-48 rounded-lg shadow-2xl flex-shrink-0" />

          <div class="flex-1">
            <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold mb-3">{{ item.title }}</h1>

            <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-4 text-xs sm:text-sm">
              <span v-if="item.year" class="text-gray-300">{{ item.year }}</span>
              <span v-if="item.rating" class="flex items-center gap-1 text-yellow-400">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" /></svg>
                {{ item.rating }}
              </span>
              <span v-if="item.duration" class="text-gray-300">{{ formatDuration(item.duration) }}</span>
              <span v-if="item.content_rating" class="px-2 py-0.5 bg-gray-700 rounded text-xs">{{ item.content_rating }}</span>
            </div>

            <div v-if="item?.genre?.length" class="flex flex-wrap gap-1 sm:gap-2 mb-3 sm:mb-4">
              <span
                v-for="(g, idx) in item.genre"
                :key="idx"
                class="px-2 sm:px-3 py-1 bg-gray-800/80 rounded-full text-xs sm:text-sm"
              >{{ typeof g === 'string' ? g : g.name }}</span>
            </div>

            <p class="text-gray-300 mb-4 sm:mb-6 max-w-2xl leading-relaxed text-sm sm:text-base line-clamp-3 sm:line-clamp-none">{{ item.description }}</p>

            <div v-if="item.director || item.cast?.length" class="mb-4 sm:mb-6 text-xs sm:text-sm">
              <p v-if="item.director" class="text-gray-400"><span class="text-gray-300 font-medium">Director:</span> {{ item.director }}</p>
              <p v-if="item.cast?.length" class="text-gray-400"><span class="text-gray-300 font-medium">Cast:</span> {{ item.cast.join(', ') }}</p>
            </div>

            <div class="flex flex-wrap gap-3 sm:gap-4">
              <button @click="play" class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 px-6 sm:px-8 py-2.5 sm:py-3 rounded-lg font-semibold transition tv-touch-target tv-focusable">
                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11v11.78a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z" /></svg>
                Play
              </button>
              <button @click="toggleWatchlist" :class="[
                'flex items-center gap-2 px-5 sm:px-6 py-2.5 sm:py-3 rounded-lg font-semibold border transition tv-touch-target tv-focusable',
                isInWatchlist ? 'bg-indigo-600/20 border-indigo-500 text-indigo-400' : 'border-gray-600 hover:border-gray-400'
              ]">
                <svg v-if="!isInWatchlist" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                <svg v-else class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                {{ isInWatchlist ? 'In My List' : 'Add to List' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 space-y-8 sm:space-y-12">
      <section v-if="isSeries && seasons?.length">
        <h2 class="text-xl sm:text-2xl font-bold mb-4 sm:mb-6">Episodes</h2>
        <div class="flex gap-2 mb-4 sm:mb-6 overflow-x-auto pb-2 scrollbar-thin">
          <button
            v-for="season in seasons"
            :key="season.id"
            @click="selectedSeason = season.id"
            :class="[
              'px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg text-sm font-medium whitespace-nowrap transition tv-focusable',
              selectedSeason === season.id ? 'bg-indigo-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'
            ]"
          >
            Season {{ season.number }}
          </button>
        </div>
        <div class="space-y-3 sm:space-y-4">
          <div
            v-for="episode in filteredEpisodes"
            :key="episode.id"
            @click="playEpisode(episode)"
            class="flex gap-3 sm:gap-4 bg-gray-800 rounded-lg p-3 sm:p-4 hover:bg-gray-750 cursor-pointer transition tv-focusable"
          >
            <div class="w-32 sm:w-40 aspect-video bg-gray-700 rounded overflow-hidden flex-shrink-0">
              <img v-if="episode.thumbnail" :src="episode.thumbnail" class="w-full h-full object-cover" />
            </div>
            <div class="flex-1">
              <div class="flex items-center justify-between">
                <h3 class="font-semibold text-sm sm:text-base">{{ episode.number }}. {{ episode.title }}</h3>
                <span class="text-sm text-gray-400">{{ formatDuration(episode.duration) }}</span>
              </div>
              <p class="text-sm text-gray-400 mt-1 line-clamp-2">{{ episode.description }}</p>
            </div>
          </div>
        </div>
      </section>

      <section v-if="similar?.length">
        <h2 class="text-xl sm:text-2xl font-bold mb-4 sm:mb-6">Similar Content</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 sm:gap-4">
          <ContentCard
            v-for="s in similar"
            :key="s.id"
            :item="s"
            @click="router.visit(route('vod.show', s.slug))"
          />
        </div>
      </section>

      <section>
        <div class="flex items-center justify-between mb-4 sm:mb-6">
          <h2 class="text-xl sm:text-2xl font-bold">Reviews</h2>
          <button @click="showReviewForm = !showReviewForm" class="text-indigo-400 hover:text-indigo-300 text-sm transition tv-focusable">
            {{ userReview ? 'Edit Review' : 'Write a Review' }}
          </button>
        </div>

        <form v-if="showReviewForm" @submit.prevent="submitReview" class="bg-gray-800 rounded-lg p-4 sm:p-6 mb-6">
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-300 mb-2">Rating</label>
            <div class="flex gap-1">
              <button
                v-for="star in 10"
                :key="star"
                type="button"
                @click="form.rating = star"
                :class="['text-2xl transition tv-touch-target tv-focusable', star <= form.rating ? 'text-yellow-400' : 'text-gray-600 hover:text-gray-400']"
              >&#9733;</button>
            </div>
          </div>
          <div class="mb-4">
            <textarea
              v-model="form.comment"
              rows="3"
              placeholder="Share your thoughts..."
              class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 sm:px-4 py-2 sm:py-3 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none resize-none text-sm sm:text-base"
            />
          </div>
          <div class="flex gap-3">
            <button type="submit" :disabled="form.processing" class="bg-indigo-600 hover:bg-indigo-700 px-5 sm:px-6 py-2 rounded-lg text-sm font-medium transition disabled:opacity-50 tv-focusable">Submit</button>
            <button type="button" @click="showReviewForm = false" class="bg-gray-700 hover:bg-gray-600 px-5 sm:px-6 py-2 rounded-lg text-sm font-medium transition tv-focusable">Cancel</button>
          </div>
        </form>

        <div v-if="reviews?.length" class="space-y-3 sm:space-y-4">
          <div v-for="review in reviews" :key="review.id" class="bg-gray-800 rounded-lg p-3 sm:p-4">
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-indigo-600 rounded-full flex items-center justify-center text-sm font-medium">
                  {{ review.user?.name?.charAt(0) || '?' }}
                </div>
                <span class="font-medium text-sm">{{ review.user?.name || 'Anonymous' }}</span>
              </div>
              <div class="flex items-center gap-1 text-yellow-400 text-sm">
                <span v-for="i in review.rating" :key="i">&#9733;</span>
                <span class="text-gray-500">/10</span>
              </div>
            </div>
            <p v-if="review.comment" class="text-gray-300 text-sm">{{ review.comment }}</p>
          </div>
        </div>
        <p v-else class="text-gray-400 text-center py-6 sm:py-8">No reviews yet. Be the first to review!</p>
      </section>
    </div>
  </AppLayout>
</template>
