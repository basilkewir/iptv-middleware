<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto">
      <div class="mb-6">
        <Link :href="route('admin.invoices.index')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
          <ArrowLeft class="w-4 h-4" /> Back to Invoices
        </Link>
      </div>

      <!-- Invoice Header -->
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 mb-6">
        <div class="flex items-start justify-between">
          <div>
            <h1 class="text-2xl font-bold text-white">Invoice #{{ invoice.invoice_number }}</h1>
            <p class="text-gray-400 mt-1">Created {{ formatDate(invoice.created_at) }}</p>
          </div>
          <div class="text-right">
            <span class="px-3 py-1.5 text-sm rounded-full font-medium" :class="statusClass(invoice.status)">
              {{ invoice.status }}
            </span>
            <div class="mt-3">
              <button @click="showStatusModal = true" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition text-sm flex items-center gap-2">
                <RefreshCw class="w-4 h-4" /> Update Status
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Client Info -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 lg:col-span-2">
          <h2 class="text-lg font-semibold text-white mb-4">Client Information</h2>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-gray-400 text-sm">Username</p>
              <p class="text-white">{{ invoice.client?.username || 'N/A' }}</p>
            </div>
            <div>
              <p class="text-gray-400 text-sm">Email</p>
              <p class="text-white">{{ invoice.client?.email || 'N/A' }}</p>
            </div>
            <div>
              <p class="text-gray-400 text-sm">Full Name</p>
              <p class="text-white">{{ clientFullName }}</p>
            </div>
            <div>
              <p class="text-gray-400 text-sm">Company</p>
              <p class="text-white">{{ invoice.client?.company_name || 'N/A' }}</p>
            </div>
          </div>
        </div>

        <!-- Invoice Summary -->
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h2 class="text-lg font-semibold text-white mb-4">Invoice Summary</h2>
          <div class="space-y-3">
            <div class="flex justify-between">
              <span class="text-gray-400">Invoice #</span>
              <span class="text-white font-mono">{{ invoice.invoice_number }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Date Issued</span>
              <span class="text-white">{{ formatDate(invoice.created_at) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Due Date</span>
              <span class="text-white">{{ formatDate(invoice.due_date) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Payment Method</span>
              <span class="text-white">{{ invoice.payment_method || 'N/A' }}</span>
            </div>
            <hr class="border-gray-700" />
            <div class="flex justify-between">
              <span class="text-gray-400">Status</span>
              <span class="px-2 py-0.5 text-xs rounded-full" :class="statusClass(invoice.status)">
                {{ invoice.status }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Line Items -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden mb-6">
        <div class="p-4 border-b border-gray-700">
          <h2 class="text-lg font-semibold text-white">Line Items</h2>
        </div>
        <table class="w-full">
          <thead>
            <tr class="border-b border-gray-700">
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Description</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Qty</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Unit Price</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Total</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-700">
            <tr v-for="(item, i) in lineItems" :key="i" class="hover:bg-gray-700/50">
              <td class="px-6 py-4 text-white text-sm">{{ item.description }}</td>
              <td class="px-6 py-4 text-right text-gray-300 text-sm">{{ item.quantity }}</td>
              <td class="px-6 py-4 text-right text-gray-300 text-sm">${{ item.unit_price }}</td>
              <td class="px-6 py-4 text-right text-white text-sm font-medium">${{ (item.quantity * item.unit_price).toFixed(2) }}</td>
            </tr>
          </tbody>
        </table>
        <div v-if="!lineItems.length" class="p-8 text-center text-gray-500">
          No line items
        </div>

        <!-- Totals -->
        <div class="border-t border-gray-700 p-6">
          <div class="flex justify-end">
            <div class="w-64 space-y-2">
              <div class="flex justify-between text-sm">
                <span class="text-gray-400">Subtotal</span>
                <span class="text-white">${{ subtotal }}</span>
              </div>
              <div v-if="invoice.tax" class="flex justify-between text-sm">
                <span class="text-gray-400">Tax ({{ invoice.tax }}%)</span>
                <span class="text-white">${{ taxAmount }}</span>
              </div>
              <div v-if="invoice.discount" class="flex justify-between text-sm">
                <span class="text-gray-400">Discount</span>
                <span class="text-green-400">-${{ invoice.discount }}</span>
              </div>
              <hr class="border-gray-700" />
              <div class="flex justify-between">
                <span class="text-white font-semibold">Total</span>
                <span class="text-white font-bold text-lg">${{ invoice.amount }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Notes -->
      <div v-if="invoice.notes" class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        <h2 class="text-lg font-semibold text-white mb-2">Notes</h2>
        <p class="text-gray-300 text-sm whitespace-pre-wrap">{{ invoice.notes }}</p>
      </div>

      <!-- Update Status Modal -->
      <div v-if="showStatusModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="showStatusModal = false">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 w-full max-w-md">
          <h3 class="text-lg font-semibold text-white mb-4">Update Invoice Status</h3>
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
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeft, RefreshCw } from 'lucide-vue-next'

const props = defineProps({
  invoice: { type: Object, required: true },
})

const showStatusModal = ref(false)
const newStatus = ref(props.invoice.status)

const lineItems = computed(() => props.invoice.line_items || [])

const clientFullName = computed(() => {
  const c = props.invoice.client
  if (!c) return 'N/A'
  const parts = [c.first_name, c.last_name].filter(Boolean)
  return parts.length ? parts.join(' ') : c.username || 'N/A'
})

const subtotal = computed(() => {
  return lineItems.value.reduce((sum, item) => sum + (item.quantity * item.unit_price), 0).toFixed(2)
})

const taxAmount = computed(() => {
  if (!props.invoice.tax) return '0.00'
  return (subtotal.value * props.invoice.tax / 100).toFixed(2)
})

const statusClass = (status) => {
  const map = {
    pending: 'bg-yellow-500/20 text-yellow-400',
    paid: 'bg-green-500/20 text-green-400',
    overdue: 'bg-red-500/20 text-red-400',
    cancelled: 'bg-gray-500/20 text-gray-400',
  }
  return map[status] || 'bg-gray-500/20 text-gray-400'
}

const formatDate = (date) => {
  if (!date) return 'N/A'
  return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

const saveStatus = () => {
  router.put(route('admin.invoices.update-status', props.invoice.id), { status: newStatus.value }, { preserveScroll: true })
  showStatusModal.value = false
}
</script>
