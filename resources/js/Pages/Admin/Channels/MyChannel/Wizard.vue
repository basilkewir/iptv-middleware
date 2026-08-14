<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto">
      <div class="mb-6">
        <Link :href="route('admin.admin.channels.index')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
          <ArrowLeft class="w-4 h-4" /> Back to My Channels
        </Link>
        <div class="flex items-center justify-between">
          <h1 class="text-2xl font-bold text-white">{{ editing ? 'Edit' : 'Create' }} My Channel</h1>
          <div class="text-sm text-gray-400">Step {{ currentStep }} of {{ totalSteps }}</div>
        </div>
        <div class="w-full bg-gray-700 rounded-full h-2 mt-4">
          <div class="bg-indigo-600 h-2 rounded-full transition-all"
               :style="{ width: ((currentStep - 1) / (totalSteps - 1) * 100) + '%' }"></div>
        </div>
      </div>

      <form @submit.prevent="submit" class="space-y-6">
        <StepBasicInfo v-if="currentStep === 1" :form="form" />
        <StepBranding v-else-if="currentStep === 2" :form="form" :upload-fn="uploadBrandingImage" />
        <StepContent v-else-if="currentStep === 3" :form="form" :uploading="uploading"
          :uploadProgress="uploadProgress" :handleFileUpload="handleFileUpload"
          :removeUploaded="removeUploaded" :editing="editing" />
        <StepBroadcast v-else-if="currentStep === 4" :form="form" />
        <StepOverlays v-else-if="currentStep === 5" :form="form" />
        <StepBouquetPackage v-else-if="currentStep === 6" :form="form" :bouquets="bouquets" :packages="packages" />
        <StepStreamConfig v-else-if="currentStep === 7" :form="form" />
        <StepReview v-else-if="currentStep === 8" :form="form" :editing="editing" :bouquets="bouquets" :packages="packages" />

        <div class="flex justify-between pt-6 border-t border-gray-700">
          <button v-if="currentStep > 1" type="button" @click="currentStep--"
            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">
            Back
          </button>
          <div class="flex gap-3">
            <button v-if="currentStep < totalSteps" type="button" @click="currentStep++"
              :disabled="!canProceed"
              class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50">
              Next
            </button>
            <button v-if="currentStep === totalSteps" type="submit" :disabled="form.processing"
              class="px-6 py-2 bg-green-600 hover:bg-green-500 text-white rounded-lg transition flex items-center gap-2 disabled:opacity-50">
              <Save class="w-4 h-4" v-if="!form.processing" />
              <Loader2 class="w-4 h-4 animate-spin" v-else />
              {{ editing ? 'Update Channel' : 'Create Channel' }}
            </button>
          </div>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeft, Save, Loader2, Upload } from 'lucide-vue-next'
import StepBasicInfo from './Wizard/StepBasicInfo.vue'
import StepBranding from './Wizard/StepBranding.vue'
import StepContent from './Wizard/StepContent.vue'
import StepBroadcast from './Wizard/StepBroadcast.vue'
import StepOverlays from './Wizard/StepOverlays.vue'
import StepBouquetPackage from './Wizard/StepBouquetPackage.vue'
import StepStreamConfig from './Wizard/StepStreamConfig.vue'
import StepReview from './Wizard/StepReview.vue'

const props = defineProps({
  channel: Object,
  categories: { type: Array, default: () => [] },
  bouquets: { type: Array, default: () => [] },
  packages: { type: Array, default: () => [] },
})

const editing = computed(() => !!props.channel)
const currentStep = ref(1)
const totalSteps = 8
const uploading = ref(false)
const uploadProgress = ref(0)

