<template>
  <div class="relative w-full h-[300px] sm:h-[400px] md:h-[500px] bg-gray-900 rounded-xl overflow-hidden">
    <div v-if="items?.length" class="relative w-full h-full">
      <div class="absolute inset-0 flex items-center justify-center">
        <div class="text-center z-10 px-4">
          <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-white mb-3 sm:mb-4">{{ items[currentSlide]?.title }}</h2>
          <p class="text-gray-300 mb-4 sm:mb-6 max-w-xl mx-auto text-sm sm:text-base line-clamp-3">{{ items[currentSlide]?.description }}</p>
          <div class="flex gap-3 sm:gap-4 justify-center flex-wrap">
            <button
              @click="$emit('play', items[currentSlide])"
              class="px-6 sm:px-8 py-2.5 sm:py-3 bg-purple-600 hover:bg-purple-700 text-white font-semibold rounded-lg transition tv-touch-target tv-focusable"
            >
              Play
            </button>
            <button
              @click="$emit('select', items[currentSlide])"
              class="px-6 sm:px-8 py-2.5 sm:py-3 bg-gray-700 hover:bg-gray-600 text-white font-semibold rounded-lg transition tv-touch-target tv-focusable"
            >
              More Info
            </button>
          </div>
        </div>
      </div>
      <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2">
        <button
          v-for="(_, i) in items"
          :key="i"
          @click="$emit('slide-change', i)"
          class="w-2 h-2 rounded-full transition tv-touch-target"
          :class="i === currentSlide ? 'bg-white' : 'bg-gray-500'"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({ items: Array, currentSlide: { type: Number, default: 0 } })
defineEmits(['play', 'select', 'slide-change'])
</script>
