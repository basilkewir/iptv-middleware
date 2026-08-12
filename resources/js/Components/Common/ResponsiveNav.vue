<template>
  <div
    :class="[
      'lg:hidden fixed inset-0 z-40 transform transition-transform duration-200',
      open ? 'translate-x-0' : '-translate-x-full'
    ]"
  >
    <!-- Overlay -->
    <div
      v-if="open"
      class="absolute inset-0 bg-black/50"
      @click="$emit('close')"
    />

    <!-- Nav Panel -->
    <div class="relative w-64 h-full bg-gray-800 border-r border-gray-700">
      <div class="flex items-center justify-between h-16 px-4 border-b border-gray-700">
        <slot name="brand" />
        <button
          @click="$emit('close')"
          class="p-1 rounded-md hover:bg-gray-700"
        >
          <XMarkIcon class="w-5 h-5" />
        </button>
      </div>

      <nav class="p-3 space-y-1">
        <slot />
      </nav>
    </div>
  </div>
</template>

<script setup>
import { XMarkIcon } from '@heroicons/vue/24/outline'

defineProps({
  open: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['close'])
</script>
