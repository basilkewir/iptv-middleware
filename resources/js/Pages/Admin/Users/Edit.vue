<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto">
      <div class="mb-6">
        <Link :href="route('admin.users.index')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
          <ArrowLeft class="w-4 h-4" /> Back to Users
        </Link>
        <div class="flex items-center gap-4">
          <div class="w-14 h-14 rounded-full bg-indigo-600 flex items-center justify-center text-white font-bold text-xl">
            {{ (user.first_name || user.username || 'U').charAt(0).toUpperCase() }}
          </div>
          <div>
            <h1 class="text-2xl font-bold text-white">{{ userFullName }}</h1>
            <p class="text-gray-400">{{ user.email }}</p>
          </div>
          <span class="ml-auto px-3 py-1 text-sm rounded-full" :class="user.is_active ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'">
            {{ user.is_active ? 'Active' : 'Inactive' }}
          </span>
          <Link :href="route('admin.users.channels', user.id)" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition flex items-center gap-2">
            <Tv class="w-4 h-4" /> Manage Channels
          </Link>
        </div>
      </div>

      <!-- Tab Navigation -->
      <div class="flex gap-1 bg-gray-800 rounded-lg p-1 mb-6 border border-gray-700">
        <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
          class="flex-1 px-4 py-2.5 rounded-md text-sm font-medium transition"
          :class="activeTab === tab.key ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white'">
          {{ tab.label }}
        </button>
      </div>

      <!-- Profile Tab -->
      <form v-if="activeTab === 'profile'" @submit.prevent="profileForm.put(route('admin.users.update', user.id))" class="bg-gray-800 rounded-xl p-6 border border-gray-700 space-y-6">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Username</label>
            <input :value="user.username" type="text" disabled class="w-full px-4 py-2 bg-gray-600 border border-gray-500 rounded-lg text-gray-400 cursor-not-allowed" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Email *</label>
            <input v-model="profileForm.email" type="email" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">First Name</label>
            <input v-model="profileForm.first_name" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Last Name</label>
            <input v-model="profileForm.last_name" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Phone</label>
            <input v-model="profileForm.phone" type="tel" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Roles</label>
            <select v-model="profileForm.role_ids" multiple class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500 min-h-[120px]">
              <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.label || role.name }}</option>
            </select>
            <p class="text-xs text-gray-500 mt-1">Hold Ctrl/Cmd to select multiple. Role permissions gate what this user can see and manage.</p>
          </div>
        </div>
        <div class="flex items-center gap-6">
          <label class="flex items-center gap-2 cursor-pointer">
            <input v-model="profileForm.is_active" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
            <span class="text-gray-300">Active</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input v-model="profileForm.is_admin" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
            <span class="text-gray-300">Admin</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input v-model="profileForm.is_reseller" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
            <span class="text-gray-300">Reseller</span>
          </label>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">New Password (leave blank to keep)</label>
            <input v-model="profileForm.password" type="password" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Confirm Password</label>
            <input v-model="profileForm.password_confirmation" type="password" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>
        </div>
        <div class="flex justify-end gap-3">
          <button type="submit" :disabled="profileForm.processing" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50">
            {{ profileForm.processing ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>
      </form>

      <!-- Subscription Tab -->
      <div v-if="activeTab === 'subscription'" class="bg-gray-800 rounded-xl p-6 border border-gray-700 space-y-6">
        <div v-if="user.subscription" class="p-4 bg-gray-700/50 rounded-lg">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-white font-semibold">{{ user.subscription?.subscription_package?.name || 'No Plan' }}</h3>
            <span class="px-2 py-1 text-xs rounded-full" :class="user.subscription?.status === 'active' ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'">
              {{ user.subscription?.status }}
            </span>
          </div>
          <div class="grid grid-cols-3 gap-4 text-sm">
            <div><span class="text-gray-400">Started:</span> <span class="text-white">{{ user.subscription?.start_date }}</span></div>
            <div><span class="text-gray-400">Expires:</span> <span class="text-white">{{ user.subscription?.end_date }}</span></div>
            <div><span class="text-gray-400">Auto Renew:</span> <span class="text-white">{{ user.subscription?.auto_renew ? 'Yes' : 'No' }}</span></div>
          </div>
        </div>
        <div v-else class="text-center py-8 text-gray-400">No active subscription</div>
        <div class="flex gap-3">
          <button @click="extendSubscription" class="px-4 py-2 bg-green-600 hover:bg-green-500 text-white rounded-lg transition">Extend Subscription</button>
          <button @click="cancelSubscription" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-500 text-white rounded-lg transition">Cancel Subscription</button>
        </div>
      </div>

      <!-- Activity Tab -->
      <div v-if="activeTab === 'activity'" class="space-y-6">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Activity Log</h3>
          <div class="space-y-2">
            <div v-for="log in activityLog" :key="log.id" class="flex items-center gap-3 p-3 bg-gray-700/50 rounded-lg">
              <div class="w-8 h-8 rounded-full bg-indigo-600/20 flex items-center justify-center">
                <Activity class="w-4 h-4 text-indigo-400" />
              </div>
              <div class="flex-1">
                <p class="text-white text-sm">{{ log.action }} - {{ log.description }}</p>
                <p class="text-gray-500 text-xs">{{ log.ip_address }} | {{ log.created_at }}</p>
              </div>
            </div>
            <p v-if="!activityLog?.length" class="text-gray-400 text-center py-4">No activity recorded</p>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Watch History</h3>
          <div class="space-y-2">
            <div v-for="item in watchHistory" :key="item.id" class="flex items-center gap-3 p-3 bg-gray-700/50 rounded-lg">
              <div class="w-8 h-8 rounded-full bg-blue-600/20 flex items-center justify-center">
                <Play class="w-4 h-4 text-blue-400" />
              </div>
              <div class="flex-1">
                <p class="text-white text-sm">{{ item.title || item.channel_name }}</p>
                <p class="text-gray-500 text-xs">{{ item.watched_at }}</p>
              </div>
            </div>
            <p v-if="!watchHistory?.length" class="text-gray-400 text-center py-4">No watch history</p>
          </div>
        </div>
      </div>

      <!-- Settings Tab -->
      <div v-if="activeTab === 'settings'" class="bg-gray-800 rounded-xl p-6 border border-gray-700 space-y-6">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Max Connections</label>
            <input v-model="settingsForm.max_connections" type="number" min="1" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Credits ($)</label>
            <input v-model="settingsForm.credits" type="number" step="0.01" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">MAC Address</label>
            <input v-model="settingsForm.mac_address" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" placeholder="XX:XX:XX:XX:XX:XX" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Country</label>
            <select v-model="settingsForm.country" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
              <option value="">Select Country</option>
              <option v-for="c in countries" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Assigned Reseller</label>
          <select v-model="settingsForm.reseller_id" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            <option value="">None</option>
            <option v-for="r in resellers" :key="r.id" :value="r.id">{{ r.username }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Bouquets</label>
          <div class="space-y-2 max-h-40 overflow-y-auto bg-gray-700 rounded-lg p-3">
            <label v-for="b in bouquets" :key="b.id" class="flex items-center gap-2 text-gray-300 text-sm">
              <input type="checkbox" :value="b.id" v-model="settingsForm.bouquet_ids" class="w-4 h-4 rounded bg-gray-600 border-gray-500 text-indigo-600 focus:ring-indigo-500" />
              {{ b.name }}
            </label>
          </div>
        </div>
        <div class="flex justify-end gap-3 pt-4 border-t border-gray-700">
          <button @click="saveSettings" :disabled="savingSettings" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50">
            {{ savingSettings ? 'Saving...' : 'Save Settings' }}
          </button>
        </div>

        <!-- Reseller-Specific Fields -->
        <div v-if="user.is_reseller" class="pt-4 border-t border-gray-700 space-y-6">
          <h3 class="text-lg font-semibold text-white">Reseller Details</h3>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Company Name</label>
              <input v-model="settingsForm.company_name" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Website URL</label>
              <input v-model="settingsForm.website" type="url" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" placeholder="https://" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Credit Limit ($)</label>
              <input v-model="settingsForm.credit_limit" type="number" step="0.01" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" placeholder="0 = unlimited" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Commission Rate (%)</label>
              <input v-model="settingsForm.commission_rate" type="number" step="0.01" min="0" max="100" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
          </div>
          <div class="flex items-center gap-6">
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="settingsForm.allow_sub_resellers" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <span class="text-gray-300">Allow sub-resellers</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="settingsForm.white_label" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <span class="text-gray-300">White-label enabled</span>
            </label>
          </div>
        </div>
        <div class="pt-4 border-t border-gray-700">
          <button @click="confirmDeleteUser" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded-lg transition">Delete User</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeft, Activity, Play, Tv } from 'lucide-vue-next'

const props = defineProps({
  user: { type: Object, required: true },
  packages: { type: Array, default: () => [] },
  resellers: { type: Array, default: () => [] },
  bouquets: { type: Array, default: () => [] },
  activityLog: { type: Array, default: () => [] },
  watchHistory: { type: Array, default: () => [] },
  roles: { type: Array, default: () => [] },
})

const activeTab = ref('profile')
const savingSettings = ref(false)
const countries = ['United States', 'United Kingdom', 'Germany', 'France', 'Netherlands', 'Canada', 'Australia']

const tabs = [
  { key: 'profile', label: 'Profile' },
  { key: 'subscription', label: 'Subscription' },
  { key: 'activity', label: 'Activity' },
  { key: 'settings', label: 'Settings' },
]

const userFullName = computed(() => {
  const parts = [props.user.first_name, props.user.last_name].filter(Boolean)
  return parts.length ? parts.join(' ') : props.user.username
})

const profileForm = useForm({
  username: props.user.username || '',
  email: props.user.email || '',
  first_name: props.user.first_name || '',
  last_name: props.user.last_name || '',
  phone: props.user.phone || '',
  role: props.user.role || 'client',
  role_ids: props.user.roles?.map(r => r.id) || [],
  is_active: props.user.is_active ?? true,
  is_admin: props.user.is_admin ?? false,
  is_reseller: props.user.is_reseller ?? false,
  password: '',
  password_confirmation: '',
})

const settingsForm = ref({
  max_connections: props.user.max_connections || 1,
  credits: props.user.credits || 0,
  mac_address: props.user.mac_address || '',
  country: props.user.country || '',
  reseller_id: props.user.reseller_id || '',
  bouquet_ids: props.user.bouquets?.map(b => b.id) || [],
  company_name: props.user.company_name || '',
  website: props.user.website || '',
  credit_limit: props.user.credit_limit || 0,
  commission_rate: props.user.commission_rate || 0,
  allow_sub_resellers: props.user.allow_sub_resellers ?? false,
  white_label: props.user.white_label ?? false,
})

const saveSettings = () => {
  savingSettings.value = true
  router.put(route('admin.users.update', props.user.id), settingsForm.value, {
    onFinish: () => { savingSettings.value = false },
    preserveScroll: true,
  })
}

const extendSubscription = () => {
  const days = prompt('Extend by how many days?')
  if (days && props.user.subscription) {
    router.post(route('admin.subscriptions.extend', props.user.subscription.id), { days })
  }
}

const cancelSubscription = () => {
  if (confirm('Cancel this subscription?') && props.user.subscription) {
    router.post(route('admin.subscriptions.cancel', props.user.subscription.id))
  }
}

const confirmDeleteUser = () => {
  if (confirm(`Delete user ${userFullName.value}? This cannot be undone.`)) {
    router.delete(route('admin.users.destroy', props.user.id))
  }
}
</script>
