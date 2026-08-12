<template>
  <div>
    <label
      v-if="label"
      class="block text-sm font-medium text-gray-300 mb-1"
    >
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>

    <!-- Drop Zone -->
    <div
      :class="[
        'relative border-2 border-dashed rounded-lg transition-colors',
        isDragging ? 'border-indigo-500 bg-indigo-500/10' : 'border-gray-700 hover:border-gray-600',
        error ? 'border-red-500' : ''
      ]"
      @dragover.prevent="isDragging = true"
      @dragleave="isDragging = false"
      @drop.prevent="onDrop"
    >
      <input
        ref="fileInput"
        type="file"
        :accept="accept"
        :multiple="multiple"
        :disabled="disabled"
        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
        @change="onFileSelect"
      />

      <div class="p-6 text-center">
        <!-- Preview -->
        <div v-if="previewUrl" class="mb-4">
          <img
            v-if="isImage"
            :src="previewUrl"
            alt="Preview"
            class="max-h-40 mx-auto rounded-lg"
          />
          <div v-else class="flex items-center justify-center space-x-2 text-gray-400">
            <DocumentIcon class="w-8 h-8" />
            <span class="text-sm">{{ fileName }}</span>
          </div>
        </div>

        <!-- Upload Icon -->
        <div v-else class="mb-4">
          <CloudArrowUpIcon class="w-12 h-12 mx-auto text-gray-500" />
        </div>

        <!-- Text -->
        <p class="text-sm text-gray-400">
          <span class="text-indigo-400 font-medium">Click to upload</span>
          or drag and drop
        </p>
        <p class="mt-1 text-xs text-gray-500">
          {{ acceptText }}
        </p>
      </div>
    </div>

    <!-- Remove Button -->
    <div v-if="modelValue" class="mt-2 flex items-center justify-between">
      <span class="text-sm text-gray-400 truncate">{{ fileName }}</span>
      <button
        @click="removeFile"
        class="text-sm text-red-400 hover:text-red-300"
      >
        Remove
      </button>
    </div>

    <p v-if="error" class="mt-1 text-sm text-red-500">{{ error }}</p>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { CloudArrowUpIcon, DocumentIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  modelValue: {
    type: [File, null],
    default: null,
  },
  label: {
    type: String,
    default: '',
  },
  accept: {
    type: String,
    default: 'image/*',
  },
  multiple: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  required: {
    type: Boolean,
    default: false,
  },
  maxSize: {
    type: Number,
    default: 5 * 1024 * 1024, // 5MB
  },
  error: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['update:modelValue', 'error'])

const fileInput = ref(null)
const isDragging = ref(false)
const previewUrl = ref(null)

const isImage = computed(() => {
  if (!props.modelValue) return false
  return props.modelValue.type?.startsWith('image/')
})

const fileName = computed(() => {
  return props.modelValue?.name || ''
})

const acceptText = computed(() => {
  const types = props.accept.split(',').map(t => t.trim())
  if (types.includes('image/*')) return 'PNG, JPG, GIF up to 5MB'
  if (types.includes('video/*')) return 'MP4, WebM up to 100MB'
  return types.join(', ')
})

const onFileSelect = (event) => {
  const file = event.target.files[0]
  if (file) processFile(file)
}

const onDrop = (event) => {
  isDragging.value = false
  const file = event.dataTransfer.files[0]
  if (file) processFile(file)
}

const processFile = (file) => {
  if (file.size > props.maxSize) {
    emit('error', `File size exceeds ${formatSize(props.maxSize)}`)
    return
  }

  emit('update:modelValue', file)

  if (file.type.startsWith('image/')) {
    const reader = new FileReader()
    reader.onload = (e) => {
      previewUrl.value = e.target.result
    }
    reader.readAsDataURL(file)
  }
}

const removeFile = () => {
  emit('update:modelValue', null)
  previewUrl.value = null
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

const formatSize = (bytes) => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}
</script>
