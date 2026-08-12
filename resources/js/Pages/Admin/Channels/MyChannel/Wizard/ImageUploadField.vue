<template>
  <div>
    <label class="block text-sm font-medium text-gray-300 mb-2">{{ label }}</label>

    <!-- Drop zone -->
    <div
      class="relative border-2 border-dashed rounded-lg transition-colors cursor-pointer"
      :class="isDragging ? 'border-indigo-500 bg-indigo-500/10' : 'border-gray-600 hover:border-gray-500'"
      @dragover.prevent="isDragging = true"
      @dragleave="isDragging = false"
      @drop.prevent="onDrop"
      @click="fileInput.click()"
    >
      <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onSelect" />

      <!-- Preview -->
      <div v-if="preview || currentUrl" class="relative group">
        <img :src="preview || currentUrl" class="w-full max-h-40 object-contain rounded-lg p-2" />
        <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center rounded-lg">
          <span class="text-white text-sm font-medium">Click to replace</span>
        </div>
      </div>

      <!-- Empty state -->
      <div v-else class="p-6 text-center">
        <Upload class="w-8 h-8 mx-auto text-gray-500 mb-2" />
        <p class="text-sm text-gray-400">
          <span class="text-indigo-400 font-medium">Click to upload</span> or drag and drop
        </p>
        <p class="text-xs text-gray-500 mt-1">PNG, JPG, GIF up to 5MB</p>
      </div>
    </div>

    <!-- Uploading indicator -->
    <div v-if="uploading" class="mt-2 flex items-center gap-2 text-sm text-gray-400">
      <Loader2 class="w-4 h-4 animate-spin" />
      Uploading…
    </div>

    <!-- Error -->
    <p v-if="error" class="mt-1 text-xs text-red-400">{{ error }}</p>

    <p class="text-xs text-gray-500 mt-1">{{ hint }}</p>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Upload, Loader2 } from 'lucide-vue-next'

const props = defineProps({
  label: String,
  field: String,
  hint: String,
  currentUrl: String,
  uploadFn: { type: Function, required: true },
})

const emit = defineEmits(['uploaded'])

const fileInput = ref(null)
const preview = ref(null)
const uploading = ref(false)
const error = ref('')

const onSelect = (e) => processFile(e.target.files[0])
const onDrop = (e) => { isDragging.value = false; processFile(e.dataTransfer.files[0]) }

const isDragging = ref(false)

const processFile = async (file) => {
  if (!file) return
  if (!file.type.startsWith('image/')) { error.value = 'Only image files are allowed'; return }
  if (file.size > 5 * 1024 * 1024) { error.value = 'File must be under 5MB'; return }

  error.value = ''
  preview.value = URL.createObjectURL(file)
  uploading.value = true

  try {
    const url = await props.uploadFn(file, props.field)
    emit('uploaded', url)
  } catch (e) {
    error.value = e.message || 'Upload failed'
    preview.value = null
  } finally {
    uploading.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}
</script>
