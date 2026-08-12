<template>
  <nav class="flex items-center space-x-2 text-sm text-gray-400">
    <template v-for="(item, index) in items" :key="index">
      <Link
        v-if="item.href && index !== items.length - 1"
        :href="item.href"
        class="hover:text-white transition-colors"
      >
        {{ item.label }}
      </Link>
      <span v-else class="text-gray-300 font-medium">
        {{ item.label }}
      </span>
      <ChevronRightIcon
        v-if="index !== items.length - 1"
        class="w-4 h-4 text-gray-600"
      />
    </template>
  </nav>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import { ChevronRightIcon } from '@heroicons/vue/24/outline'

defineProps({
  items: {
    type: Array,
    required: true,
    validator: (items) => items.every(item => item.label),
  },
})
</script>
