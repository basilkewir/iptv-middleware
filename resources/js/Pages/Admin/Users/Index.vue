<template>
  <AdminLayout>
    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-white">Users</h1>
          <p class="text-gray-400 mt-1">Manage platform users</p>
        </div>
        <div class="flex gap-3">
          <Link :href="route('admin.users.bulk')" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition flex items-center gap-2">
            <Upload class="w-4 h-4" />
            Bulk Import
          </Link>
          <Link :href="route('admin.users.create')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition flex items-center gap-2">
            <UserPlus class="w-4 h-4" />
            Add User
          </Link>
        </div>
      </div>

      <!-- Quick Filter Tabs -->
      <div class="flex gap-2 flex-wrap">
        <button v-for="tab in filterTabs" :key="tab.key" @click="activeTab = tab.key"
          class="px-4 py-2 rounded-lg text-sm font-medium transition"
          :class="activeTab === tab.key ? 'bg-indigo-600 text-white' : 'bg-gray-800 text-gray-400 hover:text-white hover:bg-gray-700'">
          {{ tab.label }}
          <span v-if="tab.count !== undefined" class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-gray-700">{{ tab.count }}</span>
        </button>
      </div>

      <!-- Filters -->
      <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
        <div class="flex flex-wrap gap-4 items-center">
          <div class="flex-1 min-w-[200px]">
            <div class="relative">
              <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
              <input v-model="search" type="text" placeholder="Search users..." class="w-full pl-10 pr-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500" />
            </div>
          </div>
          <select v-model="filterRole" class="px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            <option value="">All Roles</option>
            <option value="admin">Admin</option>
            <option value="reseller">Reseller</option>
            <option value="client">Client</option>
          </select>
          <select v-model="filterStatus" class="px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            <option value="">All Status</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
          <div class="flex gap-1 bg-gray-700 rounded-lg p-1">
            <button @click="viewMode = 'table'" class="p-2 rounded transition" :class="viewMode === 'table' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white'">
              <List class="w-4 h-4" />
            </button>
            <button @click="viewMode = 'grid'" class="p-2 rounded transition" :class="viewMode === 'grid' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white'">
              <LayoutGrid class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      <!-- Bulk Actions Bar -->
      <div v-if="selectedUsers.length > 0" class="bg-indigo-600/20 border border-indigo-500/30 rounded-xl p-4 flex items-center justify-between">
        <div class="flex items-center gap-4">
          <span class="text-indigo-400 text-sm font-medium">{{ selectedUsers.length }} user(s) selected</span>
          <button @click="bulkActivate" class="px-3 py-1.5 bg-green-600/20 text-green-400 rounded-lg text-sm hover:bg-green-600/30">Activate</button>
          <button @click="bulkSuspend" class="px-3 py-1.5 bg-yellow-600/20 text-yellow-400 rounded-lg text-sm hover:bg-yellow-600/30">Suspend</button>
          <button @click="bulkDelete" class="px-3 py-1.5 bg-red-600/20 text-red-400 rounded-lg text-sm hover:bg-red-600/30">Delete</button>
        </div>
        <button @click="selectedUsers = []" class="text-gray-400 hover:text-white text-sm">Clear Selection</button>
      </div>

      <!-- Table View -->
      <div v-if="viewMode === 'table'" class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
        <table class="w-full">
          <thead>
            <tr class="border-b border-gray-700">
              <th class="px-6 py-3 text-left">
                <input type="checkbox" @change="toggleSelectAll" :checked="isAllSelected" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">User</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Role</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Created</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-700">
            <tr v-for="user in users?.data || []" :key="user.id" class="hover:bg-gray-700/50">
              <td class="px-6 py-4">
                <input type="checkbox" :value="user.id" v-model="selectedUsers" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-full bg-indigo-600 flex items-center justify-center text-white font-semibold">
                    {{ (user.first_name || user.username || 'U').charAt(0).toUpperCase() }}
                  </div>
                  <div>
                    <p class="text-white font-medium">{{ userFullName(user) }}</p>
                    <p class="text-gray-400 text-sm">{{ user.email }}</p>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 text-xs rounded-full" :class="roleClass(user)">
                  {{ userRole(user) }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 text-xs rounded-full" :class="user.is_active ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'">
                  {{ user.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-400 text-sm">{{ formatDate(user.created_at) }}</td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="relative" v-if="openDropdown === user.id">
                  <div class="fixed inset-0 z-10" @click="openDropdown = null" />
                  <div class="absolute right-0 mt-1 w-48 bg-gray-700 rounded-lg shadow-xl border border-gray-600 z-20 py-1">
                    <Link :href="route('admin.users.edit', user.id)" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-300 hover:bg-gray-600">
                      <Pencil class="w-4 h-4" /> Edit
                    </Link>
                    <Link :href="route('admin.users.activity', user.id)" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-300 hover:bg-gray-600">
                      <Activity class="w-4 h-4" /> View Activity
                    </Link>
                    <Link :href="route('admin.users.channels', user.id)" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-300 hover:bg-gray-600">
                      <Tv class="w-4 h-4" /> Manage Channels
                    </Link>
                    <button @click="resetPassword(user)" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-300 hover:bg-gray-600 w-full text-left">
                      <Key class="w-4 h-4" /> Reset Password
                    </button>
                    <hr class="border-gray-600 my-1" />
                    <button @click="toggleUserStatus(user)" class="flex items-center gap-2 px-4 py-2 text-sm w-full text-left"
                      :class="user.is_active ? 'text-yellow-400 hover:bg-yellow-600/20' : 'text-green-400 hover:bg-green-600/20'">
                      <UserX v-if="user.is_active" class="w-4 h-4" /> <UserCheck v-else class="w-4 h-4" />
                      {{ user.is_active ? 'Suspend' : 'Activate' }}
                    </button>
                    <button @click="confirmDelete(user)" class="flex items-center gap-2 px-4 py-2 text-sm text-red-400 hover:bg-red-600/20 w-full text-left">
                      <Trash2 class="w-4 h-4" /> Delete
                    </button>
                  </div>
                </div>
                <button v-else @click="openDropdown = user.id" class="p-2 hover:bg-gray-600 rounded-lg transition text-gray-400 hover:text-white">
                  <MoreVertical class="w-4 h-4" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Grid View -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <div v-for="user in users?.data || []" :key="user.id" class="bg-gray-800 rounded-xl border border-gray-700 p-4 hover:border-gray-600 transition">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-12 h-12 rounded-full bg-indigo-600 flex items-center justify-center text-white font-bold text-lg">
              {{ (user.first_name || user.username || 'U').charAt(0).toUpperCase() }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-white font-semibold truncate">{{ userFullName(user) }}</p>
              <p class="text-gray-400 text-sm truncate">{{ user.email }}</p>
            </div>
          </div>
          <div class="flex items-center gap-2 mb-3">
            <span class="px-2 py-1 text-xs rounded-full" :class="roleClass(user)">{{ userRole(user) }}</span>
            <span class="px-2 py-1 text-xs rounded-full" :class="user.is_active ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'">
              {{ user.is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>
          <p class="text-gray-500 text-xs mb-3">Created: {{ formatDate(user.created_at) }}</p>
          <div class="flex gap-2">
            <Link :href="route('admin.users.edit', user.id)" class="flex-1 px-3 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg text-sm transition text-center">Edit</Link>
            <button @click="confirmDelete(user)" class="px-3 py-2 bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white rounded-lg text-sm transition">Delete</button>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="users?.links" class="flex items-center justify-between">
        <p class="text-gray-400 text-sm">Showing {{ users.from }} to {{ users.to }} of {{ users.total }} users</p>
        <div class="flex gap-2">
          <Link v-for="page in users.links" :key="page.label" :href="page.url || '#'" class="px-3 py-1 rounded-lg text-sm"
            :class="page.active ? 'bg-indigo-600 text-white' : 'bg-gray-700 text-gray-400 hover:bg-gray-600'" preserve-scroll>
            {{ page.label }}
          </Link>
        </div>
      </div>

      <!-- Delete Modal -->
      <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 w-full max-w-md">
          <h3 class="text-lg font-semibold text-white mb-2">Delete User</h3>
          <p class="text-gray-400">Are you sure you want to delete <strong class="text-white">{{ userFullName(deleteTarget) }}</strong>? This action cannot be undone.</p>
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
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Search, UserPlus, Upload, Pencil, Trash2, MoreVertical, Activity, Key, UserX, UserCheck, List, LayoutGrid, Tv } from 'lucide-vue-next'

const props = defineProps({
  users: { type: Object, default: () => ({ data: [], links: [], from: 0, to: 0, total: 0 }) },
})

const search = ref('')
const filterRole = ref('')
const filterStatus = ref('')
const activeTab = ref('all')
const viewMode = ref('table')
const selectedUsers = ref([])
const openDropdown = ref(null)
const showDeleteModal = ref(false)
const deleteTarget = ref(null)

const filterTabs = computed(() => [
  { key: 'all', label: 'All Users', count: props.users?.total },
  { key: 'admin', label: 'Admins' },
  { key: 'reseller', label: 'Resellers' },
  { key: 'client', label: 'Clients' },
  { key: 'active', label: 'Active' },
  { key: 'suspended', label: 'Suspended' },
])

const userFullName = (user) => {
  const parts = [user.first_name, user.last_name].filter(Boolean)
  return parts.length ? parts.join(' ') : user.username || user.email
}

const userRole = (user) => {
  if (user.resolved_role) return user.resolved_role
  if (user.role) return user.role.charAt(0).toUpperCase() + user.role.slice(1)
  if (user.is_admin) return 'Admin'
  if (user.is_reseller) return 'Reseller'
  return 'Client'
}

const roleClass = (user) => {
  const role = (user.resolved_role || user.role || (user.is_admin ? 'admin' : user.is_reseller ? 'reseller' : 'client')).toLowerCase()
  const map = { super_admin: 'bg-red-500/20 text-red-400', admin: 'bg-purple-500/20 text-purple-400', reseller: 'bg-blue-500/20 text-blue-400', client: 'bg-gray-500/20 text-gray-400', moderator: 'bg-yellow-500/20 text-yellow-400', support: 'bg-cyan-500/20 text-cyan-400' }
  return map[role] || map.client
}

const formatDate = (d) => d ? new Date(d).toLocaleDateString() : ''

const isAllSelected = computed(() => {
  const data = props.users?.data || []
  return data.length > 0 && data.every(u => selectedUsers.value.includes(u.id))
})

const toggleSelectAll = () => {
  if (isAllSelected.value) { selectedUsers.value = [] }
  else { selectedUsers.value = (props.users?.data || []).map(u => u.id) }
}

const toggleUserStatus = (user) => {
  router.post(route('admin.users.toggle-status', user.id), {}, { preserveScroll: true })
  openDropdown.value = null
}

const resetPassword = (user) => {
  const email = prompt(`Enter new password for ${user.username}:`)
  if (email) {
    router.post(route('admin.users.reset-password', user.id), { password: email, password_confirmation: email }, { preserveScroll: true })
  }
  openDropdown.value = null
}

const confirmDelete = (user) => { deleteTarget.value = user; showDeleteModal.value = true; openDropdown.value = null }

const performDelete = () => {
  if (deleteTarget.value) {
    router.delete(route('admin.users.destroy', deleteTarget.value.id))
    showDeleteModal.value = false
    deleteTarget.value = null
  }
}

const bulkActivate = () => {
  router.post(route('admin.users.bulk-activate'), { ids: selectedUsers.value }, { preserveScroll: true, onFinish: () => { selectedUsers.value = [] } })
}

const bulkSuspend = () => {
  router.post(route('admin.users.bulk-suspend'), { ids: selectedUsers.value }, { preserveScroll: true, onFinish: () => { selectedUsers.value = [] } })
}

const bulkDelete = () => {
  if (confirm(`Delete ${selectedUsers.value.length} users?`)) {
    router.post(route('admin.users.bulk-delete'), { ids: selectedUsers.value }, { preserveScroll: true, onFinish: () => { selectedUsers.value = [] } })
  }
}
</script>
