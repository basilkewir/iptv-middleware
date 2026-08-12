<template>
  <AdminLayout>
    <div class="p-6 max-w-6xl mx-auto space-y-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">Roles & Permissions</h1>
        <p class="text-gray-400 mt-1">Manage user roles and permission matrix</p>
      </div>
      <form @submit.prevent="form.put(route('admin.settings.roles.update'))" class="space-y-6">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Default Roles</h3>
          <div class="overflow-x-auto">
            <table class="w-full text-left">
              <thead>
                <tr class="border-b border-gray-700">
                  <th class="py-3 px-4 text-white font-semibold">Role</th>
                  <th class="py-3 px-4 text-white font-semibold">Users</th>
                  <th class="py-3 px-4 text-white font-semibold">Permissions</th>
                  <th class="py-3 px-4 text-white font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="role in roles" :key="role.name" class="border-b border-gray-700 hover:bg-gray-700/50">
                  <td class="py-3 px-4 text-white">{{ role.name }}</td>
                  <td class="py-3 px-4 text-gray-300">{{ role.users_count }}</td>
                  <td class="py-3 px-4 text-gray-300 text-sm">{{ role.permissions.join(', ') }}</td>
                  <td class="py-3 px-4">
                    <div class="flex space-x-2">
                      <button type="button" @click="viewRole(role)" class="px-3 py-1 bg-blue-600 hover:bg-blue-500 text-white text-sm rounded-lg transition">
                        View
                      </button>
                      <button type="button" @click="editRole(role)" class="px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white text-sm rounded-lg transition">
                        Edit
                      </button>
                      <button type="button" @click="deleteRole(role)" class="px-3 py-1 bg-red-600 hover:bg-red-500 text-white text-sm rounded-lg transition">
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Permission Matrix</h3>
          <div class="mb-4">
            <label class="block text-white font-medium mb-2">Select Role</label>
            <select v-model="selectedRole" class="w-64 bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500">
              <option v-for="role in roles" :key="role.name" :value="role.name">{{ role.name }}</option>
            </select>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-left">
              <thead>
                <tr class="border-b border-gray-700">
                  <th class="py-3 px-4 text-white font-semibold">Module</th>
                  <th class="py-3 px-4 text-white font-semibold text-center">View</th>
                  <th class="py-3 px-4 text-white font-semibold text-center">Create</th>
                  <th class="py-3 px-4 text-white font-semibold text-center">Edit</th>
                  <th class="py-3 px-4 text-white font-semibold text-center">Delete</th>
                  <th class="py-3 px-4 text-white font-semibold text-center">All</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="module in modules" :key="module" class="border-b border-gray-700 hover:bg-gray-700/50">
                  <td class="py-3 px-4 text-white">{{ module }}</td>
                  <td class="py-3 px-4 text-center">
                    <input type="checkbox" v-model="permissionMatrix[selectedRole][module].view" class="w-4 h-4 text-indigo-500 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                  </td>
                  <td class="py-3 px-4 text-center">
                    <input type="checkbox" v-model="permissionMatrix[selectedRole][module].create" class="w-4 h-4 text-indigo-500 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                  </td>
                  <td class="py-3 px-4 text-center">
                    <input type="checkbox" v-model="permissionMatrix[selectedRole][module].edit" class="w-4 h-4 text-indigo-500 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                  </td>
                  <td class="py-3 px-4 text-center">
                    <input type="checkbox" v-model="permissionMatrix[selectedRole][module].delete" class="w-4 h-4 text-indigo-500 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                  </td>
                  <td class="py-3 px-4 text-center">
                    <input type="checkbox" :checked="isAllChecked(module)" @change="toggleAll(module)" class="w-4 h-4 text-indigo-500 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="flex justify-end">
          <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50">
            {{ form.processing ? 'Saving...' : 'Save Settings' }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import { ref, reactive } from 'vue'

const props = defineProps({ settings: Object })

const roles = ref([
  { name: 'Admin', users_count: 2, permissions: ['all'] },
  { name: 'Reseller', users_count: 15, permissions: ['view_dashboard', 'manage_users', 'manage_channels', 'manage_bouquets'] },
  { name: 'Subscriber', users_count: 150, permissions: ['view_dashboard', 'view_channels'] },
])

const modules = ['Dashboard', 'Users', 'Resellers', 'Channels', 'Bouquets', 'VOD', 'EPG', 'Transcoding', 'Servers', 'Billing', 'Settings', 'Security']

const selectedRole = ref('Admin')

const permissionMatrix = reactive(
  props.settings.permission_matrix ?? {
    Admin: Object.fromEntries(modules.map(m => [m, { view: true, create: true, edit: true, delete: true }])),
    Reseller: Object.fromEntries(modules.map(m => [m, { view: true, create: m !== 'Settings' && m !== 'Security', edit: m !== 'Settings' && m !== 'Security', delete: false }])),
    Subscriber: Object.fromEntries(modules.map(m => [m, { view: m === 'Dashboard' || m === 'Channels', create: false, edit: false, delete: false }])),
  }
)

const form = useForm({
  permission_matrix: permissionMatrix,
})

function isAllChecked(module) {
  const perms = permissionMatrix[selectedRole][module]
  return perms.view && perms.create && perms.edit && perms.delete
}

function toggleAll(module) {
  const allChecked = isAllChecked(module)
  const newVal = !allChecked
  permissionMatrix[selectedRole][module].view = newVal
  permissionMatrix[selectedRole][module].create = newVal
  permissionMatrix[selectedRole][module].edit = newVal
  permissionMatrix[selectedRole][module].delete = newVal
}

function viewRole(role) {
  selectedRole.value = role.name
}

function editRole(role) {
  selectedRole.value = role.name
}

function deleteRole(role) {
  if (confirm(`Are you sure you want to delete the "${role.name}" role?`)) {
    roles.value = roles.value.filter(r => r.name !== role.name)
    delete permissionMatrix[role.name]
  }
}
</script>
