<template>
  <AdminLayout>
    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-white">Invoices</h1>
          <p class="text-gray-400 mt-1">Manage invoices and payments</p>
        </div>
        <button @click="exportCSV" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition flex items-center gap-2">
          <Download class="w-4 h-4" />
          Export CSV
        </button>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-400 text-sm">Total Revenue</p>
              <p class="text-2xl font-bold text-green-400 mt-1">${{ stats.total_revenue ?? 0 }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-green-500/20 flex items-center justify-center">
              <DollarSign class="w-5 h-5 text-green-400" />
            </div>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-400 text-sm">Pending</p>
              <p class="text-2xl font-bold text-yellow-400 mt-1">${{ stats.pending ?? 0 }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-yellow-500/20 flex items-center justify-center">
              <Clock class="w-5 h-5 text-yellow-400" />
            </div>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-400 text-sm">Paid</p>
              <p class="text-2xl font-bold text-blue-400 mt-1">${{ stats.paid ?? 0 }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-blue-500/20 flex items-center justify-center">
              <CheckCircle class="w-5 h-5 text-blue-400" />
            </div>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-400 text-sm">Overdue</p>
              <p class="text-2xl font-bold text-red-400 mt-1">${{ stats.overdue ?? 0 }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-red-500/20 flex items-center justify-center">
              <AlertCircle class="w-5 h-5 text-red-400" />
            </div>
          </div>
        </div>
      </div>

      <!-- Filter Tabs -->
      <div class="flex gap-2 flex-wrap">
        <button v-for="tab in filterTabs" :key="tab.key" @click="activeTab = tab.key"
          class="px-4 py-2 rounded-lg text-sm font-medium transition"
          :class="activeTab === tab.key ? 'bg-indigo-600 text-white' : 'bg-gray-800 text-gray-400 hover:text-white hover:bg-gray-700'">
          {{ tab.label }}
          <span v-if="tab.count !== undefined" class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-gray-700">{{ tab.count }}</span>
        </button>
      </div>

      <!-- Table -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
        <table class="w-full">
          <thead>
            <tr class="border-b border-gray-700">
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Invoice #</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Client</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Amount</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Date</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-700">
            <tr v-for="invoice in invoices?.data || []" :key="invoice.id" class="hover:bg-gray-700/50">
              <td class="px-6 py-4 whitespace-nowrap text-white font-medium text-sm">#{{ invoice.invoice_number }}</td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white text-sm font-semibold">
                    {{ (invoice.client?.username || 'C').charAt(0).toUpperCase() }}
                  </div>
                  <div>
                    <p class="text-white text-sm">{{ invoice.client?.username || 'N/A' }}</p>
                    <p class="text-gray-400 text-xs">{{ invoice.client?.email || '' }}</p>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-white font-medium text-sm">${{ invoice.amount }}</td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 text-xs rounded-full" :class="statusClass(invoice.status)">
                  {{ invoice.status }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-400 text-sm">{{ formatDate(invoice.created_at) }}</td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center gap-2">
                  <Link :href="route('admin.invoices.show', invoice.id)" class="p-1.5 hover:bg-gray-600 rounded-lg transition text-gray-400 hover:text-white">
                    <Eye class="w-4 h-4" />
                  </Link>
                  <button @click="updateStatus(invoice)" class="p-1.5 hover:bg-gray-600 rounded-lg transition text-gray-400 hover:text-white">
                    <RefreshCw class="w-4 h-4" />
                  </button>
                  <button @click="confirmDelete(invoice)" class="p-1.5 hover:bg-red-600/20 rounded-lg transition text-gray-400 hover:text-red-400">
                    <Trash2 class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="!invoices?.data?.length" class="p-8 text-center text-gray-500">
          No invoices found
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="invoices?.links" class="flex items-center justify-between">
        <p class="text-gray-400 text-sm">Showing {{ invoices.from }} to {{ invoices.to }} of {{ invoices.total }} invoices</p>
        <div class="flex gap-2">
          <Link v-for="page in invoices.links" :key="page.label" :href="page.url || '#'" class="px-3 py-1 rounded-lg text-sm"
            :class="page.active ? 'bg-indigo-600 text-white' : 'bg-gray-700 text-gray-400 hover:bg-gray-600'" preserve-scroll>
            {{ page.label }}
          </Link>
        </div>
      </div>

      <!-- Update Status Modal -->
      <div v-if="showStatusModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="showStatusModal = false">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 w-full max-w-md">
          <h3 class="text-lg font-semibold text-white mb-4">Update Invoice Status</h3>
          <p class="text-gray-400 text-sm mb-4">Invoice #{{ statusTarget?.invoice_number }}</p>
          <select v-model="newStatus" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500 mb-4">
            <option value="pending">Pending</option>
            <option value="paid">Paid</option>
            <option value="overdue">Overdue</option>
            <option value="cancelled">Cancelled</option>
          </select>
          <div class="flex justify-end gap-3">
            <button @click="showStatusModal = false" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">Cancel</button>
            <button @click="saveStatus" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition">Save</button>
          </div>
        </div>
      </div>

      <!-- Delete Modal -->
      <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 w-full max-w-md">
          <h3 class="text-lg font-semibold text-white mb-2">Delete Invoice</h3>
          <p class="text-gray-400">Are you sure you want to delete invoice <strong class="text-white">#{{ deleteTarget?.invoice_number }}</strong>? This action cannot be undone.</p>
          <div class="flex justify-end gap-3 mt-6">
            <button @click="showDeleteModal = false" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">Cancel</button>
            <button @click="performDelete" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded-lg transition">Delete</button>
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
import { Download, DollarSign, Clock, CheckCircle, AlertCircle, Eye, RefreshCw, Trash2 } from 'lucide-vue-next'

const props = defineProps({
  invoices: { type: Object, default: () => ({ data: [], links: [], from: 0, to: 0, total: 0 }) },
  stats: { type: Object, default: () => ({}) },
})

const activeTab = ref('all')
const showDeleteModal = ref(false)
const showStatusModal = ref(false)
const deleteTarget = ref(null)
const statusTarget = ref(null)
const newStatus = ref('pending')

const filterTabs = computed(() => [
  { key: 'all', label: 'All', count: props.invoices?.total },
  { key: 'pending', label: 'Pending', count: props.stats.pending_count },
  { key: 'paid', label: 'Paid', count: props.stats.paid_count },
  { key: 'overdue', label: 'Overdue', count: props.stats.overdue_count },
])

const statusClass = (status) => {
  const map = {
    pending: 'bg-yellow-500/20 text-yellow-400',
    paid: 'bg-green-500/20 text-green-400',
    overdue: 'bg-red-500/20 text-red-400',
    cancelled: 'bg-gray-500/20 text-gray-400',
  }
  return map[status] || 'bg-gray-500/20 text-gray-400'
}

const formatDate = (d) => {
  if (!d) return ''
  return new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

const updateStatus = (invoice) => {
  statusTarget.value = invoice
  newStatus.value = invoice.status
  showStatusModal.value = true
}

const saveStatus = () => {
  router.put(route('admin.invoices.update-status', statusTarget.value.id), { status: newStatus.value }, { preserveScroll: true })
  showStatusModal.value = false
}

const confirmDelete = (invoice) => {
  deleteTarget.value = invoice
  showDeleteModal.value = true
}

const performDelete = () => {
  if (deleteTarget.value) {
    router.delete(route('admin.invoices.destroy', deleteTarget.value.id))
    showDeleteModal.value = false
    deleteTarget.value = null
  }
}

const exportCSV = () => {
  router.get(route('admin.invoices.export'), {}, { preserveScroll: true })
}
</script>
