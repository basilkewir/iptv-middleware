<template>
  <AdminLayout>
    <div class="p-6 max-w-3xl mx-auto">
      <div class="mb-6">
        <Link :href="route('admin.users.index')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
          <ArrowLeft class="w-4 h-4" /> Back to Users
        </Link>
        <h1 class="text-2xl font-bold text-white">Create User</h1>
      </div>

      <!-- Tab Navigation -->
      <div class="flex gap-1 bg-gray-800 rounded-lg p-1 mb-6 border border-gray-700">
        <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
          class="flex-1 px-4 py-2.5 rounded-md text-sm font-medium transition"
          :class="activeTab === tab.key ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white'">
          {{ tab.label }}
        </button>
      </div>

      <form @submit.prevent="submit" class="bg-gray-800 rounded-xl p-6 border border-gray-700 space-y-6">
        <!-- Common Fields -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Username *</label>
            <input v-model="form.username" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" :class="{ 'border-red-500': form.errors.username }" />
            <p v-if="form.errors.username" class="text-red-400 text-sm mt-1">{{ form.errors.username }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Email *</label>
            <input v-model="form.email" type="email" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" :class="{ 'border-red-500': form.errors.email }" />
            <p v-if="form.errors.email" class="text-red-400 text-sm mt-1">{{ form.errors.email }}</p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Password *</label>
            <input v-model="form.password" type="password" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" :class="{ 'border-red-500': form.errors.password }" />
            <p v-if="form.errors.password" class="text-red-400 text-sm mt-1">{{ form.errors.password }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Confirm Password *</label>
            <input v-model="form.password_confirmation" type="password" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>
        </div>

        <!-- Admin Tab Fields -->
        <template v-if="activeTab === 'admin'">
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
              <label class="block text-sm font-medium text-gray-300 mb-2">Phone</label>
              <input v-model="form.phone" type="tel" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" placeholder="+1 (555) 000-0000" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Role *</label>
              <select v-model="form.role_id" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="">Select role</option>
                <option v-for="role in adminRoleOptions" :key="role.id" :value="role.id">{{ role.label || role.name }}</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Permissions</label>
            <div class="grid grid-cols-2 gap-3">
              <label v-for="perm in adminPermissions" :key="perm.key" class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
                <input type="checkbox" :value="perm.key" v-model="form.permissions" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                {{ perm.label }}
              </label>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <input v-model="form.is_active" type="checkbox" id="is_active" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
            <label for="is_active" class="text-gray-300">Active</label>
          </div>
        </template>

        <!-- Reseller Tab Fields -->
        <template v-if="activeTab === 'reseller'">
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
              <label class="block text-sm font-medium text-gray-300 mb-2">Company Name</label>
              <input v-model="form.company_name" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Phone</label>
              <input v-model="form.phone" type="tel" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Website URL</label>
            <input v-model="form.website" type="url" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" placeholder="https://" />
          </div>
          <h3 class="text-white font-medium border-t border-gray-700 pt-4">Credit & Limits</h3>
          <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Initial Credit ($)</label>
              <input v-model="form.credits" type="number" step="0.01" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Credit Limit ($)</label>
              <input v-model="form.credit_limit" type="number" step="0.01" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" placeholder="0 = unlimited" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Commission Rate (%)</label>
              <input v-model="form.commission_rate" type="number" step="0.01" min="0" max="100" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Max Users (0 = unlimited)</label>
              <input v-model="form.max_users" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Max Connections per User</label>
              <input v-model="form.max_connections" type="number" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
          </div>
          <h3 class="text-white font-medium border-t border-gray-700 pt-4">Features</h3>
          <div class="grid grid-cols-2 gap-3">
            <label v-for="feat in resellerFeatures" :key="feat.key" class="flex items-center gap-2 text-gray-300 text-sm cursor-pointer">
              <input type="checkbox" v-model="form[feat.key]" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              {{ feat.label }}
            </label>
          </div>
        </template>

        <!-- Client Tab Fields -->
        <template v-if="activeTab === 'client'">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Full Name</label>
              <input v-model="form.first_name" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Phone</label>
              <input v-model="form.phone" type="tel" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Package</label>
              <select v-model="form.package_id" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="">Select Package</option>
                <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">{{ pkg.name }} - ${{ pkg.price }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Expiry Date</label>
              <input v-model="form.expiry_date" type="date" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Max Connections</label>
              <input v-model="form.max_connections" type="number" min="1" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Assigned Reseller</label>
              <select v-model="form.reseller_id" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="">None</option>
                <option v-for="r in resellers" :key="r.id" :value="r.id">{{ r.username }}</option>
              </select>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">MAC Address</label>
              <input v-model="form.mac_address" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" placeholder="XX:XX:XX:XX:XX:XX" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">IP Restrict</label>
              <input v-model="form.ip_restrict" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" placeholder="e.g. 192.168.1.0/24" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Country</label>
              <select v-model="form.country" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="">Select Country</option>
                <option v-for="c in countries" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>
            <div></div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Bouquets</label>
            <div class="space-y-2 max-h-40 overflow-y-auto bg-gray-700 rounded-lg p-3">
              <label v-for="b in bouquets" :key="b.id" class="flex items-center gap-2 text-gray-300 text-sm">
                <input type="checkbox" :value="b.id" v-model="form.bouquet_ids" class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600 focus:ring-indigo-500" />
                {{ b.name }}
              </label>
            </div>
          </div>
        </template>

        <div class="flex justify-end gap-3 pt-4 border-t border-gray-700">
          <Link :href="route('admin.users.index')" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">Cancel</Link>
          <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50">
            {{ form.processing ? 'Creating...' : 'Create User' }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeft } from 'lucide-vue-next'

const props = defineProps({
  packages: { type: Array, default: () => [] },
  resellers: { type: Array, default: () => [] },
  bouquets: { type: Array, default: () => [] },
  roles: { type: Array, default: () => [] },
})

const activeTab = ref('client')

const tabs = [
  { key: 'admin', label: 'Admin User' },
  { key: 'reseller', label: 'Reseller Account' },
  { key: 'client', label: 'Client Account' },
]

const adminPermissions = [
  { key: 'full_access', label: 'Full System Access' },
  { key: 'user_management', label: 'User Management' },
  { key: 'content_management', label: 'Content Management' },
  { key: 'reseller_management', label: 'Reseller Management' },
  { key: 'view_only', label: 'View Only' },
]

const adminRoleOptions = computed(() => {
  const names = new Set(props.roles.map(r => r.name))
  return props.roles.filter(r => ['super_admin', 'admin', 'moderator', 'support'].includes(r.name) || names.has(r.name))
})

const resellerFeatures = [
  { key: 'allow_sub_resellers', label: 'Allow sub-resellers' },
  { key: 'allow_vod', label: 'Allow VOD access' },
  { key: 'allow_epg', label: 'Allow EPG access' },
  { key: 'allow_mag', label: 'Allow MAG device support' },
  { key: 'allow_api', label: 'Allow API access' },
  { key: 'white_label', label: 'White-label enabled' },
]

const countries = ['United States', 'United Kingdom', 'Germany', 'France', 'Netherlands', 'Canada', 'Australia', 'Spain', 'Italy', 'Brazil', 'India', 'Japan']

const form = useForm({
  username: '',
  email: '',
  password: '',
  password_confirmation: '',
  first_name: '',
  last_name: '',
  phone: '',
  role: 'client',
  role_id: '',
  is_active: true,
  permissions: [],
  company_name: '',
  website: '',
  credits: 0,
  credit_limit: 0,
  commission_rate: 0,
  max_users: 0,
  max_connections: 2,
  allow_sub_resellers: false,
  white_label: false,
  allow_vod: true,
  allow_epg: true,
  allow_mag: false,
  allow_api: false,
  package_id: '',
  expiry_date: '',
  reseller_id: '',
  mac_address: '',
  ip_restrict: '',
  country: '',
  bouquet_ids: [],
  role_ids: [],
})

const submit = () => {
  if (activeTab.value === 'admin') {
    const selected = props.roles.find(r => r.id === form.role_id)
    form.role = selected?.name || form.role || 'admin'
    form.is_admin = true
    form.role_ids = form.role_id ? [form.role_id] : []
  } else if (activeTab.value === 'reseller') {
    form.role = 'reseller'
    form.is_reseller = true
    const resellerRole = props.roles.find(r => r.name === 'reseller')
    form.role_ids = resellerRole ? [resellerRole.id] : []
  } else {
    form.role = 'client'
    form.role_ids = []
  }
  form.post(route('admin.users.store'))
}
</script>
