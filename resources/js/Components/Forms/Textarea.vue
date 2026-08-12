<template>
  <div>
    <label
      v-if="label"
      :for="id"
      class="block text-sm font-medium text-gray-300 mb-1"
    >
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>
    <textarea
      :id="id"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :readonly="readonly"
      :required="required"
      :rows="rows"
      :class="[
        'w-full bg-gray-800 border rounded-lg text-white placeholder-gray-500 resize-none',
        'focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent',
        'disabled:opacity-50 disabled:cursor-not-allowed',
        'transition-colors px-3 py-2.5',
        error ? 'border-red-500' : 'border-gray-700'
      ]"
      @input="$emit('update:modelValue', $event.target.value)"
    />
    <div v-if="maxLength || error || hint" class="mt-1 flex items-center justify-between">
      <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
      <p v-else-if="hint" class="text-sm text-gray-500">{{ hint }}</p>
      <p v-if="maxLength" class="text-xs text-gray-500">
        {{ modelValue?.length || 0 }} / {{ maxLength }}
      </p>
    </div>
  </div>
</template>

<script setup>
defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  label: {
    type: String,
    default: '',
  },
  id: {
    type: String,
    default: () => `textarea-${Math.random().toString(36).slice(2, 9)}`,
  },
  placeholder: {
    type: String,
    default: '',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  readonly: {
    type: Boolean,
    default: false,
  },
  required: {
    type: Boolean,
    default: false,
  },
  rows: {
    type: Number,
    default: 4,
  },
  maxLength: {
    type: Number,
    default: null,
  },
  error: {
    type: String,
    default: '',
  },
  hint: {
    type: String,
    default: '',
  },
})

defineEmits(['update:modelValue'])
</script>
