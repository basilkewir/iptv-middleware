<template>
  <div class="space-y-6">
    <h3 class="text-lg font-semibold text-white mb-4">Broadcast Settings</h3>

    <div>
      <label class="block text-sm font-medium text-gray-300 mb-3">Broadcast Mode</label>
      <div class="flex gap-4 flex-wrap">
        <label class="flex items-center gap-2 cursor-pointer text-gray-300">
          <input type="radio" value="24_7" v-model="form.settings.broadcast_mode"
            class="text-indigo-600" />
          <span>24/7 Continuous — plays constantly</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer text-gray-300">
          <input type="radio" value="scheduled" v-model="form.settings.broadcast_mode"
            class="text-indigo-600" />
          <span>Scheduled — set specific times</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer text-gray-300">
          <input type="radio" value="time_limited" v-model="form.settings.broadcast_mode"
            class="text-indigo-600" />
          <span>Time Limited — set start/end</span>
        </label>
      </div>
    </div>

    <div v-if="form.settings.broadcast_mode !== '24_7'" class="bg-gray-700/30 rounded-lg p-4 border border-gray-600">
      <h4 class="text-sm font-medium text-gray-300 mb-3">Schedule Settings</h4>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div v-if="form.settings.broadcast_mode === 'scheduled'">
          <label class="block text-sm font-medium text-gray-300 mb-2">Days</label>
          <div class="flex flex-wrap gap-2">
            <label v-for="d in days" :key="d.value" class="flex items-center gap-1 text-xs text-gray-300">
              <input type="checkbox" :value="d.value" v-model="schedule.days"
                class="w-3 h-3 rounded bg-gray-600 border-gray-500 text-indigo-600" />
              {{ d.label }}
            </label>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">Transition Type</label>
        <select v-model="form.transition_type"
          class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
          <option value="cut">Cut</option>
          <option value="fade">Fade</option>
          <option value="slide">Slide</option>
          <option value="dissolve">Dissolve</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">Transition Duration (sec)</label>
        <input v-model.number="form.transition_duration" type="number" min="0" max="10"
          class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">Default Quality</label>
        <select v-model="form.settings.default_quality"
          class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
          <option value="4k">4K</option>
          <option value="fhd">FHD (1080p)</option>
          <option value="hd">HD (720p)</option>
          <option value="sd">SD (480p)</option>
          <option value="low">Low</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">Timezone</label>
        <select v-model="form.settings.broadcast_timezone"
          class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
          <option value="UTC">UTC</option>
          <option value="America/New_York">America/New York</option>
          <option value="America/Los_Angeles">America/Los Angeles</option>
          <option value="Europe/London">Europe/London</option>
          <option value="Asia/Tokyo">Asia/Tokyo</option>
          <option value="Asia/Dubai">Asia/Dubai</option>
        </select>
      </div>
    </div>

    <div class="bg-gray-700/30 rounded-lg p-4 border border-gray-600">
      <h4 class="text-sm font-medium text-gray-300 mb-3">Advanced Features</h4>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <label class="flex items-center justify-between cursor-pointer text-gray-300">
          <span>Auto-adjust quality</span>
          <input type="checkbox" v-model="form.settings.auto_adjust_quality"
            class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600" />
        </label>
        <label class="flex items-center justify-between cursor-pointer text-gray-300">
          <span>DVR support</span>
          <input type="checkbox" v-model="form.settings.enable_dvr"
            class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600" />
        </label>
        <label class="flex items-center justify-between cursor-pointer text-gray-300">
          <span>Timeshift</span>
          <input type="checkbox" v-model="form.settings.enable_timeshift"
            class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600" />
        </label>
        <label class="flex items-center justify-between cursor-pointer text-gray-300">
          <span>Notify on low content</span>
          <input type="checkbox" v-model="form.settings.notify_low_content"
            class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600" />
        </label>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  form: { type: Object, required: true },
})

const days = [
  { value: 1, label: 'Mon' }, { value: 2, label: 'Tue' }, { value: 3, label: 'Wed' },
  { value: 4, label: 'Thu' }, { value: 5, label: 'Fri' }, { value: 6, label: 'Sat' }, { value: 0, label: 'Sun' },
]

const schedule = {
  days: [],
}
</script>
