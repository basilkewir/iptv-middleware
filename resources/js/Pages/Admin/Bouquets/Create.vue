<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto">
      <!-- Header -->
      <div class="mb-6">
        <Link :href="route('admin.bouquets.index')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
          <ArrowLeft class="w-4 h-4" /> Back to Bouquets
        </Link>
        <h1 class="text-2xl font-bold text-white">Create Bouquet</h1>
      </div>

      <form @submit.prevent="form.post(route('admin.bouquets.store'))" class="space-y-6">
        <!-- Basic Information -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h2 class="text-lg font-semibold text-white mb-4">Basic Information</h2>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Name -->
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-300 mb-2">
                Name <span class="text-red-500">*</span>
              </label>
              <input v-model="form.name" type="text" placeholder="Enter bouquet name..."
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              <p v-if="form.errors.name" class="text-red-400 text-sm mt-1">{{ form.errors.name }}</p>
            </div>

            <!-- Description -->
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
              <textarea v-model="form.description" rows="2" placeholder="Enter a description..."
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500 resize-none" />
            </div>

            <!-- Icon/Logo -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Icon / Logo</label>
              <FileUpload v-model="form.icon" :label="form.icon ? undefined : 'Upload Image'" accept="image/*" :max-size="5 * 1024 * 1024" />
              <p class="text-xs text-gray-500 mt-1">PNG, JPG, SVG up to 5MB</p>
            </div>

            <!-- Parent Bouquet -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Parent Bouquet</label>
              <select v-model="form.parent_id"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option :value="null">None</option>
                <option v-for="bouquet in parentBouquets" :key="bouquet.id" :value="bouquet.id">
                  {{ bouquet.name }}
                </option>
              </select>
            </div>

            <!-- Sort Order -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Sort Order</label>
              <input v-model.number="form.sort_order" type="number" min="0" placeholder="0"
                class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>

            <!-- Status -->
            <div class="flex items-center gap-4 pt-6">
              <span class="text-sm font-medium text-gray-300">Status:</span>
              <label class="flex items-center gap-2">
                <input type="radio" name="is_active" :value="true" v-model="form.is_active"
                  class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 focus:ring-indigo-500" />
                <span class="text-gray-300">Active</span>
              </label>
              <label class="flex items-center gap-2">
                <input type="radio" name="is_active" :value="false" v-model="form.is_active"
                  class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 focus:ring-indigo-500" />
                <span class="text-gray-300">Inactive</span>
              </label>
            </div>
          </div>
        </div>

        <!-- Channel Selection -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h2 class="text-lg font-semibold text-white mb-4">Channel Selection</h2>
          <ChannelMultiSelect v-model="form.channel_ids" :channels="channels" />
          <p v-if="form.errors.channel_ids" class="text-red-400 text-sm mt-1">{{ form.errors.channel_ids }}</p>
        </div>

        <!-- VOD Content Selection -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h2 class="text-lg font-semibold text-white mb-4">VOD Content Selection</h2>
          <VODMultiSelect v-model="form.vod_ids" :vod-content="vodContent" />
          <p v-if="form.errors.vod_ids" class="text-red-400 text-sm mt-1">{{ form.errors.vod_ids }}</p>
        </div>

        <!-- Package Assignment -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h2 class="text-lg font-semibold text-white mb-4">Package Assignment</h2>
          <div v-if="packages.length === 0" class="text-gray-500 text-sm py-4">No packages available.</div>
          <div v-else class="space-y-2">
            <label v-for="pkg in packages" :key="pkg.id"
              class="flex items-center gap-2 text-gray-300 text-sm p-2 hover:bg-gray-700 rounded cursor-pointer">
              <input type="checkbox" :value="pkg.id" v-model="form.package_ids"
                class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600 focus:ring-indigo-500" />
              <span class="flex-1">{{ pkg.name }}</span>
              <span class="text-xs text-gray-500">${{ pkg.price }}</span>
            </label>
          </div>
          <p v-if="form.errors.package_ids" class="text-red-400 text-sm mt-1">{{ form.errors.package_ids }}</p>
        </div>

        <!-- Form Actions -->
        <div class="flex justify-end gap-3">
          <Link :href="route('admin.bouquets.index')"
            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">
            Cancel
          </Link>
          <button type="submit" :disabled="form.processing"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50 flex items-center gap-2">
            <Plus v-if="!form.processing" class="w-4 h-4" />
            <span>{{ form.processing ? 'Creating...' : 'Create Bouquet' }}</span>
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
import ChannelMultiSelect from '@/Components/Bouquet/ChannelMultiSelect.vue'
import VODMultiSelect from '@/Components/Bouquet/VODMultiSelect.vue'
import FileUpload from '@/Components/Forms/FileUpload.vue'
import { ArrowLeft, Plus } from 'lucide-vue-next'

const props = defineProps({
  parentBouquets: { type: Array, default: () => [] },
  channels: { type: Array, default: () => [] },
  vodContent: { type: Array, default: () => [] },
  packages: { type: Array, default: () => [] },
})

const form = useForm({
  name: '',
  description: '',
  icon: null,
  parent_id: null,
  sort_order: 0,
  is_active: true,
  channel_ids: [],
  vod_ids: [],
  package_ids: [],
})
</script>