const form = useForm({
  channel_name: props.channel?.channel_name || '',
  channel_slug: props.channel?.channel_slug || '',
  channel_number: props.channel?.channel_number || null,
  description: props.channel?.description || '',
  channel_type: 'admin',
  is_my_channel: true,
  playlist_type: props.channel?.playlist_type || 'continuous',
  broadcast_status: props.channel?.broadcast_status || 'offline',
  license_type: props.channel?.license_type || 'free',

  logo_url: props.channel?.logo_url || '',
  banner_url: props.channel?.banner_url || '',
  background_color: props.channel?.background_color || '#1e293b',
  accent_color: props.channel?.accent_color || '#6366f1',
  text_color: props.channel?.text_color || '#ffffff',
  watermark_url: props.channel?.watermark_url || '',

  uploaded_content: [],
  playlist_order: [],

  broadcast_mode: props.channel?.broadcast_mode || 'manual',
  timezone: props.channel?.timezone || 'UTC',
  duration_type: props.channel?.duration_type || 'continuous',
  playout_mode: props.channel?.playout_mode || 'playlist',
  default_duration: props.channel?.default_duration || 0,
  loop_playlist: props.channel?.loop_playlist ?? true,
  shuffle_mode: props.channel?.shuffle_mode ?? false,
  transition_type: props.channel?.transition_type || 'cut',
  transition_duration: props.channel?.transition_duration || 2,

  enable_ticker: props.channel?.enable_ticker ?? false,
  ticker_text: props.channel?.ticker_text || '',
  ticker_speed: props.channel?.ticker_speed || 30,
  ticker_color: props.channel?.ticker_color || '#ffffff',
  ticker_background: props.channel?.ticker_background || '#000000',
  ticker_direction: props.channel?.ticker_direction || 'left',

  enable_overlay_logo: props.channel?.enable_overlay_logo ?? false,
  overlay_logo_position: props.channel?.overlay_logo_position || 'top-left',
  overlay_logo_x: props.channel?.overlay_logo_x ?? 2,
  overlay_logo_y: props.channel?.overlay_logo_y ?? 2,
  overlay_logo_size: props.channel?.overlay_logo_size || 100,
  overlay_logo_opacity: props.channel?.overlay_logo_opacity || 1.0,

  enable_overlay_clock: props.channel?.enable_overlay_clock ?? false,
  overlay_clock_position: props.channel?.overlay_clock_position || 'top-right',
  overlay_clock_x: props.channel?.overlay_clock_x ?? 90,
  overlay_clock_y: props.channel?.overlay_clock_y ?? 2,
  overlay_clock_format: props.channel?.overlay_clock_format || 'HH:MM:SS',

  enable_watermark: props.channel?.enable_watermark ?? false,
  watermark_position: props.channel?.watermark_position || 'bottom-right',
  watermark_opacity: props.channel?.watermark_opacity || 0.5,

  genre: props.channel?.genre || '',
  category: props.channel?.category || '',
  language: props.channel?.language || 'en',
  country: props.channel?.country || '',
  is_public: props.channel?.is_public ?? true,
  is_featured: props.channel?.is_featured ?? false,
  is_adult: props.channel?.is_adult ?? false,
  featured_order: props.channel?.featured_order || 0,
  require_subscription: props.channel?.require_subscription ?? false,
  stream_url: props.channel?.stream_url || '',
  stream_type: props.channel?.stream_type || 'hls',
  package_ids: (props.channel?.packages || []).map(p => p.id),
  bouquet_ids: props.channel?.bouquets ? props.channel.bouquets.map(b => b.id) : [],

  settings: {
    broadcast_mode: props.channel?.playlist_type === 'scheduled' ? 'scheduled' : '24_7',
    broadcast_timezone: props.channel?.timezone || 'UTC',
    default_transition: props.channel?.transition_type || 'cut',
    transition_duration: props.channel?.transition_duration || 2,
    buffer_between_items: 0,
    fallback_enabled: true,
    default_quality: 'hd',
    auto_adjust_quality: true,
    notify_low_content: true,
    low_content_threshold: 10,
    notify_broadcast_start: true,
    notify_broadcast_end: true,
    enable_dvr: false,
    enable_timeshift: false,
    timeshift_duration: 0,
  },
})

const canProceed = computed(() => {
  if (currentStep.value === 1) return !!form.channel_name
  return true
})

const uploadBrandingImage = async (file, field) => {
  const formData = new FormData()
  formData.append('image', file)
  formData.append('field', field)

  const token = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))
  const xsrfToken = token ? decodeURIComponent(token.split('=')[1]) : ''

  const res = await fetch(route('admin.channels.my-channel.upload-image'), {
    method: 'POST',
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json',
      'X-XSRF-TOKEN': xsrfToken,
    },
    credentials: 'same-origin',
    body: formData,
  })

  const json = await res.json()
  if (!res.ok) throw new Error(json?.message || 'Upload failed')
  return json.url
}

const handleFileUpload = async (e) => {
  const file = e.target?.files?.[0]
  if (!file) return

  uploading.value = true
  uploadProgress.value = 0

  const formData = new FormData()
  formData.append('file', file)
  formData.append('title', `Upload_${Date.now()}`)
  formData.append('description', '')

  try {
    const token = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))
    const xsrfToken = token ? decodeURIComponent(token.split('=')[1]) : ''
    const channelId = props.channel?.channel_slug || props.channel?.id || ''

    const res = await fetch(route('admin.channels.my-channel.content.upload', channelId), {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'X-XSRF-TOKEN': xsrfToken,
      },
      credentials: 'same-origin',
      body: formData,
    })

    if (res.ok) {
      const json = await res.json()
      form.uploaded_content.push(json.content)
    } else {
      const json = await res.json()
      alert(json?.message || 'Upload failed')
    }
  } catch (err) {
    alert('Upload failed')
  } finally {
    uploading.value = false
    uploadProgress.value = 0
  }
}

const removeUploaded = (index) => {
  form.uploaded_content.splice(index, 1)
}

const submit = () => {
  if (editing.value) {
    const slug = props.channel.channel_slug || props.channel.id
    form.put(`/admin/channels/admin/${slug}`)
  } else {
    form.post(route('admin.admin.channels.store'))
  }
}
</script>
