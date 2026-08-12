<template>
  <div class="relative">
    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
      <MagnifyingGlassIcon class="w-4 h-4 sm:w-5 sm:h-5 text-gray-500" />
    </div>
    <input
      ref="inputEl"
      type="text"
      :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"
      :placeholder="placeholder"
      :class="[
        'w-full pl-9 sm:pl-10 pr-8 sm:pr-10 py-2 sm:py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500',
        'focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent',
        'transition-colors tv-focusable',
        sizeClass
      ]"
    />
    <button
      v-if="modelValue"
      @click="$emit('update:modelValue', '')"
      class="absolute inset-y-0 right-0 pr-2 sm:pr-3 flex items-center text-gray-500 hover:text-white tv-touch-target tv-focusable"
    >
      <XMarkIcon class="w-4 h-4 sm:w-5 sm:h-5" />
    </button>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { MagnifyingGlassIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: 'Search...',
  },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md', 'lg'].includes(v),
  },
})

defineEmits(['update:modelValue'])

const inputEl = ref(null)

const sizeClass = {
  sm: 'text-sm py-2',
  md: 'text-base py-2.5',
  lg: 'text-lg py-3',
}[props.size]

defineExpose({
  focus: () => inputEl.value?.focus(),
})
</script>
