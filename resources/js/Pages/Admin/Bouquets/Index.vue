<template>
  <AdminLayout>
    <div class="p-6 space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-white">Bouquets</h1>
          <p class="text-gray-400 mt-1">Manage bouquets and their channel assignments</p>
        </div>
        <Link
          :href="route('admin.bouquets.create')"
          class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition flex items-center gap-2"
        >
          <Plus class="w-4 h-4" />
          Create Bouquet
        </Link>
      </div>

      <!-- Search & Filters -->
      <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
        <div class="flex flex-wrap gap-4">
          <div class="flex-1 min-w-[200px]">
            <div class="relative">
              <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
              <input
                v-model="search"
                type="text"
                placeholder="Search bouquets..."
                class="w-full pl-10 pr-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500"
              />
            </div>
          </div>
          <select
            v-model="filterStatus"
            class="px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500"
          >
            <option value="">All Status</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
        </div>
      </div>

      <!-- Bouquet List -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="bouquet in bouquets?.data || []"
          :key="bouquet.id"
          class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden hover:border-gray-600 transition"
        >
          <div class="p-4">
            <div class="flex items-center justify-between mb-2">
              <h3 class="text-white font-semibold">{{ bouquet.name }}</h3>
              <span
                class="px-2 py-1 text-xs rounded-full"
                :class="
                  bouquet.is_active
                    ? 'bg-green-500/20 text-green-400'
                    : 'bg-red-500/20 text-red-400'
                "
              >
                {{ bouquet.is_active ? 'Active' : 'Inactive' }}
              </span>
            </div>

            <p v-if="bouquet.description" class="text-gray-400 text-sm mb-3 line-clamp-2">
              {{ bouquet.description }}
            </p>

            <div class="flex items-center gap-2 text-xs text-gray-500 mb-3">
              <span v-if="bouquet.parent" class="px-2 py-1 bg-gray-700 rounded">
                Parent: {{ bouquet.parent.name }}
              </span>
              <span v-if="bouquet.category" class="px-2 py-1 bg-gray-700 rounded">
                {{ bouquet.category.name }}
              </span>
              <span class="px-2 py-1 bg-gray-700 rounded">
                {{ bouquet.channels_count ?? 0 }} channels
              </span>
            </div>

            <div class="flex items-center gap-2">
              <Link
                :href="route('admin.bouquets.show', bouquet.id)"
                class="flex-1 px-3 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg text-sm transition text-center"
              >
                Manage Channels
              </Link>
              <button
                @click="toggleStatus(bouquet)"
                class="px-3 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg text-sm transition"
                :title="bouquet.is_active ? 'Deactivate' : 'Activate'"
              >
                {{ bouquet.is_active ? 'Deactivate' : 'Activate' }}
              </button>
              <button
                @click="deleteBouquet(bouquet)"
                class="px-3 py-2 bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white rounded-lg text-sm transition"
                title="Delete"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <p v-if="(bouquets?.data || []).length === 0" class="text-gray-500 text-center py-12">
        No bouquets found. Create your first bouquet to get started.
      </p>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Search, Plus, Trash2 } from 'lucide-vue-next'

const props = defineProps({
  bouquets: Object,
  categories: Array,
})

const search = ref('')
const filterStatus = ref('')

let searchTimeout = null
watch(search, (val) => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    router.get(
      route('admin.bouquets.index'),
      { search: val, status: filterStatus.value || undefined },
      { preserveState: true, replace: true }
    )
  }, 300)
})

watch(filterStatus, (val) => {
  router.get(
    route('admin.bouquets.index'),
    { search: search.value, status: val || undefined },
    { preserveState: true, replace: true }
  )
})

const toggleStatus = (bouquet) => {
  router.post(route('admin.bouquets.toggle-status', bouquet.id))
}

const deleteBouquet = (bouquet) => {
  if (confirm(`Delete bouquet "${bouquet.name}"? This action cannot be undone.`)) {
    router.delete(route('admin.bouquets.destroy', bouquet.id))
  }
}
</script>
