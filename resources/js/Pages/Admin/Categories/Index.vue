<template>
  <AdminLayout>
    <div class="p-6">
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-white">Categories</h1>
          <p class="text-gray-400 text-sm mt-1">Manage your content categories</p>
        </div>
        <Link
          :href="route('admin.categories.create')"
          class="btn-primary flex items-center gap-2"
        >
          <Plus class="w-4 h-4" />
          Create Category
        </Link>
      </div>

      <div class="card mb-6">
        <div class="flex flex-col md:flex-row gap-4">
          <div class="flex-1">
            <input
              v-model="search"
              type="text"
              placeholder="Search categories..."
              class="input-field"
              @keyup.enter="applyFilters"
            />
          </div>
          <div class="flex gap-3">
            <select v-model="filterType" class="input-field w-auto">
              <option value="">All Types</option>
              <option value="live">Live</option>
              <option value="vod">VOD</option>
              <option value="series">Series</option>
            </select>
            <select v-model="filterStatus" class="input-field w-auto">
              <option value="">All Status</option>
              <option value="1">Active</option>
              <option value="0">Inactive</option>
            </select>
            <button @click="applyFilters" class="btn-primary">
              <Search class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="text-left text-gray-400 border-b border-gray-700">
                <th class="pb-3 font-medium">Name</th>
                <th class="pb-3 font-medium">Type</th>
                <th class="pb-3 font-medium">Parent</th>
                <th class="pb-3 font-medium">Channels</th>
                <th class="pb-3 font-medium">Status</th>
                <th class="pb-3 font-medium">Order</th>
                <th class="pb-3 font-medium text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
              <tr v-if="categories.data.length === 0">
                <td colspan="7" class="py-12 text-center text-gray-500">
                  No categories found.
                </td>
              </tr>
              <tr
                v-for="category in categories.data"
                :key="category.id"
                class="hover:bg-gray-800/50"
              >
                <td class="py-4">
                  <div class="flex items-center gap-3">
                    <div
                      v-if="category.color"
                      class="w-8 h-8 rounded-lg flex items-center justify-center"
                      :style="{ backgroundColor: category.color }"
                    >
                      <Folder class="w-4 h-4 text-white" />
                    </div>
                    <div v-else class="w-8 h-8 bg-gray-700 rounded-lg flex items-center justify-center">
                      <Folder class="w-4 h-4 text-gray-400" />
                    </div>
                    <div>
                      <p class="text-white font-medium">{{ category.name }}</p>
                      <p class="text-gray-500 text-sm">{{ category.slug }}</p>
                    </div>
                  </div>
                </td>
                <td class="py-4">
                  <span
                    class="badge"
                    :class="{
                      'badge-success': category.category_type === 'live',
                      'badge-warning': category.category_type === 'vod',
                      'bg-purple-100 text-purple-800': category.category_type === 'series'
                    }"
                  >
                    {{ category.category_type }}
                  </span>
                </td>
                <td class="py-4 text-gray-400">
                  {{ category.parent?.name || '-' }}
                </td>
                <td class="py-4 text-gray-400">
                  {{ category.channels_count || 0 }}
                </td>
                <td class="py-4">
                  <button
                    @click="toggleStatus(category)"
                    class="flex items-center gap-2"
                  >
                    <div
                      class="w-10 h-5 rounded-full transition-colors"
                      :class="category.is_active ? 'bg-purple-600' : 'bg-gray-600'"
                    >
                      <div
                        class="w-4 h-4 bg-white rounded-full transition-transform mt-0.5"
                        :class="category.is_active ? 'translate-x-5' : 'translate-x-0.5'"
                      />
                    </div>
                    <span class="text-sm" :class="category.is_active ? 'text-green-400' : 'text-gray-500'">
                      {{ category.is_active ? 'Active' : 'Inactive' }}
                    </span>
                  </button>
                </td>
                <td class="py-4 text-gray-400">
                  {{ category.sort_order }}
                </td>
                <td class="py-4 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <Link
                      :href="route('admin.categories.channels', category.id)"
                      class="p-2 text-gray-400 hover:text-white hover:bg-gray-700 rounded-lg transition"
                      title="Assign Channels"
                    >
                      <Tv class="w-4 h-4" />
                    </Link>
                    <Link
                      :href="route('admin.categories.edit', category.id)"
                      class="p-2 text-gray-400 hover:text-white hover:bg-gray-700 rounded-lg transition"
                      title="Edit"
                    >
                      <Pencil class="w-4 h-4" />
                    </Link>
                    <button
                      @click="confirmDelete(category)"
                      class="p-2 text-gray-400 hover:text-red-400 hover:bg-gray-700 rounded-lg transition"
                      title="Delete"
                    >
                      <Trash2 class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="mt-4 pt-4 border-t border-gray-700">
          <Pagination
            :links="categories.links"
            @page-change="handlePageChange"
          />
        </div>
      </div>

      <Modal
        :show="showDeleteModal"
        @close="showDeleteModal = false"
        max-width="md"
      >
        <div class="p-6">
          <h3 class="text-lg font-semibold text-white mb-4">Delete Category</h3>
          <p class="text-gray-400 mb-6">
            Are you sure you want to delete <strong class="text-white">{{ categoryToDelete?.name }}</strong>?
            This will remove all channel and VOD assignments from this category.
          </p>
          <div class="flex justify-end gap-3">
            <button
              @click="showDeleteModal = false"
              class="btn-secondary"
            >
              Cancel
            </button>
            <button
              @click="deleteCategory"
              class="btn-danger"
              :disabled="deleting"
            >
              {{ deleting ? 'Deleting...' : 'Delete' }}
            </button>
          </div>
        </div>
      </Modal>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Pagination from '@/Components/Common/Pagination.vue'
import Modal from '@/Components/Common/Modal.vue'
import { Plus, Search, Folder, Pencil, Trash2, Tv } from 'lucide-vue-next'

const props = defineProps({
  categories: { type: Object, required: true },
})

const page = usePage()

const search = ref('')
const filterType = ref('')
const filterStatus = ref('')
const showDeleteModal = ref(false)
const categoryToDelete = ref(null)
const deleting = ref(false)

const applyFilters = () => {
  router.get(route('admin.categories.index'), {
    search: search.value,
    type: filterType.value,
    status: filterStatus.value,
  }, {
    preserveState: true,
    replace: true,
  })
}

const handlePageChange = (url) => {
  router.get(url, {
    search: search.value,
    type: filterType.value,
    status: filterStatus.value,
  }, {
    preserveState: true,
    replace: true,
  })
}

const toggleStatus = async (category) => {
  try {
    await router.post(route('admin.categories.toggle-status', category.id), {}, {
      preserveState: true,
    })
  } catch (error) {
    console.error('Failed to toggle status:', error)
  }
}

const confirmDelete = (category) => {
  categoryToDelete.value = category
  showDeleteModal.value = true
}

const deleteCategory = async () => {
  if (!categoryToDelete.value) return

  deleting.value = true
  try {
    await router.delete(route('admin.categories.destroy', categoryToDelete.value.id), {
      preserveState: true,
    })
    showDeleteModal.value = false
    categoryToDelete.value = null
  } catch (error) {
    console.error('Failed to delete category:', error)
  } finally {
    deleting.value = false
  }
}

const successMessage = ref('')
watch(
  () => page.props.flash?.success,
  (message) => {
    if (message) {
      successMessage.value = message
      setTimeout(() => {
        successMessage.value = ''
      }, 3000)
    }
  },
  { immediate: true }
)
</script>
