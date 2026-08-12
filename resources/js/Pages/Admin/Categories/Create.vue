<template>
  <AdminLayout>
    <div class="p-6 max-w-3xl mx-auto">
      <div class="mb-6">
        <Link :href="route('admin.categories.index')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
          <ArrowLeft class="w-4 h-4" /> Back to Categories
        </Link>
        <h1 class="text-2xl font-bold text-white">Create Category</h1>
      </div>

      <form @submit.prevent="form.post(route('admin.categories.store'))" class="space-y-6">
        <div class="card">
          <h2 class="text-lg font-semibold text-white mb-4">Category Details</h2>
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-300 mb-2">Name *</label>
                <input
                  v-model="form.name"
                  type="text"
                  class="input-field"
                  @input="generateSlug"
                />
                <p v-if="form.errors.name" class="text-red-400 text-sm mt-1">{{ form.errors.name }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Slug</label>
                <input
                  v-model="form.slug"
                  type="text"
                  class="input-field"
                />
                <p v-if="form.errors.slug" class="text-red-400 text-sm mt-1">{{ form.errors.slug }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Category Type *</label>
                <select v-model="form.category_type" class="input-field">
                  <option value="live">Live</option>
                  <option value="vod">VOD</option>
                  <option value="series">Series</option>
                </select>
                <p v-if="form.errors.category_type" class="text-red-400 text-sm mt-1">{{ form.errors.category_type }}</p>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
              <textarea
                v-model="form.description"
                rows="3"
                class="input-field"
              />
              <p v-if="form.errors.description" class="text-red-400 text-sm mt-1">{{ form.errors.description }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Icon URL</label>
                <input
                  v-model="form.icon"
                  type="text"
                  class="input-field"
                  placeholder="https://example.com/icon.png"
                />
                <p v-if="form.errors.icon" class="text-red-400 text-sm mt-1">{{ form.errors.icon }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Banner Image URL</label>
                <input
                  v-model="form.banner_image"
                  type="text"
                  class="input-field"
                  placeholder="https://example.com/banner.png (1920x500 recommended)"
                />
                <p v-if="form.errors.banner_image" class="text-red-400 text-sm mt-1">{{ form.errors.banner_image }}</p>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Color</label>
                <div class="flex items-center gap-2">
                  <input
                    v-model="form.color"
                    type="color"
                    class="w-10 h-10 rounded-lg cursor-pointer border-0"
                  />
                  <input
                    v-model="form.color"
                    type="text"
                    class="input-field flex-1"
                    placeholder="#FF6B6B"
                  />
                </div>
                <p v-if="form.errors.color" class="text-red-400 text-sm mt-1">{{ form.errors.color }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Parent Category</label>
                <select v-model="form.parent_id" class="input-field">
                  <option :value="null">None</option>
                  <option
                    v-for="parent in parentCategories"
                    :key="parent.id"
                    :value="parent.id"
                  >
                    {{ parent.name }}
                  </option>
                </select>
                <p v-if="form.errors.parent_id" class="text-red-400 text-sm mt-1">{{ form.errors.parent_id }}</p>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Sort Order</label>
                <input
                  v-model.number="form.sort_order"
                  type="number"
                  min="0"
                  class="input-field"
                />
                <p v-if="form.errors.sort_order" class="text-red-400 text-sm mt-1">{{ form.errors.sort_order }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                <div class="flex items-center gap-4 mt-2">
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input
                      v-model="form.is_active"
                      type="radio"
                      :value="true"
                      class="w-4 h-4 text-purple-600 bg-gray-700 border-gray-600 focus:ring-purple-500"
                    />
                    <span class="text-gray-300">Active</span>
                  </label>
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input
                      v-model="form.is_active"
                      type="radio"
                      :value="false"
                      class="w-4 h-4 text-purple-600 bg-gray-700 border-gray-600 focus:ring-purple-500"
                    />
                    <span class="text-gray-300">Inactive</span>
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="card">
          <h2 class="text-lg font-semibold text-white mb-4">Auto-assignment</h2>
          <div class="space-y-4">
            <label class="flex items-center gap-3 cursor-pointer">
              <input
                v-model="form.auto_assign_channels"
                type="checkbox"
                class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-purple-600 focus:ring-purple-500"
              />
              <span class="text-gray-300">Auto-assign new channels to this category</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
              <input
                v-model="form.auto_assign_vod"
                type="checkbox"
                class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-purple-600 focus:ring-purple-500"
              />
              <span class="text-gray-300">Auto-assign new VOD to this category</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
              <input
                v-model="form.include_in_m3u"
                type="checkbox"
                class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-purple-600 focus:ring-purple-500"
              />
              <span class="text-gray-300">Include in M3U generation</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
              <input
                v-model="form.include_in_xmltv"
                type="checkbox"
                class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-purple-600 focus:ring-purple-500"
              />
              <span class="text-gray-300">Include in XMLTV generation</span>
            </label>
          </div>
        </div>

        <div class="flex justify-end gap-3">
          <Link :href="route('admin.categories.index')" class="btn-secondary">
            Cancel
          </Link>
          <button
            type="submit"
            :disabled="form.processing"
            class="btn-primary"
          >
            {{ form.processing ? 'Creating...' : 'Create Category' }}
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

const props = defineProps({
  parentCategories: { type: Array, default: () => [] },
})

const form = useForm({
  name: '',
  slug: '',
  description: '',
  icon: '',
  banner_image: '',
  color: '',
  parent_id: null,
  sort_order: 0,
  is_active: true,
  category_type: 'live',
  auto_assign_channels: false,
  auto_assign_vod: false,
  include_in_m3u: true,
  include_in_xmltv: true,
})

const generateSlug = () => {
  form.slug = form.name
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/(^-|-$)/g, '')
}
</script>
