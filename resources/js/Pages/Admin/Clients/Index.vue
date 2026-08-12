<template>
  <AdminLayout>
    <div class="p-6">
      <div class="flex justify-between items-center mb-6">
        <div>
          <h1 class="text-2xl font-bold text-white">Client Management</h1>
          <p class="text-gray-400 text-sm mt-1">Manage IPTV client accounts</p>
        </div>
        <div class="flex gap-3">
          <Link :href="route('admin.clients.bulkImportForm')" class="btn-secondary">
            Bulk Import
          </Link>
          <Link :href="route('admin.clients.create')" class="btn-primary">
            + Create Client
          </Link>
        </div>
      </div>

      <!-- Filters -->
      <div class="card mb-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Search</label>
            <input
              v-model="filters.search"
              type="text"
              placeholder="Username or email..."
              class="input-field"
              @input="debouncedSearch"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Package</label>
            <select v-model="filters.package_id" class="input-field" @change="applyFilters">
              <option value="">All Packages</option>
              <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">{{ pkg.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Status</label>
            <select v-model="filters.status" class="input-field" @change="applyFilters">
              <option value="">All Status</option>
              <option value="active">Active</option>
              <option value="expired">Expired</option>
              <option value="suspended">Suspended</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Reseller</label>
            <select v-model="filters.reseller_id" class="input-field" @change="applyFilters">
              <option value="">All Resellers</option>
              <option v-for="r in resellers" :key="r.id" :value="r.id">{{ r.username }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Expiry Until</label>
            <input v-model="filters.expiry_to" type="date" class="input-field" @change="applyFilters" />
          </div>
        </div>
      </div>

      <!-- Bulk Actions Bar -->
      <div v-if="selectedIds.length > 0" class="card mb-4 bg-purple-900/20 border-purple-500/30">
        <div class="flex items-center justify-between">
          <span class="text-white text-sm">{{ selectedIds.length }} clients selected</span>
          <div class="flex gap-2">
            <button @click="bulkAction('activate')" class="px-3 py-1.5 bg-green-600 hover:bg-green-500 text-white rounded text-sm">Activate</button>
            <button @click="bulkAction('suspend')" class="px-3 py-1.5 bg-yellow-600 hover:bg-yellow-500 text-white rounded text-sm">Suspend</button>
            <button @click="bulkAction('delete')" class="px-3 py-1.5 bg-red-600 hover:bg-red-500 text-white rounded text-sm">Delete</button>
          </div>
        </div>
      </div>

      <!-- Clients Table -->
      <div class="card overflow-hidden">
        <table class="w-full">
          <thead>
            <tr class="border-b border-gray-700">
              <th class="p-3 text-left">
                <input type="checkbox" @change="toggleSelectAll" :checked="allSelected" class="w-4 h-4 rounded bg-gray-600" />
              </th>
              <th class="p-3 text-left text-sm font-medium text-gray-400">Username</th>
              <th class="p-3 text-left text-sm font-medium text-gray-400">Email</th>
              <th class="p-3 text-left text-sm font-medium text-gray-400">Package</th>
              <th class="p-3 text-left text-sm font-medium text-gray-400">Status</th>
              <th class="p-3 text-left text-sm font-medium text-gray-400">Expiry</th>
              <th class="p-3 text-left text-sm font-medium text-gray-400">Connections</th>
              <th class="p-3 text-right text-sm font-medium text-gray-400">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="client in clients.data" :key="client.id" class="border-b border-gray-700/50 hover:bg-gray-700/30">
              <td class="p-3">
                <input type="checkbox" :value="client.id" v-model="selectedIds" class="w-4 h-4 rounded bg-gray-600" />
              </td>
              <td class="p-3">
                <Link :href="route('admin.clients.show', client.id)" class="text-white hover:text-purple-400 font-medium">
                  {{ client.username }}
                </Link>
                <div class="text-gray-500 text-xs">{{ client.first_name }} {{ client.last_name }}</div>
              </td>
              <td class="p-3 text-gray-300 text-sm">{{ client.email }}</td>
              <td class="p-3 text-gray-300 text-sm">{{ client.package_name || 'No Package' }}</td>
              <td class="p-3">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                  :class="{
                    'bg-green-500/20 text-green-400': client.subscription_status === 'active',
                    'bg-red-500/20 text-red-400': client.subscription_status === 'expired',
                    'bg-yellow-500/20 text-yellow-400': client.subscription_status === 'suspended',
                  }">
                  {{ client.subscription_status }}
                </span>
              </td>
              <td class="p-3 text-gray-300 text-sm">{{ formatDate(client.subscription_end_date) }}</td>
              <td class="p-3 text-gray-300 text-sm">{{ client.max_connections }}</td>
              <td class="p-3 text-right">
                <div class="flex justify-end gap-1">
                  <Link :href="route('admin.clients.show', client.id)" class="p-1.5 hover:bg-gray-600 rounded text-gray-400 hover:text-white" title="View">
                    <Eye class="w-4 h-4" />
                  </Link>
                  <Link :href="route('admin.clients.edit', client.id)" class="p-1.5 hover:bg-gray-600 rounded text-gray-400 hover:text-white" title="Edit">
                    <Pencil class="w-4 h-4" />
                  </Link>
                  <button @click="toggleStatus(client)" class="p-1.5 hover:bg-gray-600 rounded text-gray-400 hover:text-white" :title="client.is_active ? 'Suspend' : 'Activate'">
                    <Pause v-if="client.is_active" class="w-4 h-4" />
                    <Play v-else class="w-4 h-4" />
                  </button>
                  <button @click="deleteClient(client)" class="p-1.5 hover:bg-red-600/20 rounded text-gray-400 hover:text-red-400" title="Delete">
                    <Trash2 class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="clients.data.length === 0">
              <td colspan="8" class="p-8 text-center text-gray-500">No clients found</td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div class="p-4 border-t border-gray-700 flex justify-between items-center">
          <span class="text-gray-400 text-sm">Showing {{ clients.from || 0 }}-{{ clients.to || 0 }} of {{ clients.total }}</span>
          <div class="flex gap-1">
            <Link v-for="link in clients.links" :key="link.label"
              :href="link.url || ''"
              :class="['px-3 py-1 rounded text-sm', link.active ? 'bg-purple-600 text-white' : 'text-gray-400 hover:bg-gray-700', !link.url ? 'opacity-50 pointer-events-none' : '']"
              v-html="link.label"></Link>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Eye, Pencil, Pause, Play, Trash2 } from 'lucide-vue-next'

