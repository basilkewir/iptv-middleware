<template>
  <div class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">Channel Name *</label>
        <input v-model="form.channel_name" type="text" placeholder="My Channel"
          class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
        <p v-if="form.errors.channel_name" class="text-red-400 text-xs mt-1">{{ form.errors.channel_name }}</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">Channel Number</label>
        <input v-model.number="form.channel_number" type="number" placeholder="Auto-assigned"
          class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
        <p class="text-xs text-gray-500 mt-1">Leave blank for auto-assignment</p>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
      <textarea v-model="form.description" rows="3"
        class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500 resize-none"
        placeholder="Channel description..."></textarea>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">Genre</label>
        <select v-model="form.genre"
          class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
          <option value="">Select Genre</option>
          <option value="General">General</option>
          <option value="Promotional">Promotional</option>
          <option value="Entertainment">Entertainment</option>
          <option value="News">News</option>
          <option value="Music">Music</option>
          <option value="Sports">Sports</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">Language</label>
        <select v-model="form.language"
          class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
          <option value="en">English</option>
          <option value="es">Spanish</option>
          <option value="fr">French</option>
          <option value="de">German</option>
          <option value="pt">Portuguese</option>
          <option value="ar">Arabic</option>
          <option value="hi">Hindi</option>
          <option value="ja">Japanese</option>
          <option value="zh">Chinese</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">Country</label>
        <select v-model="form.country"
          class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
          <option value="">Select Country</option>
          <option value="US">United States</option>
          <option value="GB">United Kingdom</option>
          <option value="CA">Canada</option>
          <option value="DE">Germany</option>
          <option value="FR">France</option>
          <option value="IN">India</option>
          <option value="BR">Brazil</option>
          <option value="JP">Japan</option>
          <option value="CN">China</option>
        </select>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  form: { type: Object, required: true },
})
</script>
