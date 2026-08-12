<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto">
      <div class="mb-6">
        <Link :href="route('admin.transcoding.index')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
          <ArrowLeft class="w-4 h-4" /> Back to Transcoding
        </Link>
        <h1 class="text-2xl font-bold text-white">Create Transcoding Profile</h1>
      </div>

      <form @submit.prevent="form.post(route('admin.transcoding.store'))" class="space-y-6">
        <!-- Profile Details -->
        <div class="card">
          <h2 class="text-lg font-semibold text-white mb-4">Profile Details</h2>
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Profile Name *</label>
                <input v-model="form.name" type="text" class="input-field" placeholder="HD Profile" />
                <p v-if="form.errors.name" class="text-red-400 text-sm mt-1">{{ form.errors.name }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Profile Type</label>
                <select v-model="form.profile_type" class="input-field">
                  <option value="video">Video</option>
                  <option value="audio">Audio</option>
                  <option value="both">Both</option>
                </select>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
              <textarea v-model="form.description" rows="2" class="input-field" placeholder="Profile description..." />
            </div>
          </div>
        </div>

        <!-- Video Settings -->
        <div class="card">
          <h2 class="text-lg font-semibold text-white mb-4">Video Settings</h2>
          <div class="space-y-4">
            <div class="grid grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Resolution</label>
                <select v-model="form.resolution" class="input-field">
                  <option value="3840x2160">4K (3840x2160)</option>
                  <option value="1920x1080">1080p (1920x1080)</option>
                  <option value="1280x720">720p (1280x720)</option>
                  <option value="854x480">480p (854x480)</option>
                  <option value="640x360">360p (640x360)</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Video Codec</label>
                <select v-model="form.video_codec" class="input-field">
                  <option value="h264">H264</option>
                  <option value="h265">H265 (HEVC)</option>
                  <option value="vp9">VP9</option>
                  <option value="av1">AV1</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Bitrate (kbps)</label>
                <input v-model.number="form.bitrate" type="number" class="input-field" placeholder="5000" />
              </div>
            </div>
            <div class="grid grid-cols-4 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Frame Rate</label>
                <select v-model.number="form.frame_rate" class="input-field">
                  <option :value="24">24 fps</option>
                  <option :value="25">25 fps</option>
                  <option :value="30">30 fps</option>
                  <option :value="60">60 fps</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Pixel Format</label>
                <select v-model="form.pixel_format" class="input-field">
                  <option value="yuv420p">yuv420p</option>
                  <option value="yuv444p">yuv444p</option>
                  <option value="yuv420p10le">yuv420p10le</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Profile</label>
                <select v-model="form.profile" class="input-field">
                  <option value="baseline">Baseline</option>
                  <option value="main">Main</option>
                  <option value="high">High</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Preset</label>
                <select v-model="form.preset" class="input-field">
                  <option value="ultrafast">Ultrafast</option>
                  <option value="superfast">Superfast</option>
                  <option value="veryfast">Veryfast</option>
                  <option value="faster">Faster</option>
                  <option value="fast">Fast</option>
                  <option value="medium" selected>Medium</option>
                  <option value="slow">Slow</option>
                  <option value="slower">Slower</option>
                  <option value="veryslow">Veryslow</option>
                </select>
              </div>
            </div>
            <div class="grid grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Level</label>
                <input v-model="form.level" type="text" class="input-field" placeholder="4.1" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Tune</label>
                <select v-model="form.tune" class="input-field">
                  <option value="">None</option>
                  <option value="film">Film</option>
                  <option value="animation">Animation</option>
                  <option value="grain">Grain</option>
                  <option value="stillimage">Still Image</option>
                  <option value="fastdecode">Fast Decode</option>
                  <option value="zerolatency">Zero Latency</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">CRF Value (18-28)</label>
                <input v-model.number="form.crf" type="number" min="18" max="28" class="input-field" placeholder="23" />
              </div>
            </div>
          </div>
        </div>

        <!-- Audio Settings -->
        <div class="card">
          <h2 class="text-lg font-semibold text-white mb-4">Audio Settings</h2>
          <div class="grid grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Audio Codec</label>
              <select v-model="form.audio_codec" class="input-field">
                <option value="aac">AAC</option>
                <option value="mp3">MP3</option>
                <option value="opus">Opus</option>
                <option value="flac">FLAC</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Bitrate (kbps)</label>
              <input v-model.number="form.audio_bitrate" type="number" class="input-field" placeholder="128" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Sample Rate</label>
              <select v-model.number="form.sample_rate" class="input-field">
                <option :value="22050">22050 Hz</option>
                <option :value="44100">44100 Hz</option>
                <option :value="48000">48000 Hz</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Channels</label>
              <select v-model.number="form.channels" class="input-field">
                <option :value="1">Mono (1)</option>
                <option :value="2">Stereo (2)</option>
                <option :value="6">5.1 (6)</option>
                <option :value="8">7.1 (8)</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Hardware Acceleration -->
        <div class="card">
          <h2 class="text-lg font-semibold text-white mb-4">Hardware Acceleration</h2>
          <div class="space-y-4">
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="form.gpu_acceleration" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-purple-600" />
              <span class="text-gray-300">Use GPU Acceleration</span>
            </label>
            <div v-if="form.gpu_acceleration" class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">GPU Type</label>
                <select v-model="form.gpu_type" class="input-field">
                  <option value="nvenc">NVIDIA (NVENC)</option>
                  <option value="qsv">Intel (QSV)</option>
                  <option value="vaapi">AMD (VAAPI)</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-3">
          <Link :href="route('admin.transcoding.index')" class="btn-secondary">Cancel</Link>
          <button type="submit" :disabled="form.processing" class="btn-primary">
            {{ form.processing ? 'Creating...' : 'Create Profile' }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeft } from 'lucide-vue-next'

const form = useForm({
  name: '',
  description: '',
  profile_type: 'video',
  resolution: '1920x1080',
  video_codec: 'h264',
  bitrate: 5000,
  frame_rate: 30,
  pixel_format: 'yuv420p',
  profile: 'high',
  level: '4.1',
  preset: 'medium',
  tune: '',
  crf: 23,
  audio_codec: 'aac',
  audio_bitrate: 128,
  sample_rate: 48000,
  channels: 2,
  gpu_acceleration: false,
  gpu_type: 'nvenc',
  is_active: true,
})
</script>
