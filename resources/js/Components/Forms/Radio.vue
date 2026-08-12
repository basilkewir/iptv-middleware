<template>
  <label :class="['flex items-start cursor-pointer', disabled ? 'opacity-50 cursor-not-allowed' : '']">
    <div class="relative flex items-center">
      <input
        type="radio"
        :checked="modelValue === value"
        :disabled="disabled"
        :required="required"
        :name="name"
        class="sr-only"
        @change="$emit('update:modelValue', value)"
      />
      <div :class="[
        'w-5 h-5 rounded-full border-2 transition-colors flex items-center justify-center',
        modelValue === value
          ? 'bg-indigo-600 border-indigo-600'
          : 'bg-transparent border-gray-600 hover:border-gray-500'
      ]">
        <div
          v-if="modelValue === value"
          class="w-2 h-2 bg-white rounded-full"
        />
      </div>
    </div>
    <div class="ml-2">
      <span class="text-sm text-gray-300">
        {{ label }}
      </span>
      <p v-if="description" class="text-xs text-gray-500 mt-0.5">
        {{ description }}
      </p>
    </div>
  </label>
</template>

<script setup>
defineProps({
  modelValue: {
    type: [String, Number, Boolean],
    default: '',
  },
  value: {
    type: [String, Number, Boolean],
    required: true,
  },
  label: {
    type: String,
    default: '',
  },
  description: {
    type: String,
    default: '',
  },
  name: {
    type: String,
    required: true,
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
