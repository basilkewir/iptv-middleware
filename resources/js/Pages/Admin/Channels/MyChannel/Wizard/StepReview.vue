<template>
  <div class="space-y-6">
    <h3 class="text-lg font-semibold text-white mb-4">Review & Publish</h3>

    <div class="space-y-4">
      <div class="bg-gray-700/30 rounded-lg p-4 border border-gray-600">
        <h4 class="text-sm font-medium text-gray-300 mb-3">Channel Identity</h4>
        <div class="grid grid-cols-2 gap-2 text-sm">
          <div><span class="text-gray-500">Name:</span> <span class="text-white">{{ form.channel_name }}</span></div>
          <div><span class="text-gray-500">Number:</span> <span class="text-white">{{ form.channel_number || 'Auto' }}</span></div>
          <div><span class="text-gray-500">Genre:</span> <span class="text-white">{{ form.genre || '—' }}</span></div>
          <div><span class="text-gray-500">Language:</span> <span class="text-white">{{ form.language }}</span></div>
          <div><span class="text-gray-500">Country:</span> <span class="text-white">{{ form.country || '—' }}</span></div>
          <div><span class="text-gray-500">Playlist Type:</span> <span class="text-white capitalize">{{ form.playlist_type }}</span></div>
        </div>
      </div>

      <div class="bg-gray-700/30 rounded-lg p-4 border border-gray-600">
        <h4 class="text-sm font-medium text-gray-300 mb-3">Uploaded Content</h4>
        <div v-if="form.uploaded_content.length" class="space-y-1">
          <div v-for="(item, i) in form.uploaded_content" :key="item.id || i" class="text-sm text-gray-300">
            {{ i + 1 }}. {{ item.title || item.file_name }} ({{ item.quality_level }})
          </div>
        </div>
        <p v-else class="text-sm text-gray-500">No content uploaded. Channel will have an empty playlist.</p>
      </div>

      <div class="bg-gray-700/30 rounded-lg p-4 border border-gray-600">
        <h4 class="text-sm font-medium text-gray-300 mb-3">Broadcast</h4>
        <div class="grid grid-cols-2 gap-2 text-sm">
          <div><span class="text-gray-500">Mode:</span> <span class="text-white capitalize">{{ form.settings.broadcast_mode }}</span></div>
          <div><span class="text-gray-500">Transition:</span> <span class="text-white">{{ form.transition_type }}</span></div>
          <div><span class="text-gray-500">Loop:</span> <span class="text-white">{{ form.loop_playlist ? 'Yes' : 'No' }}</span></div>
          <div><span class="text-gray-500">Shuffle:</span> <span class="text-white">{{ form.shuffle_mode ? 'Yes' : 'No' }}</span></div>
        </div>
      </div>

      <div class="bg-gray-700/30 rounded-lg p-4 border border-gray-600">
        <h4 class="text-sm font-medium text-gray-300 mb-3">Overlays</h4>
        <div class="flex flex-wrap gap-2">
          <span v-if="form.enable_ticker" class="px-2 py-1 bg-blue-500/20 text-blue-400 rounded text-xs">Ticker</span>
          <span v-if="form.enable_overlay_logo" class="px-2 py-1 bg-purple-500/20 text-purple-400 rounded text-xs">Logo</span>
          <span v-if="form.enable_watermark" class="px-2 py-1 bg-gray-500/20 text-gray-400 rounded text-xs">Watermark</span>
          <span v-if="form.enable_overlay_clock" class="px-2 py-1 bg-green-500/20 text-green-400 rounded text-xs">Clock</span>
          <span v-if="!form.enable_ticker && !form.enable_overlay_logo && !form.enable_watermark && !form.enable_overlay_clock"
            class="text-xs text-gray-400">No overlays configured</span>
        </div>
      </div>

      <div class="bg-gray-700/30 rounded-lg p-4 border border-gray-600">
        <h4 class="text-sm font-medium text-gray-300 mb-3">Access Control</h4>
        <div class="grid grid-cols-2 gap-2 text-sm">
          <div><span class="text-gray-500">Public:</span> <span class="text-white">{{ form.is_public ? 'Yes' : 'No' }}</span></div>
          <div><span class="text-gray-500">Subscription required:</span> <span class="text-white">{{ form.require_subscription ? 'Yes' : 'No' }}</span></div>
          <div><span class="text-gray-500">Featured:</span> <span class="text-white">{{ form.is_featured ? 'Yes' : 'No' }}</span></div>
          <div><span class="text-gray-500">Adult content:</span> <span class="text-white">{{ form.is_adult ? 'Yes' : 'No' }}</span></div>
          <div><span class="text-gray-500">Bouquets:</span> <span class="text-white">{{ form.bouquet_ids.length }} assigned</span></div>
          <div><span class="text-gray-500">Packages:</span> <span class="text-white">{{ form.package_ids.length }} assigned</span></div>
        </div>
      </div>
    </div>

    <div v-if="editing" class="bg-blue-900/20 border border-blue-700/30 rounded-lg p-4">
      <p class="text-blue-300 text-sm">
        You are editing an existing channel. Changes will take effect immediately upon saving.
      </p>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  form: { type: Object, required: true },
  editing: { type: Boolean, default: false },
})
</script>