const props = defineProps({
  clients: { type: Object, required: true },
  packages: { type: Array, default: () => [] },
  resellers: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
})

const selectedIds = ref([])
const filters = ref({
  search: props.filters?.search || '',
  package_id: props.filters?.package_id || '',
  status: props.filters?.status || '',
  reseller_id: props.filters?.reseller_id || '',
  expiry_to: props.filters?.expiry_to || '',
})

const allSelected = computed(() => {
  return props.clients.data.length > 0 && props.clients.data.every(c => selectedIds.value.includes(c.id))
})

let searchTimeout
const debouncedSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => applyFilters(), 500)
}

const applyFilters = () => {
  router.get(route('admin.clients.index'), filters.value, { preserveState: true, preserveScroll: true })
}

const toggleSelectAll = (e) => {
  if (e.target.checked) {
    selectedIds.value = props.clients.data.map(c => c.id)
  } else {
    selectedIds.value = []
  }
}

const toggleStatus = (client) => {
  if (confirm(`Are you sure you want to ${client.is_active ? 'suspend' : 'activate'} this client?`)) {
    router.post(route('admin.clients.toggleStatus', client.id), {}, { preserveScroll: true })
  }
}

const deleteClient = (client) => {
  if (confirm(`Are you sure you want to delete client "${client.username}"? This cannot be undone.`)) {
    router.delete(route('admin.clients.destroy', client.id))
  }
}

const bulkAction = (action) => {
  if (confirm(`Are you sure you want to ${action} ${selectedIds.value.length} clients?`)) {
    router.post(route('admin.clients.bulkAction'), { ids: selectedIds.value, action }, {
      preserveScroll: true,
      onSuccess: () => { selectedIds.value = [] }
    })
  }
}

const formatDate = (date) => {
  if (!date) return 'N/A'
  return new Date(date).toLocaleDateString()
}
</script>