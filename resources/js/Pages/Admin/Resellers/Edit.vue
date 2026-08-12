<template>
  <AdminLayout>
    <div class="p-6 max-w-3xl mx-auto">
      <div class="mb-6">
        <Link :href="route('admin.resellers.index')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
          <ArrowLeft class="w-4 h-4" /> Back to Resellers
        </Link>
        <h1 class="text-2xl font-bold text-white">Edit Reseller</h1>
      </div>

      <form @submit.prevent="submit" class="bg-gray-800 rounded-xl p-6 border border-gray-700 space-y-6">
        <!-- Account Info -->
        <h3 class="text-white font-medium border-b border-gray-700 pb-2">Account Information</h3>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">First Name</label>
            <input v-model="form.first_name" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Last Name</label>
            <input v-model="form.last_name" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Email *</label>
            <input v-model="form.email" type="email" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" :class="{ 'border-red-500': form.errors.email }" />
            <p v-if="form.errors.email" class="text-red-400 text-sm mt-1">{{ form.errors.email }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Username *</label>
            <input v-model="form.username" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" :class="{ 'border-red-500': form.errors.username }" />
            <p v-if="form.errors.username" class="text-red-400 text-sm mt-1">{{ form.errors.username }}</p>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">New Password (leave blank to keep current)</label>
            <input v-model="form.password" type="password" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Confirm Password</label>
            <input v-model="form.password_confirmation" type="password" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>
        </div>

        <!-- Company Info -->
        <h3 class="text-white font-medium border-t border-gray-700 pt-4">Company Information</h3>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Company Name</label>
            <input v-model="form.company_name" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Website</label>
            <input v-model="form.website" type="url" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" placeholder="https://" />
          </div>
        </div>

        <!-- Credits & Commission -->
        <h3 class="text-white font-medium border-t border-gray-700 pt-4">Credits & Commission</h3>
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Credits ($)</label>
            <input v-model="form.credits" type="number" step="0.01" min="0" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Credit Limit ($)</label>
            <input v-model="form.credit_limit" type="number" step="0.01" min="0" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" placeholder="0 = unlimited" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Commission Rate (%)</label>
            <input v-model="form.commission_rate" type="number" step="0.01" min="0" max="100" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>
        </div>

        <!-- Features -->
        <h3 class="text-white font-medium border-t border-gray-700 pt-4">Features</h3>
        <div class="grid grid-cols-2 gap-3">
          <label class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
            <input type="checkbox" v-model="form.white_label" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
            White Label
          </label>
          <label class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
            <input type="checkbox" v-model="form.allow_sub_resellers" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
            Allow Sub-resellers
          </label>
        </div>

        <!-- Status -->
        <div>
          <label class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
            <input type="checkbox" v-model="form.is_active" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
            Active Account
          </label>
        </div>

        <!-- Package -->
        <h3 class="text-white font-medium border-t border-gray-700 pt-4">Package</h3>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Subscription Package</label>
          <select v-model="form.package_id" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            <option value="">No Package</option>
            <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">{{ pkg.name }} - ${{ pkg.price }}</option>
          </select>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-gray-700">
          <Link :href="route('admin.resellers.index')" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">Cancel</Link>
          <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50">
            {{ form.processing ? 'Saving...' : 'Save Changes' }}
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
  reseller: { type: Object, required: true },
  packages: { type: Array, default: () => [] },
})

const form = useForm({
  first_name: props.reseller.first_name || '',
  last_name: props.reseller.last_name || '',
  email: props.reseller.email || '',
  username: props.reseller.username || '',
  password: '',
  password_confirmation: '',
  company_name: props.reseller.company_name || '',
  website: props.reseller.website || '',
  credits: props.reseller.credits ?? 0,
  credit_limit: props.reseller.credit_limit ?? 0,
  commission_rate: props.reseller.commission_rate ?? 0,
  white_label: props.reseller.white_label ?? false,
  allow_sub_resellers: props.reseller.allow_sub_resellers ?? false,
  is_active: props.reseller.is_active ?? true,
  package_id: props.reseller.package_id || '',
})

const submit = () => {
  form.put(route('admin.resellers.update', props.reseller.id))
}
</script>
