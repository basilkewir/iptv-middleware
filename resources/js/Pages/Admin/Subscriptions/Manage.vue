<template>
  <AdminLayout>
    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-white">Subscription Management</h1>
          <p class="text-gray-400 mt-1">Manage and monitor all user subscriptions</p>
        </div>
        <Link :href="route('admin.subscriptions.packages')" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition flex items-center gap-2">
          <Package class="w-4 h-4" />
          Manage Packages
        </Link>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-indigo-500/20 rounded-lg">
              <Users class="w-5 h-5 text-indigo-400" />
            </div>
            <div>
              <p class="text-gray-400 text-sm">Total Active</p>
              <p class="text-2xl font-bold text-white">{{ stats?.total_active || 0 }}</p>
            </div>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-green-500/20 rounded-lg">
              <DollarSign class="w-5 h-5 text-green-400" />
            </div>
            <div>
              <p class="text-gray-400 text-sm">Monthly Revenue</p>
              <p class="text-2xl font-bold text-green-400">${{ formatNumber(stats?.monthly_revenue || 0) }}</p>
            </div>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-yellow-500/20 rounded-lg">
              <Clock class="w-5 h-5 text-yellow-400" />
            </div>
            <div>
              <p class="text-gray-400 text-sm">Expiring Soon</p>
              <p class="text-2xl font-bold text-yellow-400">{{ stats?.expiring_soon || 0 }}</p>
            </div>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-red-500/20 rounded-lg">
              <XCircle class="w-5 h-5 text-red-400" />
            </div>
            <div>
              <p class="text-gray-400 text-sm">Expired</p>
              <p class="text-2xl font-bold text-red-400">{{ stats?.expired || 0 }}</p>
            </div>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-purple-500/20 rounded-lg">
              <TrendingUp class="w-5 h-5 text-purple-400" />
            </div>
            <div>
              <p class="text-gray-400 text-sm">Avg. Revenue</p>
              <p class="text-2xl font-bold text-purple-400">${{ formatNumber(stats?.avg_revenue || 0) }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 p-4">
        <div class="flex flex-wrap items-center gap-4">
          <div class="flex-1 min-w-[200px]">
            <div class="relative">
              <Search class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                v-model="search"
                type="text"
                placeholder="Search by user, email, or username..."
                class="w-full pl-10 pr-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500"
              />
            </div>
          </div>
          <select v-model="filterStatus" class="px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            <option value="">All Statuses</option>
            <option value="active">Active</option>
            <option value="expiring">Expiring Soon</option>
            <option value="expired">Expired</option>
            <option value="cancelled">Cancelled</option>
            <option value="suspended">Suspended</option>
          </select>
          <select v-model="filterPackage" class="px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            <option value="">All Packages</option>
            <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">{{ pkg.name }}</option>
          </select>
          <select v-model="sortBy" class="px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            <option value="end_date">Sort by Expiry</option>
            <option value="created_at">Sort by Created</option>
            <option value="price">Sort by Price</option>
          </select>
          <button @click="resetFilters" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">
            Reset
          </button>
        </div>
      </div>

      <!-- Subscriptions Table -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="border-b border-gray-700">
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">User</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Package</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Price</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Start Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Expiry</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Days Left</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
              <tr v-for="sub in filteredSubscriptions" :key="sub.id" class="hover:bg-gray-700/50 transition-colors">
                <td class="px-6 py-4">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-indigo-500/20 rounded-full flex items-center justify-center">
                      <span class="text-indigo-400 font-medium text-sm">{{ userInitials(sub.user) }}</span>
                    </div>
                    <div>
                      <p class="text-white font-medium">{{ userFullName(sub.user) }}</p>
                      <p class="text-gray-400 text-sm">{{ sub.user?.email }}</p>
                      <p class="text-gray-500 text-xs">@{{ sub.user?.username }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <span class="px-2 py-1 text-xs rounded-full bg-indigo-500/20 text-indigo-400">
                    {{ sub.subscriptionPackage?.name || sub.subscription_package_id }}
                  </span>
                </td>
                <td class="px-6 py-4 text-green-400 font-medium">${{ sub.subscriptionPackage?.price || '0.00' }}</td>
                <td class="px-6 py-4">
                  <span class="px-2 py-1 text-xs rounded-full" :class="subStatusClass(sub)">
                    {{ sub.status }}
                  </span>
                </td>
                <td class="px-6 py-4 text-gray-400 text-sm">{{ formatDate(sub.start_date) }}</td>
                <td class="px-6 py-4">
                  <span class="text-sm" :class="daysLeftClass(sub)">{{ formatDate(sub.end_date) }}</span>
                </td>
                <td class="px-6 py-4">
                  <div class="flex items-center gap-2">
                    <div class="w-12 bg-gray-700 rounded-full h-1.5">
                      <div
                        class="h-1.5 rounded-full"
                        :class="daysLeftBarColor(sub)"
                        :style="{ width: Math.min(daysLeft(sub) * 3.33, 100) + '%' }"
                      />
                    </div>
                    <span class="text-sm font-medium" :class="daysLeftClass(sub)">{{ daysLeft(sub) }}d</span>
                  </div>
                </td>
                <td class="px-6 py-4 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <button @click="openExtendModal(sub)" class="p-2 bg-green-600/20 hover:bg-green-600 text-green-400 hover:text-white rounded-lg transition" title="Extend">
                      <CalendarPlus class="w-4 h-4" />
                    </button>
                    <button @click="openEditModal(sub)" class="p-2 bg-blue-600/20 hover:bg-blue-600 text-blue-400 hover:text-white rounded-lg transition" title="Edit">
                      <Edit2 class="w-4 h-4" />
                    </button>
                    <button @click="suspendSubscription(sub)" v-if="sub.status === 'active'" class="p-2 bg-yellow-600/20 hover:bg-yellow-600 text-yellow-400 hover:text-white rounded-lg transition" title="Suspend">
                      <PauseCircle class="w-4 h-4" />
                    </button>
                    <button @click="reactivateSubscription(sub)" v-if="sub.status === 'suspended'" class="p-2 bg-green-600/20 hover:bg-green-600 text-green-400 hover:text-white rounded-lg transition" title="Reactivate">
                      <PlayCircle class="w-4 h-4" />
                    </button>
                    <button @click="cancelSubscription(sub)" v-if="sub.status !== 'cancelled' && sub.status !== 'expired'" class="p-2 bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white rounded-lg transition" title="Cancel">
                      <XCircle class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredSubscriptions.length === 0">
                <td colspan="8" class="px-6 py-12 text-center text-gray-400">
                  <Package class="w-12 h-12 mx-auto mb-3 text-gray-600" />
                  <p>No subscriptions found</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="subscriptions?.last_page > 1" class="px-6 py-4 border-t border-gray-700 flex items-center justify-between">
          <p class="text-sm text-gray-400">
            Showing {{ subscriptions?.from }} to {{ subscriptions?.to }} of {{ subscriptions?.total }} subscriptions
          </p>
          <div class="flex gap-2">
            <Link
              v-for="page in paginationPages"
              :key="page"
              :href="route('admin.subscriptions.manage', { page, search, filterStatus, filterPackage, sortBy })"
              class="px-3 py-1 text-sm rounded-lg transition"
              :class="page === (subscriptions?.current_page || 1) ? 'bg-indigo-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'"
            >
              {{ page }}
            </Link>
          </div>
        </div>
      </div>

      <!-- Extend Modal -->
      <div v-if="showExtendModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
        <div class="bg-gray-800 rounded-xl p-6 w-full max-w-md border border-gray-700 shadow-2xl">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-semibold text-white">Extend Subscription</h2>
            <button @click="showExtendModal = false" class="text-gray-400 hover:text-white transition">
              <X class="w-5 h-5" />
            </button>
          </div>
          <div v-if="selectedSub" class="space-y-4">
            <div class="bg-gray-700/50 rounded-lg p-4">
              <p class="text-gray-400 text-sm">User</p>
              <p class="text-white font-medium">{{ userFullName(selectedSub.user) }}</p>
            </div>
            <div class="bg-gray-700/50 rounded-lg p-4">
              <p class="text-gray-400 text-sm">Current Package</p>
              <p class="text-white font-medium">{{ selectedSub.subscriptionPackage?.name }}</p>
            </div>
            <div class="bg-gray-700/50 rounded-lg p-4">
              <p class="text-gray-400 text-sm">Current Expiry</p>
              <p class="text-white font-medium">{{ formatDate(selectedSub.end_date) }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Extend By (Days)</label>
              <div class="grid grid-cols-4 gap-2 mb-3">
                <button
                  v-for="d in [7, 14, 30, 90]"
                  :key="d"
                  @click="extendDays = d"
                  class="px-3 py-2 rounded-lg text-sm transition font-medium"
                  :class="extendDays === d ? 'bg-indigo-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'"
                >
                  {{ d }}d
                </button>
              </div>
              <div class="relative">
                <input
                  v-model.number="extendDays"
                  type="number"
                  min="1"
                  max="365"
                  class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500"
                />
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">days</span>
              </div>
            </div>
            <div class="bg-indigo-500/10 border border-indigo-500/30 rounded-lg p-4">
              <p class="text-gray-400 text-sm">New Expiry Date</p>
              <p class="text-indigo-400 font-medium">{{ newExpiryDate }}</p>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button @click="showExtendModal = false" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">Cancel</button>
            <button @click="confirmExtend" class="px-4 py-2 bg-green-600 hover:bg-green-500 text-white rounded-lg transition flex items-center gap-2">
              <CalendarPlus class="w-4 h-4" />
              Extend Subscription
            </button>
          </div>
        </div>
      </div>

      <!-- Edit Package Modal -->
      <div v-if="showEditModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
        <div class="bg-gray-800 rounded-xl p-6 w-full max-w-md border border-gray-700 shadow-2xl">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-semibold text-white">Edit Subscription</h2>
            <button @click="showEditModal = false" class="text-gray-400 hover:text-white transition">
              <X class="w-5 h-5" />
            </button>
          </div>
          <div v-if="selectedSub" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Change Package</label>
              <select v-model="editPackageId" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">{{ pkg.name }} - ${{ pkg.price }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Set Expiry Date</label>
              <input v-model="editEndDate" type="date" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Status</label>
              <select v-model="editStatus" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
                <option value="active">Active</option>
                <option value="suspended">Suspended</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button @click="showEditModal = false" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">Cancel</button>
            <button @click="confirmEdit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition flex items-center gap-2">
              <Save class="w-4 h-4" />
              Save Changes
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  Users, DollarSign, Clock, XCircle, TrendingUp, Search, Package,
  CalendarPlus, Edit2, PauseCircle, PlayCircle, X, Save
} from 'lucide-vue-next'

const props = defineProps({
  subscriptions: { type: Object, default: () => ({ data: [] }) },
  packages: { type: Array, default: () => [] },
  stats: { type: Object, default: () => ({}) },
})

const search = ref('')
const filterStatus = ref('')
const filterPackage = ref('')
const sortBy = ref('end_date')
const showExtendModal = ref(false)
const showEditModal = ref(false)
const selectedSub = ref(null)
const extendDays = ref(30)
const editPackageId = ref('')
const editEndDate = ref('')
const editStatus = ref('')

const filteredSubscriptions = computed(() => {
  let subs = props.subscriptions?.data || []
  if (search.value) {
    const q = search.value.toLowerCase()
    subs = subs.filter(s =>
      userFullName(s.user).toLowerCase().includes(q) ||
      s.user?.email?.toLowerCase().includes(q) ||
      s.user?.username?.toLowerCase().includes(q)
    )
  }
  if (filterStatus.value) {
    subs = subs.filter(s => s.status === filterStatus.value)
  }
  if (filterPackage.value) {
    subs = subs.filter(s => s.subscription_package_id == filterPackage.value)
  }
  return subs
})

const paginationPages = computed(() => {
  const total = props.subscriptions?.last_page || 1
  return Array.from({ length: total }, (_, i) => i + 1)
})

const userFullName = (user) => {
  if (!user) return 'Unknown'
  const parts = [user.first_name, user.last_name].filter(Boolean)
  return parts.length ? parts.join(' ') : user.username || user.email
}

const userInitials = (user) => {
  if (!user) return '?'
  if (user.first_name && user.last_name) return (user.first_name[0] + user.last_name[0]).toUpperCase()
  if (user.username) return user.username.slice(0, 2).toUpperCase()
  return user.email?.slice(0, 2).toUpperCase() || '?'
}

const subStatusClass = (sub) => ({
  'bg-green-500/20 text-green-400': sub.status === 'active',
  'bg-yellow-500/20 text-yellow-400': sub.status === 'expiring',
  'bg-red-500/20 text-red-400': sub.status === 'expired' || sub.status === 'cancelled',
  'bg-orange-500/20 text-orange-400': sub.status === 'suspended',
})

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

const formatNumber = (num) => Number(num).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const daysLeft = (sub) => {
  const end = new Date(sub.end_date)
  const now = new Date()
  const diff = Math.ceil((end - now) / (1000 * 60 * 60 * 24))
  return Math.max(0, diff)
}

const daysLeftClass = (sub) => {
  const d = daysLeft(sub)
  if (d <= 3) return 'text-red-400'
  if (d <= 7) return 'text-yellow-400'
  return 'text-green-400'
}

const daysLeftBarColor = (sub) => {
  const d = daysLeft(sub)
  if (d <= 3) return 'bg-red-500'
  if (d <= 7) return 'bg-yellow-500'
  return 'bg-green-500'
}

const newExpiryDate = computed(() => {
  if (!selectedSub.value || !extendDays.value) return '-'
  const end = new Date(selectedSub.value.end_date)
  end.setDate(end.getDate() + extendDays.value)
  return end.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })
})

