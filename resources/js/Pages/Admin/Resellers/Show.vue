<template>
  <AdminLayout>
    <div class="p-6 space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <Link :href="route('admin.resellers.index')" class="text-gray-400 hover:text-white">
            <ArrowLeft class="w-5 h-5" />
          </Link>
          <div>
            <h1 class="text-2xl font-bold text-white">{{ resellerFullName(reseller) }}</h1>
            <p class="text-gray-400 text-sm">Reseller Profile</p>
          </div>
        </div>
        <div class="flex gap-2">
          <Link :href="route('admin.resellers.edit', reseller.id)" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition flex items-center gap-2">
            <Pencil class="w-4 h-4" /> Edit
          </Link>
          <button @click="toggleStatus" class="px-4 py-2 rounded-lg transition flex items-center gap-2"
            :class="reseller.is_active ? 'bg-yellow-600/20 text-yellow-400 hover:bg-yellow-600/30' : 'bg-green-600/20 text-green-400 hover:bg-green-600/30'">
            <UserX v-if="reseller.is_active" class="w-4 h-4" /> <UserCheck v-else class="w-4 h-4" />
            {{ reseller.is_active ? 'Deactivate' : 'Activate' }}
          </button>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-400 text-sm">Total Sub-clients</p>
              <p class="text-2xl font-bold text-white mt-1">{{ subClients?.length ?? 0 }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-blue-500/20 flex items-center justify-center">
              <Users class="w-5 h-5 text-blue-400" />
            </div>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-400 text-sm">Credits Remaining</p>
              <p class="text-2xl font-bold text-yellow-400 mt-1">${{ reseller.credits ?? 0 }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-yellow-500/20 flex items-center justify-center">
              <CreditCard class="w-5 h-5 text-yellow-400" />
            </div>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-400 text-sm">Commission Earned</p>
              <p class="text-2xl font-bold text-green-400 mt-1">${{ reseller.commission_earned ?? 0 }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-green-500/20 flex items-center justify-center">
              <TrendingUp class="w-5 h-5 text-green-400" />
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Reseller Info Card -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 lg:col-span-1">
          <h2 class="text-lg font-semibold text-white mb-4">Reseller Details</h2>
          <div class="space-y-3">
            <div class="flex justify-between">
              <span class="text-gray-400">Username</span>
              <span class="text-white font-medium">{{ reseller.username }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Email</span>
              <span class="text-white">{{ reseller.email }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Company</span>
              <span class="text-white">{{ reseller.company_name || 'N/A' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Website</span>
              <a v-if="reseller.website" :href="reseller.website" target="_blank" class="text-indigo-400 hover:text-indigo-300 text-sm truncate max-w-[160px] block text-right">{{ reseller.website }}</a>
              <span v-else class="text-white">N/A</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Credits</span>
              <span class="text-yellow-400 font-medium">${{ reseller.credits ?? 0 }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Credit Limit</span>
              <span class="text-white">${{ reseller.credit_limit ?? 0 }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Commission Rate</span>
              <span class="text-white">{{ reseller.commission_rate ?? 0 }}%</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">White Label</span>
              <span class="text-white">{{ reseller.white_label ? 'Yes' : 'No' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Sub-resellers</span>
              <span class="text-white">{{ reseller.allow_sub_resellers ? 'Allowed' : 'Disabled' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Status</span>
              <span class="px-2 py-0.5 text-xs rounded-full" :class="reseller.is_active ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'">
                {{ reseller.is_active ? 'Active' : 'Inactive' }}
              </span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Created</span>
              <span class="text-white">{{ formatDate(reseller.created_at) }}</span>
            </div>
          </div>
        </div>

        <!-- Right Column -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Sub-clients Table -->
          <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
            <div class="p-4 border-b border-gray-700 flex items-center justify-between">
              <h2 class="text-lg font-semibold text-white">Sub-clients</h2>
              <Link :href="route('admin.users.create') + '?role=client&reseller_id=' + reseller.id" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm transition flex items-center gap-1">
                <UserPlus class="w-3.5 h-3.5" /> Add Client
              </Link>
            </div>
            <table v-if="subClients?.length" class="w-full">
              <thead>
                <tr class="border-b border-gray-700">
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Username</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Email</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Expiry</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-700">
                <tr v-for="client in subClients" :key="client.id" class="hover:bg-gray-700/50">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center gap-3">
                      <div class="w-8 h-8 rounded-full bg-purple-600 flex items-center justify-center text-white text-sm font-semibold">
                        {{ (client.username || 'C').charAt(0).toUpperCase() }}
                      </div>
                      <span class="text-white text-sm">{{ client.username }}</span>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-gray-300 text-sm">{{ client.email }}</td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 py-1 text-xs rounded-full" :class="client.is_active ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'">
                      {{ client.is_active ? 'Active' : 'Inactive' }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-gray-300 text-sm">{{ formatDate(client.subscription_end_date) }}</td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <Link :href="route('admin.clients.show', client.id)" class="text-indigo-400 hover:text-indigo-300 text-sm">
                      View
                    </Link>
                  </td>
                </tr>
              </tbody>
            </table>
            <div v-else class="p-8 text-center text-gray-500">
              No sub-clients yet
            </div>
          </div>

          <!-- Activity Log -->
          <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
            <h2 class="text-lg font-semibold text-white mb-4">Activity Log</h2>
            <div v-if="activityLogs?.length" class="space-y-2">
              <div v-for="log in activityLogs" :key="log.id" class="flex items-start gap-3 p-2 rounded hover:bg-gray-700/30">
                <div class="w-2 h-2 rounded-full bg-blue-500 mt-2"></div>
                <div class="flex-1">
                  <p class="text-white text-sm">{{ log.description }}</p>
                  <p class="text-gray-500 text-xs">{{ formatDate(log.created_at) }} from {{ log.ip_address || 'N/A' }}</p>
                </div>
              </div>
            </div>
            <p v-else class="text-gray-500 text-center py-4">No activity logs</p>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeft, Pencil, UserX, UserCheck, Users, CreditCard, TrendingUp, UserPlus } from 'lucide-vue-next'

const props = defineProps({
  reseller: { type: Object, required: true },
  subClients: { type: Array, default: () => [] },
  activityLogs: { type: Array, default: () => [] },
})

const resellerFullName = (r) => {
  const parts = [r.first_name, r.last_name].filter(Boolean)
  return parts.length ? parts.join(' ') : r.username || r.email
}

const formatDate = (date) => {
  if (!date) return 'N/A'
  return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

const toggleStatus = () => {
  if (confirm(`Are you sure you want to ${props.reseller.is_active ? 'deactivate' : 'activate'} this reseller?`)) {
    router.post(route('admin.resellers.toggle-status', props.reseller.id), {}, { preserveScroll: true })
  }
}
</script>
