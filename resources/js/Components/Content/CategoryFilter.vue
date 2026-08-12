<template>
  <div :class="vertical ? 'flex flex-col space-y-1' : 'flex flex-wrap gap-2'">
    <!-- All Button -->
    <button
      @click="$emit('select', null)"
      :class="[
        'px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg text-sm font-medium transition-colors tv-focusable',
        !selected
          ? 'bg-indigo-600 text-white'
          : 'bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-white'
      ]"
    >
      All
    </button>

    <!-- Category Buttons -->
    <button
      v-for="category in categories"
      :key="category.id"
      @click="$emit('select', category.id)"
      :class="[
        'px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg text-sm font-medium transition-colors flex items-center space-x-1.5 sm:space-x-2 tv-focusable',
        selected === category.id
          ? 'bg-indigo-600 text-white'
          : 'bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-white'
      ]"
    >
      <component
        v-if="category.icon"
        :is="category.icon"
        class="w-3 h-3 sm:w-4 sm:h-4"
      />
      <span>{{ category.name }}</span>
      <span
        v-if="showCount && category.count !== undefined"
        class="px-1.5 py-0.5 text-xs bg-gray-700 rounded-full"
      >
        {{ category.count }}
      </span>
    </button>
  </div>
</template>

<script setup>
defineProps({
  categories: {
    type: Array,
    required: true,
    validator: (cats) => cats.every(c => c.id !== undefined && c.name),
  },
  selected: {
    type: [Number, String, null],
    default: null,
  },
  vertical: {
    type: Boolean,
    default: false,
  },
  showCount: {
    type: Boolean,
    default: true,
  },
})

defineEmits(['select'])
</script>
