<script setup>
import { ref, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AppLayout from '@/Layouts/AppLayout.vue'
import HeroSlider from '@/Components/VOD/HeroSlider.vue'
import ContentRow from '@/Components/VOD/ContentRow.vue'
import GenreSection from '@/Components/VOD/GenreSection.vue'

const props = defineProps({
  featured: Array,
  latestMovies: Array,
  latestSeries: Array,
  genres: Array,
  continueWatching: Array,
})

const currentSlide = ref(0)
const heroInterval = ref(null)

onMounted(() => {
  if (props.featured?.length) {
    heroInterval.value = setInterval(() => {
      currentSlide.value = (currentSlide.value + 1) % props.featured.length
    }, 6000)
  }
})

const goToSlide = (index) => {
  currentSlide.value = index
  clearInterval(heroInterval.value)
  heroInterval.value = setInterval(() => {
    currentSlide.value = (currentSlide.value + 1) % props.featured.length
  }, 6000)
}

const playContent = (content) => {
  router.visit(route('vod.player', content.slug))
}

const showContent = (content) => {
  router.visit(route('vod.show', content.slug))
}
</script>

<template>
  <AppLayout>
    <HeroSlider
      v-if="featured?.length"
      :items="featured"
      :current-slide="currentSlide"
      @slide-change="goToSlide"
      @play="playContent"
      @info="showContent"
    />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-8 sm:space-y-12">
      <ContentRow
        v-if="continueWatching?.length"
        title="Continue Watching"
        :items="continueWatching"
        @play="playContent"
        @select="showContent"
      />

      <ContentRow
        v-if="latestMovies?.length"
        title="Latest Movies"
        :items="latestMovies"
        @play="playContent"
        @select="showContent"
      />

      <ContentRow
        v-if="latestSeries?.length"
        title="Latest Series"
        :items="latestSeries"
        @play="playContent"
        @select="showContent"
      />

      <GenreSection
        v-for="genre in genres"
        :key="genre.id"
        :genre="genre"
        @play="playContent"
        @select="showContent"
      />
    </div>
  </AppLayout>
</template>
