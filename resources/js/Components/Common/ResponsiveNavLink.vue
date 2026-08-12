<template>
  <Link
    :href="href"
    :class="[
      'flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors',
      active
        ? 'bg-indigo-600/20 text-indigo-400'
        : 'text-gray-400 hover:bg-gray-700/50 hover:text-white'
    ]"
  >
    <slot />
  </Link>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  href: {
    type: String,
    required: true,
  },
  active: {
    type: Boolean,
    default: false,
  },
})
</script>
