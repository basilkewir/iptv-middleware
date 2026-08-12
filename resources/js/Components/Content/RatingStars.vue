<template>
  <div class="flex items-center" :class="wrapperClass">
    <template v-for="star in 5" :key="star">
      <button
        v-if="interactive"
        @click="$emit('update:modelValue', star)"
        @mouseenter="hoveredStar = star"
        @mouseleave="hoveredStar = 0"
        class="focus:outline-none tv-touch-target tv-focusable"
      >
        <StarIcon
          :class="[
            'transition-colors',
            sizeClass,
            getStarColor(star)
          ]"
        />
      </button>
      <StarIcon
        v-else
        :class="[
          'transition-colors',
          sizeClass,
          getStarColor(star)
        ]"
      />
    </template>
    <span
      v-if="showValue"
      class="ml-2 text-xs sm:text-sm font-medium text-gray-400"
    >
      {{ modelValue?.toFixed(1) || '0.0' }}
    </span>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { StarIcon } from '@heroicons/vue/24/solid'

const props = defineProps({
  modelValue: {
    type: Number,
    default: 0,
  },
  interactive: {
    type: Boolean,
    default: false,
  },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md', 'lg'].includes(v),
  },
  showValue: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['update:modelValue'])

const hoveredStar = ref(0)

const wrapperClass = computed(() => props.interactive ? 'group' : '')

const sizeClass = computed(() => {
  const sizes = {
    sm: 'w-3 h-3 sm:w-4 sm:h-4',
    md: 'w-4 h-4 sm:w-5 sm:h-5',
    lg: 'w-5 h-5 sm:w-6 sm:h-6',
  }
  return sizes[props.size]
})

const getStarColor = (star) => {
  const rating = hoveredStar.value || props.modelValue || 0
  if (star <= Math.floor(rating)) {
    return 'text-yellow-400'
  }
  if (star - 0.5 <= rating) {
    return 'text-yellow-400/50'
  }
  return 'text-gray-600'
}
</script>
