<template>
  <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
    <div class="flex items-start justify-between">
      <div>
        <p class="text-sm font-medium text-gray-400">{{ title }}</p>
        <p class="mt-2 text-3xl font-bold text-white">
          {{ formattedValue }}
        </p>
      </div>
      <div :class="[
        'p-3 rounded-lg',
        iconBgClass
      ]">
        <component :is="icon" :class="['w-6 h-6', iconColorClass]" />
      </div>
    </div>

    <div v-if="change !== null" class="mt-4 flex items-center">
      <component
        :is="change >= 0 ? ArrowUpIcon : ArrowDownIcon"
        :class="[
          'w-4 h-4 mr-1',
          change >= 0 ? 'text-green-500' : 'text-red-500'
        ]"
      />
      <span :class="[
        'text-sm font-medium',
        change >= 0 ? 'text-green-500' : 'text-red-500'
      ]">
        {{ Math.abs(change) }}%
      </span>
      <span class="ml-2 text-sm text-gray-500">vs last period</span>
    </div>

    <div v-if="description" class="mt-2">
      <p class="text-xs text-gray-500">{{ description }}</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { ArrowUpIcon, ArrowDownIcon } from '@heroicons/vue/24/solid'

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  value: {
    type: [Number, String],
    required: true,
  },
  icon: {
    type: [Object, Function],
    required: true,
  },
  change: {
    type: Number,
    default: null,
  },
  description: {
    type: String,
    default: '',
  },
  color: {
    type: String,
    default: 'indigo',
    validator: (v) => ['indigo', 'green', 'blue', 'red', 'yellow'].includes(v),
  },
  prefix: {
    type: String,
    default: '',
  },
  suffix: {
    type: String,
    default: '',
  },
})

const formattedValue = computed(() => {
  const val = typeof props.value === 'number'
    ? props.value.toLocaleString()
    : props.value
  return `${props.prefix}${val}${props.suffix}`
})

const iconBgClass = computed(() => {
  const classes = {
    indigo: 'bg-indigo-500/20',
    green: 'bg-green-500/20',
    blue: 'bg-blue-500/20',
    red: 'bg-red-500/20',
    yellow: 'bg-yellow-500/20',
  }
  return classes[props.color]
})

const iconColorClass = computed(() => {
  const classes = {
    indigo: 'text-indigo-400',
    green: 'text-green-400',
    blue: 'text-blue-400',
    red: 'text-red-400',
    yellow: 'text-yellow-400',
  }
  return classes[props.color]
})
</script>
