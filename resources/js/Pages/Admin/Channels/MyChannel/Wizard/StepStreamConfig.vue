<template>
  <div class="space-y-6">
    <h3 class="text-lg font-semibold text-white mb-4">Stream Configuration</h3>

    <div class="bg-gray-700/30 rounded-lg p-4 border border-gray-600">
      <div class="flex items-center justify-between mb-3">
        <h4 class="text-white font-medium">Stream Source</h4>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Stream Type</label>
          <select v-model="form.stream_type"
            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            <option value="hls">HLS</option>
            <option value="rtmp">RTMP</option>
            <option value="mpegts">MPEG-TS</option>
            <option value="http">HTTP</option>
            <option value="srt">SRT</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Stream URL</label>
          <input v-model="form.stream_url" type="url"
            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500"
            placeholder="https://example.com/stream.m3u8" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Stream Key (optional)</label>
          <input v-model="form.stream_key" type="text"
            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500"
            placeholder="Used for live input mode" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Playlist Type</label>
          <select v-model="form.playlist_type"
            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            <option value="continuous">Continuous — play in order, loop</option>
            <option value="scheduled">Scheduled — time-based scheduling</option>
            <option value="mixed">Mixed — combine both modes</option>
          </select>
        </div>
      </div>
    </div>

    <div class="bg-gray-700/30 rounded-lg p-4 border border-gray-600">
      <h4 class="text-white font-medium mb-3">Playout Configuration</h4>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Playout Mode</label>
          <select v-model="form.playout_mode"
            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            <option value="playlist">Playlist</option>
            <option value="schedule">Schedule</option>
            <option value="live_input">Live Input</option>
            <option value="mixed">Mixed</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Default Duration (sec)</label>
          <input v-model.number="form.default_duration" type="number" min="0"
            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          <p class="text-xs text-gray-500 mt-1">Fallback duration when not detected</p>
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-300 mb-2">Duration Type</label>
          <select v-model="form.duration_type"
            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            <option value="continuous">Continuous</option>
            <option value="limited">Time Limited</option>
            <option value="specific">Specific Schedule</option>
          </select>
        </div>
      </div>
    </div>

    <div class="bg-gray-700/30 rounded-lg p-4 border border-gray-600">
      <h4 class="text-white font-medium mb-3">Output Settings</h4>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Resolution</label>
          <input v-model="form.output_resolution" type="text"
            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500"
            placeholder="e.g. 1920x1080" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Video Codec</label>
          <input v-model="form.video_codec" type="text"
            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500"
            placeholder="e.g. h264" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Audio Codec</label>
          <input v-model="form.audio_codec" type="text"
            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500"
            placeholder="e.g. aac" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  form: { type: Object, required: true },
})
</script>
