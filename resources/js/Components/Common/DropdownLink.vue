<template>
  <Link
    :href="href"
    :method="method"
    :as="as"
    class="block w-full text-left px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition-colors tv-focusable"
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
  method: {
    type: String,
    default: 'get',
  },
  as: {
    type: String,
    default: 'a',
  },
})
</script>
