<template>
  <div class="space-y-6">
    <h3 class="text-lg font-semibold text-white mb-4">Bouquet & Package Assignment</h3>

    <div class="bg-gray-700/30 rounded-lg p-4 border border-gray-600">
      <h4 class="text-sm font-medium text-gray-300 mb-3">Bouquets</h4>
      <div class="space-y-1 max-h-40 overflow-y-auto">
        <label v-for="b in bouquets" :key="b.id"
          class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
          <input type="checkbox" :value="b.id" v-model="form.bouquet_ids"
            class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600" />
          <span>{{ b.name }}</span>
        </label>
      </div>
      <p v-if="!bouquets.length" class="text-xs text-gray-400 mt-2">No bouquets available.</p>
    </div>

    <div class="bg-gray-700/30 rounded-lg p-4 border border-gray-600">
      <h4 class="text-sm font-medium text-gray-300 mb-3">Subscription Packages</h4>
      <div class="space-y-1 max-h-40 overflow-y-auto">
        <label v-for="p in packages" :key="p.id"
          class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
          <input type="checkbox" :value="p.id" v-model="form.package_ids"
            class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600" />
          <span>{{ p.name }}</span>
        </label>
      </div>
      <p v-if="!packages.length" class="text-xs text-gray-400 mt-2">No packages available.</p>
    </div>

    <div class="bg-gray-700/30 rounded-lg p-4 border border-gray-600">
      <h4 class="text-sm font-medium text-gray-300 mb-3">Access Control</h4>
      <div class="space-y-2">
        <label class="flex items-center gap-2 cursor-pointer text-gray-300">
          <input type="checkbox" v-model="form.is_public"
            class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600" />
          <span>Public — visible to all clients</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer text-gray-300">
          <input type="checkbox" v-model="form.require_subscription"
            class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600" />
          <span>Requires subscription</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer text-gray-300">
          <input type="checkbox" v-model="form.is_featured"
            class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600" />
          <span>Featured in listings</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer text-gray-300">
          <input type="checkbox" v-model="form.is_adult"
            class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600" />
          <span>18+ content (adult)</span>
        </label>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  form: { type: Object, required: true },
  bouquets: { type: Array, default: () => [] },
  packages: { type: Array, default: () => [] },
})
</script>
