<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto">
      <div class="mb-6">
        <Link :href="route('admin.categories.index')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
          <ArrowLeft class="w-4 h-4" /> Back to Categories
        </Link>
        <h1 class="text-2xl font-bold text-white">Manage Category: {{ category.name }}</h1>
      </div>

      <div class="card mb-6">
        <h2 class="text-lg font-semibold text-white mb-4">Available Channels</h2>
        <div class="mb-4">
          <input
            v-model="search"
            type="text"
            placeholder="Search channels..."
            class="input-field"
            @keyup.enter="applyFilters"
          />
        </div>

        <div class="mb-4 flex items-center justify-between">
          <div class="flex items-center gap-4">
            <label class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
              <input
                v-model="selectAll"
                type="checkbox"
                class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-purple-600 focus:ring-purple-500"
                @change="toggleSelectAll"
              />
              Select All
            </label>
            <span class="text-gray-500 text-sm">
              {{ selectedChannels.length }} selected
            </span>
          </div>
          <div class="flex items-center gap-2">
            <select v-model="perPage" class="input-field w-auto" @change="applyFilters">
              <option value="20">20 per page</option>
              <option value="50">50 per page</option>
              <option value="100">100 per page</option>
            </select>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="text-left text-gray-400 border-b border-gray-700">
                <th class="pb-3 font-medium w-10"></th>
                <th class="pb-3 font-medium">Channel Name</th>
                <th class="pb-3 font-medium">Type</th>
                <th class="pb-3 font-medium">Status</th>
                <th class="pb-3 font-medium">Current Categories</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
              <tr v-if="channels.data.length === 0">
                <td colspan="5" class="py-12 text-center text-gray-500">
                  No channels found.
                </td>
              </tr>
              <tr
                v-for="channel in channels.data"
                :key="channel.id"
                class="hover:bg-gray-800/50"
              >
                <td class="py-3">
                  <input
                    v-model="selectedChannels"
                    type="checkbox"
                    :value="channel.id"
                    class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-purple-600 focus:ring-purple-500"
                  />
                </td>
                <td class="py-3">
                  <div class="flex items-center gap-3">
                    <div v-if="channel.logo_url" class="w-8 h-8 rounded-lg overflow-hidden bg-gray-700">
                      <img :src="channel.logo_url" :alt="channel.name" class="w-full h-full object-cover" />
                    </div>
                    <div v-else class="w-8 h-8 bg-gray-700 rounded-lg flex items-center justify-center">
                      <Tv class="w-4 h-4 text-gray-400" />
                    </div>
                    <span class="text-white">{{ channel.name }}</span>
                  </div>
                </td>
                <td class="py-3">
                  <span class="badge badge-success">{{ channel.stream_type }}</span>
                </td>
                <td class="py-3">
                  <span
                    class="badge"
                    :class="channel.is_active ? 'badge-success' : 'bg-gray-100 text-gray-800'"
                  >
                    {{ channel.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td class="py-3">
                  <div class="flex flex-wrap gap-1">
                    <span
                      v-for="cat in channel.categories"
                      :key="cat.id"
                      class="badge"
                      :class="cat.id === category.id ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'"
                    >
                      {{ cat.name }}
                    </span>
                    <span v-if="channel.categories.length === 0" class="text-gray-500 text-sm">No categories</span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="mt-4 pt-4 border-t border-gray-700">
          <Pagination
            :links="channels.links"
            @page-change="handlePageChange"
          />
        </div>
      </div>

      <div class="card">
        <h2 class="text-lg font-semibold text-white mb-4">Bulk Actions</h2>
        <div class="flex items-center gap-4">
          <button
            @click="selectAllChannels"
            class="btn-secondary"
          >
            Select All
          </button>
          <button
            @click="deselectAllChannels"
            class="btn-secondary"
          >
            Deselect All
          </button>
          <button
            @click="assignSelectedChannels"
            :disabled="selectedChannels.length === 0 || assigning"
            class="btn-primary"
          >
            {{ assigning ? 'Assigning...' : 'Add Selected' }}
          </button>
          <button
            @click="removeSelectedChannels"
            :disabled="selectedChannels.length === 0 || removing"
            class="btn-danger"
          >
            {{ removing ? 'Removing...' : 'Remove Selected' }}
          </button>
        </div>
      </div>

      <div class="mt-6 flex justify-end gap-3">
        <Link :href="route('admin.categories.index')" class="btn-secondary">
          Cancel
        </Link>
        <Link :href="route('admin.categories.edit', category.id)" class="btn-primary">
          Save Changes
        </Link>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Pagination from '@/Components/Common/Pagination.vue'
import { ArrowLeft, Tv } from 'lucide-vue-next'

const props = defineProps({
  category: { type: Object, required: true },
  channels: { type: Object, required: true },
})

const search = ref('')
const perPage = ref('20')
const selectedChannels = ref([])
const assigning = ref(false)
const removing = ref(false)

const selectAll = computed({
  get() {
    return props.channels.data.length > 0 && selectedChannels.value.length === props.channels.data.length
  },
  set(value) {
    if (value) {
      selectedChannels.value = props.channels.data.map(c => c.id)
    } else {
      selectedChannels.value = []
    }
  }
})

const toggleSelectAll = () => {
  if (selectAll.value) {
    selectedChannels.value = props.channels.data.map(c => c.id)
  } else {
    selectedChannels.value = []
  }
}

const selectAllChannels = () => {
  selectedChannels.value = props.channels.data.map(c => c.id)
}

const deselectAllChannels = () => {
  selectedChannels.value = []
}

const applyFilters = () => {
  router.get(route('admin.categories.channels', props.category.id), {
    search: search.value,
    per_page: perPage.value,
  }, {
    preserveState: true,
    replace: true,
  })
}

const handlePageChange = (url) => {
  router.get(url, {
    search: search.value,
    per_page: perPage.value,
  }, {
    preserveState: true,
    replace: true,
  })
}

const assignSelectedChannels = async () => {
  if (selectedChannels.value.length === 0) return

  assigning.value = true
  try {
    await router.post(route('admin.categories.assign-channels', props.category.id), {
      channel_ids: selectedChannels.value,
    }, {
      preserveState: true,
      preserveScroll: true,
    })
    selectedChannels.value = []
  } catch (error) {
    console.error('Failed to assign channels:', error)
  } finally {
    assigning.value = false
  }
}

const removeSelectedChannels = async () => {
  if (selectedChannels.value.length === 0) return

  removing.value = true
  try {
    await router.post(route('admin.categories.remove-channels', props.category.id), {
      channel_ids: selectedChannels.value,
    }, {
      preserveState: true,
      preserveScroll: true,
    })
    selectedChannels.value = []
  } catch (error) {
    console.error('Failed to remove channels:', error)
  } finally {
    removing.value = false
  }
}
</script>
