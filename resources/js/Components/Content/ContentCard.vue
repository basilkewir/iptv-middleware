<template>
  <Link
    :href="href"
    class="group block bg-gray-800 rounded-xl overflow-hidden border border-gray-700 hover:border-indigo-500/50 transition-all duration-200 hover:shadow-lg hover:shadow-indigo-500/10 tv-focusable"
  >
    <!-- Poster/Logo -->
    <div class="relative aspect-video bg-gray-700">
      <img
        v-if="poster"
        :src="poster"
        :alt="title"
        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
      />
      <div
        v-else
        class="w-full h-full flex items-center justify-center"
      >
        <component :is="typeIcon" class="w-12 h-12 text-gray-500" />
      </div>

      <!-- Type Badge -->
      <div class="absolute top-2 left-2">
        <span :class="[
          'px-2 py-1 text-xs font-medium rounded',
          typeBadgeClass
        ]">
          {{ type }}
        </span>
      </div>

      <!-- Rating Badge -->
      <div
        v-if="rating"
        class="absolute top-2 right-2 flex items-center space-x-1 bg-black/60 backdrop-blur-sm px-2 py-1 rounded"
      >
        <StarIcon class="w-3.5 h-3.5 text-yellow-400" />
        <span class="text-xs font-medium">{{ rating.toFixed(1) }}</span>
      </div>

      <!-- Hover Overlay -->
      <div class="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition-colors flex items-center justify-center">
        <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transform scale-75 group-hover:scale-100 transition-all">
          <PlayIcon class="w-6 h-6 text-white ml-0.5" />
        </div>
      </div>

      <!-- Watch Progress -->
      <div
        v-if="watchProgress > 0"
        class="absolute bottom-0 left-0 right-0 h-1 bg-gray-600"
      >
        <div
          class="h-full bg-indigo-500"
          :style="{ width: `${watchProgress}%` }"
        />
      </div>
    </div>

    <!-- Content Info -->
    <div class="p-3 sm:p-4">
      <h3 class="font-semibold text-white truncate group-hover:text-indigo-400 transition-colors text-sm sm:text-base">
        {{ title }}
      </h3>
      <p v-if="subtitle" class="mt-1 text-sm text-gray-400 truncate">
        {{ subtitle }}
      </p>
      <div v-if="genres?.length" class="mt-2 flex flex-wrap gap-1">
        <span
          v-for="genre in genres.slice(0, 3)"
          :key="genre"
          class="px-2 py-0.5 text-xs bg-gray-700 text-gray-300 rounded"
        >
          {{ genre }}
        </span>
      </div>
    </div>
  </Link>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { PlayIcon, StarIcon, TvIcon, FilmIcon } from '@heroicons/vue/24/solid'

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  subtitle: {
    type: String,
    default: '',
  },
  poster: {
    type: String,
    default: '',
  },
  type: {
    type: String,
    default: 'channel',
    validator: (v) => ['channel', 'movie', 'series', 'episode'].includes(v),
  },
  rating: {
    type: Number,
    default: null,
  },
  genres: {
    type: Array,
    default: () => [],
  },
  href: {
    type: String,
    required: true,
  },
  watchProgress: {
    type: Number,
    default: 0,
  },
})

const typeIcon = computed(() => {
  return props.type === 'channel' ? TvIcon : FilmIcon
})

const typeBadgeClass = computed(() => {
  const classes = {
    channel: 'bg-green-500/80 text-white',
    movie: 'bg-blue-500/80 text-white',
    series: 'bg-purple-500/80 text-white',
    episode: 'bg-orange-500/80 text-white',
  }
  return classes[props.type] || classes.channel
})
</script>
