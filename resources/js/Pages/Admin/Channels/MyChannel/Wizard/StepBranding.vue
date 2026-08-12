<template>
  <div class="space-y-4">
    <h3 class="text-lg font-semibold text-white mb-4">Channel Branding</h3>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <ImageUploadField
        label="Logo"
        field="logo_url"
        hint="200x200 recommended, PNG with transparency"
        :current-url="form.logo_url"
        :upload-fn="uploadFn"
        @uploaded="form.logo_url = $event"
      />
      <ImageUploadField
        label="Banner"
        field="banner_url"
        hint="1920x1080 recommended"
        :current-url="form.banner_url"
        :upload-fn="uploadFn"
        @uploaded="form.banner_url = $event"
      />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">Background Color</label>
        <div class="flex items-center gap-2">
          <input v-model="form.background_color" type="color"
            class="w-10 h-10 p-0 border border-gray-600 rounded-lg cursor-pointer" />
          <input v-model="form.background_color" type="text"
            class="flex-1 px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm" />
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">Accent Color</label>
        <div class="flex items-center gap-2">
          <input v-model="form.accent_color" type="color"
            class="w-10 h-10 p-0 border border-gray-600 rounded-lg cursor-pointer" />
          <input v-model="form.accent_color" type="text"
            class="flex-1 px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm" />
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">Text Color</label>
        <div class="flex items-center gap-2">
          <input v-model="form.text_color" type="color"
            class="w-10 h-10 p-0 border border-gray-600 rounded-lg cursor-pointer" />
          <input v-model="form.text_color" type="text"
            class="flex-1 px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm" />
        </div>
      </div>
    </div>

    <ImageUploadField
      label="Watermark"
      field="watermark_url"
      hint="PNG with transparency recommended"
      :current-url="form.watermark_url"
      :upload-fn="uploadFn"
      @uploaded="form.watermark_url = $event"
    />
  </div>
</template>

<script setup>
import { defineAsyncComponent } from 'vue'

const ImageUploadField = defineAsyncComponent(() => import('./ImageUploadField.vue'))

const props = defineProps({
  form: { type: Object, required: true },
  uploadFn: { type: Function, required: true },
})
</script>
