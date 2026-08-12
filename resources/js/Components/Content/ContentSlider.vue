<template>
  <div class="relative group/slider">
    <!-- Header -->
    <div v-if="title" class="flex items-center justify-between mb-3 sm:mb-4">
      <h2 class="text-lg sm:text-xl font-bold text-white">{{ title }}</h2>
      <Link
        v-if="viewAllHref"
        :href="viewAllHref"
        class="text-sm text-indigo-400 hover:text-indigo-300 transition-colors"
      >
        View All
      </Link>
    </div>

    <!-- Slider Container -->
    <div class="relative">
      <!-- Navigation Arrows -->
      <button
        v-if="showArrows"
        @click="scrollLeft"
        class="absolute left-0 top-1/2 -translate-y-1/2 z-10 w-8 h-8 sm:w-10 sm:h-10 bg-black/60 backdrop-blur-sm rounded-full flex items-center justify-center opacity-0 group-hover/slider:opacity-100 transition-opacity hover:bg-black/80 tv-touch-target tv-focusable"
      >
        <ChevronLeftIcon class="w-5 h-5 sm:w-6 sm:h-6" />
      </button>
      <button
        v-if="showArrows"
        @click="scrollRight"
        class="absolute right-0 top-1/2 -translate-y-1/2 z-10 w-8 h-8 sm:w-10 sm:h-10 bg-black/60 backdrop-blur-sm rounded-full flex items-center justify-center opacity-0 group-hover/slider:opacity-100 transition-opacity hover:bg-black/80 tv-touch-target tv-focusable"
      >
        <ChevronRightIcon class="w-5 h-5 sm:w-6 sm:h-6" />
      </button>

      <!-- Scrollable Content -->
      <div
        ref="scrollContainer"
        class="flex space-x-3 sm:space-x-4 overflow-x-auto scrollbar-thin scroll-smooth pb-4"
        @scroll="onScroll"
      >
        <slot />
      </div>
    </div>

    <!-- Scroll Indicators -->
    <div v-if="showIndicators" class="flex justify-center mt-4 space-x-2">
      <button
        v-for="(_, index) in totalPages"
        :key="index"
        @click="scrollToPage(index)"
        :class="[
          'w-2 h-2 rounded-full transition-all tv-touch-target',
          currentPage === index ? 'bg-indigo-500 w-6' : 'bg-gray-600 hover:bg-gray-500'
        ]"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  title: {
    type: String,
    default: '',
  },
  viewAllHref: {
    type: String,
    default: '',
  },
  showArrows: {
    type: Boolean,
    default: true,
  },
  showIndicators: {
    type: Boolean,
    default: false,
  },
  scrollAmount: {
    type: Number,
    default: 300,
  },
})

const scrollContainer = ref(null)
const currentPage = ref(0)

const totalPages = computed(() => {
  if (!scrollContainer.value) return 0
  const containerWidth = scrollContainer.value.clientWidth
  const scrollWidth = scrollContainer.value.scrollWidth
  return Math.ceil(scrollWidth / containerWidth)
})

const scrollLeft = () => {
  scrollContainer.value.scrollBy({
    left: -props.scrollAmount,
    behavior: 'smooth',
  })
}

const scrollRight = () => {
  scrollContainer.value.scrollBy({
    left: props.scrollAmount,
    behavior: 'smooth',
  })
}

const scrollToPage = (page) => {
  if (!scrollContainer.value) return
  const containerWidth = scrollContainer.value.clientWidth
  scrollContainer.value.scrollTo({
    left: page * containerWidth,
    behavior: 'smooth',
  })
}

const onScroll = () => {
  if (!scrollContainer.value) return
  const containerWidth = scrollContainer.value.clientWidth
  currentPage.value = Math.round(scrollContainer.value.scrollLeft / containerWidth)
}
</script>
