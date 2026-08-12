<template>
  <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 sm:gap-4">
    <div v-for="i in count" :key="i" class="animate-pulse">
      <div class="aspect-[2/3] bg-gray-800 rounded-lg mb-2"></div>
      <div class="h-4 bg-gray-800 rounded w-3/4"></div>
    </div>
  </div>
</template>

<script setup>
defineProps({ count: { type: Number, default: 10 } })
</script>
