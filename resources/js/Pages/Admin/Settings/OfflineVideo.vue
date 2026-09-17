<template>
  <AdminLayout>
    <div class="p-6 max-w-3xl mx-auto space-y-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">Offline Video</h1>
        <p class="text-gray-400 mt-1">
          This video plays for viewers when a channel has no active stream.
        </p>
      </div>

      <!-- Flash messages -->
      <div v-if="$page.props.flash?.success" class="flex items-center gap-3 px-4 py-3 bg-green-500/10 border border-green-500/30 rounded-lg text-green-400 text-sm">
        <CheckCircle class="w-4 h-4 shrink-0" />
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error" class="flex items-center gap-3 px-4 py-3 bg-red-500/10 border border-red-500/30 rounded-lg text-red-400 text-sm">
        <XCircle class="w-4 h-4 shrink-0" />
        {{ $page.props.flash.error }}
      </div>

      <!-- Current status -->
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        <h3 class="text-lg font-semibold text-white mb-4">Current Status</h3>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
          <div class="bg-gray-700/50 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold" :class="status.has_video ? 'text-green-400' : 'text-gray-500'">
              {{ status.has_video ? 'Yes' : 'No' }}
            </div>
            <div class="text-xs text-gray-400 mt-1">Video Uploaded</div>
          </div>
          <div class="bg-gray-700/50 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-indigo-400">
              {{ status.video_size_mb ?? '—' }}
              <span v-if="status.video_size_mb" class="text-sm font-normal">MB</span>
            </div>
            <div class="text-xs text-gray-400 mt-1">File Size</div>
          </div>
          <div class="bg-gray-700/50 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold" :class="status.hls_ready ? 'text-green-400' : 'text-gray-500'">
              {{ status.hls_ready ? 'Ready' : 'Not ready' }}
            </div>
            <div class="text-xs text-gray-400 mt-1">HLS Stream</div>
          </div>
          <div class="bg-gray-700/50 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-indigo-400">{{ status.segment_count }}</div>
            <div class="text-xs text-gray-400 mt-1">Segments</div>
          </div>
        </div>

        <div v-if="status.hls_ready" class="mt-4 flex items-center gap-2 text-sm text-gray-400">
          <span class="text-gray-500">Stream URL:</span>
          <code class="text-indigo-300 bg-gray-900 px-2 py-0.5 rounded text-xs break-all">{{ status.stream_url }}</code>
        </div>
        <div v-if="status.prepared_at" class="mt-2 text-xs text-gray-500">
          Last prepared: {{ status.prepared_at }}
        </div>
      </div>

      <!-- Upload -->
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        <h3 class="text-lg font-semibold text-white mb-1">Upload Video</h3>
        <p class="text-sm text-gray-400 mb-4">Accepted formats: MP4, MKV, MOV, AVI, WebM — max 500 MB</p>

        <form @submit.prevent="submitUpload">
          <div
            class="relative flex flex-col items-center justify-center gap-3 border-2 border-dashed rounded-xl p-8 transition cursor-pointer"
            :class="dragging ? 'border-indigo-400 bg-indigo-500/10' : 'border-gray-600 hover:border-indigo-500'"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
            @click="$refs.fileInput.click()"
          >
            <input ref="fileInput" type="file" accept="video/mp4,video/x-matroska,video/quicktime,video/x-msvideo,video/webm" class="hidden" @change="onFileChange" />
            <VideoIcon class="w-10 h-10 text-gray-500" />
            <div v-if="selectedFile" class="text-center">
              <p class="text-white font-medium">{{ selectedFile.name }}</p>
              <p class="text-gray-400 text-sm">{{ (selectedFile.size / 1048576).toFixed(1) }} MB</p>
            </div>
            <div v-else class="text-center">
              <p class="text-gray-300">Drop your video here or <span class="text-indigo-400">browse</span></p>
              <p class="text-gray-500 text-sm mt-1">Replaces the current offline video</p>
            </div>
          </div>

          <!-- Upload progress -->
          <div v-if="uploading" class="mt-4">
            <div class="flex justify-between text-sm text-gray-400 mb-1">
              <span>Uploading…</span>
              <span>{{ uploadProgress }}%</span>
            </div>
            <div class="w-full bg-gray-700 rounded-full h-2">
              <div class="bg-indigo-500 h-2 rounded-full transition-all" :style="{ width: uploadProgress + '%' }" />
            </div>
          </div>

          <div class="mt-4 flex justify-end">
            <button
              type="submit"
              :disabled="!selectedFile || uploading"
              class="flex items-center gap-2 px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-40 disabled:cursor-not-allowed"
            >
              <Upload class="w-4 h-4" />
              {{ uploading ? 'Uploading…' : 'Upload Video' }}
            </button>
          </div>
        </form>
      </div>

      <!-- Actions -->
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        <h3 class="text-lg font-semibold text-white mb-1">Actions</h3>
        <p class="text-sm text-gray-400 mb-4">
          After uploading, click <strong class="text-gray-300">Prepare HLS Stream</strong> to convert the video.
          This runs in the background and takes a few seconds.
        </p>
        <div class="flex flex-wrap gap-3">
          <form @submit.prevent="prepare">
            <button
              type="submit"
              :disabled="!status.has_video || preparing"
              class="flex items-center gap-2 px-5 py-2 bg-green-600 hover:bg-green-500 text-white rounded-lg transition disabled:opacity-40 disabled:cursor-not-allowed"
            >
              <Clapperboard class="w-4 h-4" />
              {{ preparing ? 'Preparing…' : 'Prepare HLS Stream' }}
            </button>
          </form>

          <form @submit.prevent="confirmDelete">
            <button
              type="submit"
              :disabled="!status.has_video"
              class="flex items-center gap-2 px-5 py-2 bg-red-600/80 hover:bg-red-600 text-white rounded-lg transition disabled:opacity-40 disabled:cursor-not-allowed"
            >
              <Trash2 class="w-4 h-4" />
              Remove Video
            </button>
          </form>
        </div>
      </div>

      <!-- How it works -->
      <div class="bg-gray-800/50 rounded-xl p-5 border border-gray-700/50 text-sm text-gray-400 space-y-2">
        <p class="text-gray-300 font-medium flex items-center gap-2"><Info class="w-4 h-4 text-indigo-400" /> How it works</p>
        <ol class="list-decimal list-inside space-y-1 ml-1">
          <li>Upload your "channel offline" video (MP4 recommended).</li>
          <li>Click <strong class="text-gray-300">Prepare HLS Stream</strong> — ffmpeg converts it to a looping HLS stream.</li>
          <li>Whenever a channel has no active ingest, viewers are automatically redirected to this stream.</li>
          <li>Re-prepare any time you replace the video.</li>
        </ol>
      </div>

      <!-- Delete confirmation modal -->
      <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 w-full max-w-sm mx-4 space-y-4">
          <h4 class="text-white font-semibold">Remove offline video?</h4>
          <p class="text-gray-400 text-sm">This will delete the uploaded video and the generated HLS stream. Channels that go offline will return a 503 until a new video is prepared.</p>
          <div class="flex justify-end gap-3">
            <button @click="showDeleteModal = false" class="px-4 py-2 text-gray-300 hover:text-white transition">Cancel</button>
            <form :action="route('admin.settings.offline-video.destroy')" method="POST" @submit.prevent="submitDelete">
              <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded-lg transition">Delete</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { VideoIcon, Upload, Clapperboard, Trash2, CheckCircle, XCircle, Info } from 'lucide-vue-next'

