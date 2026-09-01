<template>
  <AdminLayout>
    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-white">Role Management</h1>
          <p class="text-gray-400 mt-1">Create and edit roles. Assign roles to users to control what they can see and manage.</p>
        </div>
        <button @click="openCreate" class="px-5 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium transition flex items-center gap-2">
          <Plus class="w-4 h-4" /> Create Role
        </button>
      </div>

      <div v-if="$page.props.flash?.success" class="p-3 bg-green-900/30 border border-green-700/50 rounded-lg text-green-400 text-sm">
        {{ $page.props.flash.success }}
      </div>

      <div class="space-y-3">
        <div v-for="role in roles" :key="role.id" class="bg-gray-800 rounded-xl p-5 border border-gray-700">
          <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
              <div class="w-10 h-10 rounded-lg bg-indigo-900/40 border border-indigo-700/40 flex items-center justify-center shrink-0">
                <Shield class="w-5 h-5 text-indigo-400" />
              </div>
              <div class="min-w-0">
                <div class="text-white font-medium">{{ role.label || role.name }}</div>
                <div class="text-xs text-gray-400 mt-0.5">
                  <span class="font-mono text-indigo-400">{{ role.name }}</span>
                  <span class="mx-2">·</span>
                  {{ role.users_count }} user{{ role.users_count === 1 ? '' : 's' }}
                </div>
                <div v-if="role.description" class="text-xs text-gray-500 mt-1">{{ role.description }}</div>
              </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <button @click="openEdit(role)" class="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded-lg transition flex items-center gap-1.5">
                <Edit class="w-4 h-4" /> Edit
              </button>
              <button @click="destroy(role)" class="px-3 py-1.5 bg-red-900/40 hover:bg-red-900/60 text-red-400 text-sm rounded-lg transition flex items-center gap-1.5" :title="role.users_count ? 'Remove assignments first' : ''">
                <Trash2 class="w-4 h-4" /> Delete
              </button>
            </div>
          </div>

          <div v-if="role.permissions?.length" class="mt-3 flex flex-wrap gap-1.5">
            <span v-for="p in role.permissions" :key="p" class="px-2 py-0.5 bg-gray-700/70 text-gray-300 rounded text-[11px] font-medium">
              {{ permissionLabel(p) }}
            </span>
          </div>
          <p v-else class="mt-3 text-xs text-gray-500">No permissions assigned.</p>
        </div>
      </div>

      <!-- Create/Edit Modal -->
      <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="modalOpen = false">
        <div class="bg-gray-800 rounded-xl border border-gray-700 w-full max-w-lg p-6 space-y-5">
          <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold text-white">{{ editing ? 'Edit Role' : 'Create Role' }}</h2>
            <button @click="modalOpen = false" class="text-gray-400 hover:text-white"><X class="w-5 h-5" /></button>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Name *</label>
            <input v-model="form.name" type="text" placeholder="e.g. channel_manager"
              class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500"
              :class="{ 'border-red-500': form.errors.name }" />
            <p v-if="form.errors.name" class="text-red-400 text-sm mt-1">{{ form.errors.name }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Label</label>
            <input v-model="form.label" type="text" placeholder="Channel Manager"
              class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
            <textarea v-model="form.description" rows="2" placeholder="What can this role do?"
              class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500"></textarea>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Permissions</label>
            <div class="grid grid-cols-1 gap-2 max-h-48 overflow-y-auto">
              <label v-for="(label, key) in permissions" :key="key" class="flex items-center gap-3 bg-gray-700 rounded-lg px-3 py-2 cursor-pointer">
                <input type="checkbox" :value="key" v-model="form.permissions" class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600 focus:ring-indigo-500" />
                <span class="text-sm text-gray-200">{{ label }}</span>
              </label>
            </div>
          </div>

          <div class="flex justify-end gap-3 pt-2 border-t border-gray-700">
            <button @click="modalOpen = false" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg">Cancel</button>
            <button @click="submit" :disabled="form.processing" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg disabled:opacity-50">
              {{ form.processing ? 'Saving...' : 'Save' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Plus, Edit, Trash2, Shield, X } from 'lucide-vue-next'

const props = defineProps({
  roles: { type: Array, default: () => [] },
  permissions: { type: Object, default: () => ({}) },
})

const modalOpen = ref(false)
const editing = ref(null)

const form = useForm({
  name: '',
  label: '',
  description: '',
  permissions: [],
})

const permissionLabel = (key) => props.permissions[key] || key

const resetForm = () => {
  form.defaults({
    name: '',
    label: '',
    description: '',
    permissions: [],
  })
  form.reset()
}

const openCreate = () => {
  editing.value = null
  resetForm()
  modalOpen.value = true
}

const openEdit = (role) => {
  editing.value = role
  form.defaults({
    name: role.name,
    label: role.label || '',
    description: role.description || '',
    permissions: [...(role.permissions || [])],
  })
  form.reset()
  modalOpen.value = true
}

const submit = () => {
  if (editing.value) {
    form.put(route('admin.roles.update', editing.value.id), {
      preserveScroll: true,
      onSuccess: () => { modalOpen.value = false }
    })
  } else {
    form.post(route('admin.roles.store'), {
      preserveScroll: true,
      onSuccess: () => { modalOpen.value = false }
    })
  }
}

const destroy = (role) => {
  if (!confirm(`Delete role "${role.label || role.name}"? Users will lose this role.`)) return
  router.delete(route('admin.roles.destroy', role.id), { preserveScroll: true })
}
</script>