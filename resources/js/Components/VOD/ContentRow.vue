<template>
  <div class="mb-6 sm:mb-8">
    <h3 class="text-lg sm:text-xl font-bold text-white mb-3 sm:mb-4">{{ title }}</h3>
    <div class="flex gap-3 sm:gap-4 overflow-x-auto pb-4 scrollbar-thin">
      <div
        v-for="item in items"
        :key="item.id"
        @click="$emit('select', item)"
        class="flex-none w-40 sm:w-48 cursor-pointer group tv-focusable"
      >
        <div class="aspect-[2/3] bg-gray-800 rounded-lg overflow-hidden mb-2 group-hover:ring-2 ring-purple-500 transition relative">
          <img
            v-if="item.poster_url"
            :src="item.poster_url"
            :alt="item.title"
            class="w-full h-full object-cover"
          />
          <div v-else class="w-full h-full flex items-center justify-center text-gray-600">
            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
          </div>
        </div>
        <p class="text-sm text-gray-300 group-hover:text-white transition truncate">{{ item.title }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({ title: String, items: Array })
defineEmits(['play', 'select'])
</script>
