<template>
  <div class="mb-6 sm:mb-8">
    <h3 class="text-lg sm:text-xl font-bold text-white mb-3 sm:mb-4">Genres</h3>
    <div class="flex flex-wrap gap-2 sm:gap-3">
      <span
        v-for="genre in genres"
        :key="genre"
        @click="$emit('select', genre)"
        class="px-3 sm:px-4 py-1.5 sm:py-2 bg-gray-800 hover:bg-purple-600 text-gray-300 hover:text-white rounded-full text-xs sm:text-sm cursor-pointer transition tv-touch-target tv-focusable"
      >
        {{ genre }}
      </span>
    </div>
  </div>
</template>

<script setup>
defineProps({ genres: Array })
defineEmits(['select'])
</script>
