<template>
  <AdminLayout>
    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-white">Resellers</h1>
          <p class="text-gray-400 mt-1">Manage reseller accounts and credits</p>
        </div>
        <Link :href="route('admin.resellers.create')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition flex items-center gap-2">
          <UserPlus class="w-4 h-4" />
          Add Reseller
        </Link>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-400 text-sm">Total Resellers</p>
              <p class="text-2xl font-bold text-white mt-1">{{ stats.total_resellers ?? 0 }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-blue-500/20 flex items-center justify-center">
              <Users class="w-5 h-5 text-blue-400" />
            </div>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-400 text-sm">Active Resellers</p>
              <p class="text-2xl font-bold text-green-400 mt-1">{{ stats.active_resellers ?? 0 }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-green-500/20 flex items-center justify-center">
              <UserCheck class="w-5 h-5 text-green-400" />
            </div>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-400 text-sm">Total Credits Assigned</p>
              <p class="text-2xl font-bold text-yellow-400 mt-1">${{ stats.total_credits ?? 0 }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-yellow-500/20 flex items-center justify-center">
              <CreditCard class="w-5 h-5 text-yellow-400" />
            </div>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-400 text-sm">Total Sub-clients</p>
              <p class="text-2xl font-bold text-purple-400 mt-1">{{ stats.total_sub_clients ?? 0 }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-purple-500/20 flex items-center justify-center">
              <UserCog class="w-5 h-5 text-purple-400" />
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
        <div class="flex flex-wrap gap-4 items-center">
          <div class="flex-1 min-w-[200px]">
            <div class="relative">
              <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
              <input v-model="search" type="text" placeholder="Search resellers..." class="w-full pl-10 pr-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500" />
            </div>
          </div>
          <select v-model="filterStatus" class="px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            <option value="">All Status</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
        <table class="w-full">
          <thead>
            <tr class="border-b border-gray-700">
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Name</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Email</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Company</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Credits</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Commission</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Sub-clients</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-700">
            <tr v-for="reseller in resellers?.data || []" :key="reseller.id" class="hover:bg-gray-700/50">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-white font-semibold">
                    {{ (reseller.first_name || reseller.username || 'R').charAt(0).toUpperCase() }}
                  </div>
                  <div>
                    <p class="text-white font-medium">{{ resellerFullName(reseller) }}</p>
                    <p class="text-gray-400 text-sm">@{{ reseller.username }}</p>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-300 text-sm">{{ reseller.email }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-300 text-sm">{{ reseller.company_name || 'N/A' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-yellow-400 font-medium text-sm">${{ reseller.credits ?? 0 }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-300 text-sm">{{ reseller.commission_rate ?? 0 }}%</td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-300 text-sm">{{ reseller.sub_clients_count ?? 0 }}</td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 text-xs rounded-full" :class="reseller.is_active ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'">
                  {{ reseller.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="relative" v-if="openDropdown === reseller.id">
                  <div class="fixed inset-0 z-10" @click="openDropdown = null" />
                  <div class="absolute right-0 mt-1 w-52 bg-gray-700 rounded-lg shadow-xl border border-gray-600 z-20 py-1">
                    <Link :href="route('admin.resellers.show', reseller.id)" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-300 hover:bg-gray-600">
                      <Eye class="w-4 h-4" /> View
                    </Link>
                    <Link :href="route('admin.resellers.edit', reseller.id)" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-300 hover:bg-gray-600">
                      <Pencil class="w-4 h-4" /> Edit
                    </Link>
                    <button @click="openEditModal(reseller)" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-300 hover:bg-gray-600 w-full text-left">
                      <Settings class="w-4 h-4" /> Edit Details
                    </button>
                    <button @click="assignSubscription(reseller)" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-300 hover:bg-gray-600 w-full text-left">
                      <Package class="w-4 h-4" /> Assign Subscription
                    </button>
                    <hr class="border-gray-600 my-1" />
                    <button @click="toggleResellerStatus(reseller)" class="flex items-center gap-2 px-4 py-2 text-sm w-full text-left"
                      :class="reseller.is_active ? 'text-yellow-400 hover:bg-yellow-600/20' : 'text-green-400 hover:bg-green-600/20'">
                      <UserX v-if="reseller.is_active" class="w-4 h-4" /> <UserCheck v-else class="w-4 h-4" />
                      {{ reseller.is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                    <button @click="confirmDelete(reseller)" class="flex items-center gap-2 px-4 py-2 text-sm text-red-400 hover:bg-red-600/20 w-full text-left">
                      <Trash2 class="w-4 h-4" /> Delete
                    </button>
                  </div>
                </div>
                <button v-else @click="openDropdown = reseller.id" class="p-2 hover:bg-gray-600 rounded-lg transition text-gray-400 hover:text-white">
                  <MoreVertical class="w-4 h-4" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="!resellers?.data?.length" class="p-8 text-center text-gray-500">
          No resellers found
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="resellers?.links" class="flex items-center justify-between">
        <p class="text-gray-400 text-sm">Showing {{ resellers.from }} to {{ resellers.to }} of {{ resellers.total }} resellers</p>
        <div class="flex gap-2">
          <Link v-for="page in resellers.links" :key="page.label" :href="page.url || '#'" class="px-3 py-1 rounded-lg text-sm"
            :class="page.active ? 'bg-indigo-600 text-white' : 'bg-gray-700 text-gray-400 hover:bg-gray-600'" preserve-scroll>
            {{ page.label }}
          </Link>
        </div>
      </div>

      <!-- Edit Details Modal -->
      <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="showEditModal = false">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 w-full max-w-lg">
          <h3 class="text-lg font-semibold text-white mb-4">Edit Reseller Details</h3>
          <form @submit.prevent="updateReseller" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Credits ($)</label>
                <input v-model="editForm.credits" type="number" step="0.01" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Commission Rate (%)</label>
                <input v-model="editForm.commission_rate" type="number" step="0.01" min="0" max="100" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <label class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
                <input type="checkbox" v-model="editForm.white_label" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                White Label
              </label>
              <label class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
                <input type="checkbox" v-model="editForm.allow_sub_resellers" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                Allow Sub-resellers
              </label>
            </div>
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-700">
              <button type="button" @click="showEditModal = false" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">Cancel</button>
              <button type="submit" :disabled="editForm.processing" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50">
                {{ editForm.processing ? 'Saving...' : 'Save Changes' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Delete Modal -->
      <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 w-full max-w-md">
          <h3 class="text-lg font-semibold text-white mb-2">Delete Reseller</h3>
          <p class="text-gray-400">Are you sure you want to delete <strong class="text-white">{{ resellerFullName(deleteTarget) }}</strong>? This will also remove all sub-clients. This action cannot be undone.</p>
          <div class="flex justify-end gap-3 mt-6">
            <button @click="showDeleteModal = false" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">Cancel</button>
            <button @click="performDelete" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded-lg transition">Delete Permanently</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Search, UserPlus, UserCheck, UserX, UserCog, MoreVertical, Pencil, Trash2, Eye, Settings, Package, CreditCard, Users } from 'lucide-vue-next'

const props = defineProps({
  resellers: { type: Object, default: () => ({ data: [], links: [], from: 0, to: 0, total: 0 }) },
  stats: { type: Object, default: () => ({}) },
})

const search = ref('')
const filterStatus = ref('')
const openDropdown = ref(null)
const showDeleteModal = ref(false)
const showEditModal = ref(false)
const deleteTarget = ref(null)
const editTarget = ref(null)

const editForm = useForm({
  credits: 0,
  commission_rate: 0,
  white_label: false,
  allow_sub_resellers: false,
})

const resellerFullName = (r) => {
  const parts = [r.first_name, r.last_name].filter(Boolean)
  return parts.length ? parts.join(' ') : r.username || r.email
}

const openEditModal = (reseller) => {
  editTarget.value = reseller
  editForm.credits = reseller.credits ?? 0
  editForm.commission_rate = reseller.commission_rate ?? 0
  editForm.white_label = reseller.white_label ?? false
  editForm.allow_sub_resellers = reseller.allow_sub_resellers ?? false
  showEditModal.value = true
  openDropdown.value = null
}

const updateReseller = () => {
  editForm.put(route('admin.resellers.update', editTarget.value.id), {
    preserveScroll: true,
    onSuccess: () => { showEditModal.value = false },
  })
}

const toggleResellerStatus = (reseller) => {
  router.post(route('admin.resellers.toggle-status', reseller.id), {}, { preserveScroll: true })
  openDropdown.value = null
}

const assignSubscription = (reseller) => {
  router.visit(route('admin.resellers.show', reseller.id) + '?tab=subscription')
  openDropdown.value = null
}

const confirmDelete = (reseller) => {
  deleteTarget.value = reseller
  showDeleteModal.value = true
  openDropdown.value = null
}

const performDelete = () => {
  if (deleteTarget.value) {
    router.delete(route('admin.resellers.destroy', deleteTarget.value.id))
    showDeleteModal.value = false
    deleteTarget.value = null
  }
}
</script>
