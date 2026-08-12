<template>
  <label :class="['flex items-start cursor-pointer', disabled ? 'opacity-50 cursor-not-allowed' : '']">
    <div class="relative flex items-center">
      <input
        type="checkbox"
        :checked="modelValue"
        :disabled="disabled"
        :required="required"
        class="sr-only"
        @change="$emit('update:modelValue', $event.target.checked)"
      />
      <div :class="[
        'w-5 h-5 rounded border-2 transition-colors flex items-center justify-center',
        modelValue
          ? 'bg-indigo-600 border-indigo-600'
          : 'bg-transparent border-gray-600 hover:border-gray-500'
      ]">
        <CheckIcon
          v-if="modelValue"
          class="w-3 h-3 text-white"
        />
      </div>
    </div>
    <div class="ml-2">
      <span class="text-sm text-gray-300">
        {{ label }}
        <span v-if="required" class="text-red-500">*</span>
      </span>
      <p v-if="description" class="text-xs text-gray-500 mt-0.5">
        {{ description }}
      </p>
    </div>
  </label>
</template>

<script setup>
import { CheckIcon } from '@heroicons/vue/24/solid'

defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  label: {
    type: String,
    default: '',
  },
  description: {
    type: String,
    default: '',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  required: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['update:modelValue'])
</script>
