<template>
  <div :class="['grid gap-4', gridClass]">
    <ContentCard
      v-for="item in items"
      :key="item.id"
      :title="item.title"
      :subtitle="item.subtitle"
      :poster="item.poster"
      :type="item.type"
      :rating="item.rating"
      :genres="item.genres"
      :href="item.href"
      :watch-progress="item.watch_progress"
    />
  </div>

  <!-- Empty State -->
  <div
    v-if="items.length === 0 && !loading"
    class="text-center py-12"
  >
    <component :is="emptyIcon" class="w-16 h-16 mx-auto text-gray-600 mb-4" />
    <h3 class="text-lg font-medium text-gray-400">{{ emptyTitle }}</h3>
    <p class="mt-1 text-sm text-gray-500">{{ emptyMessage }}</p>
  </div>

  <!-- Loading Skeleton -->
  <div
    v-if="loading"
    :class="['grid gap-4', gridClass]"
  >
    <div
      v-for="n in 8"
      :key="n"
      class="bg-gray-800 rounded-xl overflow-hidden border border-gray-700 animate-pulse"
    >
      <div class="aspect-video bg-gray-700" />
      <div class="p-4 space-y-3">
        <div class="h-4 bg-gray-700 rounded w-3/4" />
        <div class="h-3 bg-gray-700 rounded w-1/2" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { FilmIcon, TvIcon } from '@heroicons/vue/24/outline'
import ContentCard from './ContentCard.vue'

const props = defineProps({
  items: {
    type: Array,
    default: () => [],
  },
  columns: {
    type: Number,
    default: 4,
    validator: (v) => v >= 1 && v <= 6,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  emptyTitle: {
    type: String,
    default: 'No content found',
  },
  emptyMessage: {
    type: String,
    default: 'Try adjusting your filters or search terms.',
  },
  emptyIcon: {
    type: [Object, Function],
    default: FilmIcon,
  },
})

const gridClass = computed(() => {
  const cols = {
    1: 'grid-cols-1',
    2: 'grid-cols-1 sm:grid-cols-2',
    3: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
    4: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4',
    5: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5',
    6: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6',
  }
  return cols[props.columns] || cols[4]
})
</script>