const resetFilters = () => {
  search.value = ''
  filterStatus.value = ''
  filterPackage.value = ''
  sortBy.value = 'end_date'
}

const openExtendModal = (sub) => {
  selectedSub.value = sub
  extendDays.value = 30
  showExtendModal.value = true
}

const openEditModal = (sub) => {
  selectedSub.value = sub
  editPackageId.value = sub.subscription_package_id
  editEndDate.value = sub.end_date
  editStatus.value = sub.status
  showEditModal.value = true
}

const confirmExtend = () => {
  router.post(route('admin.subscriptions.extend', selectedSub.value.id), { days: extendDays.value })
  showExtendModal.value = false
}

const confirmEdit = () => {
  router.put(route('admin.subscriptions.update', selectedSub.value.id), {
    subscription_package_id: editPackageId.value,
    end_date: editEndDate.value,
    status: editStatus.value,
  })
  showEditModal.value = false
}

const suspendSubscription = (sub) => {
  if (confirm(`Suspend subscription for ${userFullName(sub.user)}?`)) {
    router.post(route('admin.subscriptions.cancel', sub.id), { status: 'suspended' })
  }
}

const reactivateSubscription = (sub) => {
  if (confirm(`Reactivate subscription for ${userFullName(sub.user)}?`)) {
    router.post(route('admin.subscriptions.extend', sub.id), { reactivate: true })
  }
}

const cancelSubscription = (sub) => {
  if (confirm(`Cancel subscription for ${userFullName(sub.user)}? This action cannot be undone.`)) {
    router.post(route('admin.subscriptions.cancel', sub.id))
  }
}
</script>
