<template>
  <AdminLayout>
    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-white">Subscription Packages</h1>
          <p class="text-gray-400 mt-1">Manage pricing plans</p>
        </div>
        <button @click="openAdd" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition flex items-center gap-2">
          <Plus class="w-4 h-4" />
          Add Package
        </button>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div v-for="pkg in packages" :key="pkg.id" class="bg-gray-800 rounded-xl border border-gray-700 p-6 hover:border-gray-600 transition">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold text-white">{{ pkg.name }}</h3>
            <span class="px-2 py-1 text-xs rounded-full" :class="pkg.is_active ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'">
              {{ pkg.is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>
          <div class="mb-4">
            <span class="text-3xl font-bold text-indigo-400">${{ pkg.price }}</span>
            <span class="text-gray-400">/{{ pkg.duration_days }} days</span>
          </div>
          <ul class="space-y-2 mb-6">
            <li v-for="feature in pkg.features" :key="feature" class="flex items-center gap-2 text-gray-300 text-sm">
              <Check class="w-4 h-4 text-green-400" />
              {{ feature }}
            </li>
          </ul>
          <div class="flex gap-2">
            <button @click="editPackage(pkg)" class="flex-1 px-3 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg text-sm transition">
              Edit
            </button>
            <button @click="deletePackage(pkg)" class="px-3 py-2 bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white rounded-lg text-sm transition">
              Delete
            </button>
          </div>
        </div>
      </div>

      <!-- Add/Edit Modal -->
      <div v-if="showAddModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-gray-800 rounded-xl p-6 w-full max-w-md border border-gray-700">
          <h2 class="text-lg font-semibold text-white mb-4">{{ editingPackage ? 'Edit' : 'Add' }} Package</h2>
          <form @submit.prevent="savePackage" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Name</label>
              <input v-model="form.name" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              <p v-if="form.errors.name" class="text-red-400 text-xs mt-1">{{ form.errors.name }}</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Price</label>
                <input v-model="form.price" type="number" step="0.01" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
                <p v-if="form.errors.price" class="text-red-400 text-xs mt-1">{{ form.errors.price }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Duration (days)</label>
                <input v-model="form.duration_days" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
                <p v-if="form.errors.duration_days" class="text-red-400 text-xs mt-1">{{ form.errors.duration_days }}</p>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Max Connections</label>
              <input v-model="form.max_connections" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              <p v-if="form.errors.max_connections" class="text-red-400 text-xs mt-1">{{ form.errors.max_connections }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Features (one per line)</label>
              <textarea v-model="form.featuresText" rows="4" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div class="flex items-center gap-3">
              <input v-model="form.is_active" type="checkbox" id="is_active" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <label for="is_active" class="text-gray-300">Active</label>
            </div>
            <div class="flex justify-end gap-3 mt-6">
              <button type="button" @click="showAddModal = false" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">Cancel</button>
              <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50">
                {{ editingPackage ? 'Update' : 'Add' }} Package
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Plus, Check } from 'lucide-vue-next'

defineProps({ packages: Array })

const showAddModal = ref(false)
const editingPackage = ref(null)

const form = useForm({
  name: '', price: '', duration_days: 30, max_connections: 1, featuresText: '', is_active: true
})

const openAdd = () => {
  editingPackage.value = null
  form.reset()
  form.clearErrors()
  showAddModal.value = true
}

const editPackage = (pkg) => {
  editingPackage.value = pkg
  form.name = pkg.name
  form.price = pkg.price
  form.duration_days = pkg.duration_days || 30
  form.max_connections = pkg.max_connections || 1
  form.featuresText = (pkg.features || []).join('\n')
  form.is_active = pkg.is_active
  form.clearErrors()
  showAddModal.value = true
}

const savePackage = () => {
  const payload = {
    name: form.name,
    price: Number(form.price),
    duration_days: Number(form.duration_days),
    max_connections: Number(form.max_connections),
    features: form.featuresText.split('\n').filter(f => f.trim()),
    is_active: form.is_active,
  }
  if (editingPackage.value) {
    form.transform(() => payload).put(
      route('admin.subscriptions.packages.update', editingPackage.value.id),
      { onSuccess: () => { showAddModal.value = false; editingPackage.value = null } }
    )
  } else {
    form.transform(() => payload).post(
      route('admin.subscriptions.packages.store'),
      { onSuccess: () => { showAddModal.value = false; form.reset() } }
    )
  }
}

const deletePackage = (pkg) => {
  if (confirm(`Delete package "${pkg.name}"?`)) {
    router.delete(route('admin.subscriptions.packages.destroy', pkg.id))
  }
}
</script>