const props = defineProps({ status: Object })

const fileInput    = ref(null)
const selectedFile = ref(null)
const dragging     = ref(false)
const uploading    = ref(false)
const uploadProgress = ref(0)
const preparing    = ref(false)
const showDeleteModal = ref(false)

function onFileChange(e) {
  selectedFile.value = e.target.files[0] ?? null
}

function onDrop(e) {
  dragging.value = false
  const file = e.dataTransfer.files[0]
  if (file) selectedFile.value = file
}

function submitUpload() {
  if (!selectedFile.value) return

  const data = new FormData()
  data.append('video', selectedFile.value)

  uploading.value = true
  uploadProgress.value = 0

  router.post(route('admin.settings.offline-video.upload'), data, {
    forceFormData: true,
    onProgress: (e) => {
      if (e.percentage) uploadProgress.value = Math.round(e.percentage)
    },
    onFinish: () => {
      uploading.value = false
      uploadProgress.value = 0
      selectedFile.value = null
      if (fileInput.value) fileInput.value.value = ''
    },
  })
}

function prepare() {
  preparing.value = true
  router.post(route('admin.settings.offline-video.prepare'), {}, {
    onFinish: () => { preparing.value = false },
  })
}

function confirmDelete() {
  showDeleteModal.value = true
}

function submitDelete() {
  showDeleteModal.value = false
  router.delete(route('admin.settings.offline-video.destroy'))
}
</script>
