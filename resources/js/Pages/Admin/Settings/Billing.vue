<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto space-y-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">Billing Settings</h1>
        <p class="text-gray-400 mt-1">Configure invoices, tax, and payment reminders</p>
      </div>
      <form @submit.prevent="form.put(route('admin.settings.billing.update'))" class="space-y-6">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Invoice Configuration</h3>
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-white font-medium mb-2">Invoice Prefix</label>
                <input type="text" v-model="form.invoice_prefix" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" placeholder="INV-" />
              </div>
              <div>
                <label class="block text-white font-medium mb-2">Invoice Due Days</label>
                <input type="number" v-model="form.invoice_due_days" min="1" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" />
              </div>
            </div>
            <div>
              <label class="block text-white font-medium mb-2">Invoice Format</label>
              <div class="space-y-2">
                <label v-for="format in ['Year-Month-Sequence', 'Year-Sequence', 'Sequence Only', 'Custom']" :key="format" class="flex items-center space-x-3 cursor-pointer">
                  <input type="radio" v-model="form.invoice_format" :value="format" class="w-4 h-4 text-indigo-500 bg-gray-700 border-gray-600 focus:ring-indigo-500" />
                  <span class="text-gray-300">{{ format }}</span>
                </label>
              </div>
            </div>
            <div class="flex items-center justify-between">
              <div>
                <label class="text-white font-medium">Auto-Generate Invoices</label>
                <p class="text-gray-400 text-sm">Automatically create invoices for payments</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="form.auto_generate_invoices" class="sr-only peer" />
                <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
              </label>
            </div>
            <div>
              <label class="block text-white font-medium mb-2">Invoice Time</label>
              <input type="time" v-model="form.invoice_time" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" />
            </div>
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <div>
                  <label class="text-white font-medium">Invoice Delivery - Email</label>
                  <p class="text-gray-400 text-sm">Send invoices via email</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="form.invoice_delivery_email" class="sr-only peer" />
                  <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
              </div>
              <div class="flex items-center justify-between">
                <div>
                  <label class="text-white font-medium">Invoice Delivery - Panel</label>
                  <p class="text-gray-400 text-sm">Show invoices in user panel</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="form.invoice_delivery_panel" class="sr-only peer" />
                  <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Tax Configuration</h3>
          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <label class="text-white font-medium">Enable Tax</label>
                <p class="text-gray-400 text-sm">Apply tax to invoices</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="form.enable_tax" class="sr-only peer" />
                <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
              </label>
            </div>
            <div v-if="form.enable_tax" class="space-y-4">
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-white font-medium mb-2">Tax Name</label>
                  <input type="text" v-model="form.tax_name" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" placeholder="VAT" />
                </div>
                <div>
                  <label class="block text-white font-medium mb-2">Tax Rate (%)</label>
                  <input type="number" v-model="form.tax_rate" min="0" max="100" step="0.01" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" />
                </div>
              </div>
              <div>
                <label class="block text-white font-medium mb-2">Tax Number</label>
                <input type="text" v-model="form.tax_number" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" placeholder="GB123456789" />
              </div>
              <div>
                <label class="block text-white font-medium mb-2">Tax Type</label>
                <div class="space-y-2">
                  <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="radio" v-model="form.tax_type" value="Inclusive" class="w-4 h-4 text-indigo-500 bg-gray-700 border-gray-600 focus:ring-indigo-500" />
                    <span class="text-gray-300">Inclusive (Tax included in price)</span>
                  </label>
                  <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="radio" v-model="form.tax_type" value="Exclusive" class="w-4 h-4 text-indigo-500 bg-gray-700 border-gray-600 focus:ring-indigo-500" />
                    <span class="text-gray-300">Exclusive (Tax added on top)</span>
                  </label>
                </div>
              </div>
              <div class="flex items-center justify-between">
                <div>
                  <label class="text-white font-medium">Display Tax on Invoice</label>
                  <p class="text-gray-400 text-sm">Show tax breakdown on invoices</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="form.display_tax" class="sr-only peer" />
                  <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
              </div>
              <div class="space-y-3">
                <label class="block text-white font-medium">Apply Tax To</label>
                <div class="space-y-2">
                  <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" v-model="form.apply_to_subscriptions" class="w-4 h-4 text-indigo-500 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                    <span class="text-gray-300">Subscriptions</span>
                  </label>
                  <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" v-model="form.apply_to_one_time" class="w-4 h-4 text-indigo-500 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                    <span class="text-gray-300">One-time Payments</span>
                  </label>
                  <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" v-model="form.apply_to_vod" class="w-4 h-4 text-indigo-500 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                    <span class="text-gray-300">VOD Purchases</span>
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Payment Reminders</h3>
          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <label class="text-white font-medium">Enable Payment Reminders</label>
                <p class="text-gray-400 text-sm">Send reminders for upcoming/expired payments</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="form.enable_reminders" class="sr-only peer" />
                <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
              </label>
            </div>
            <div v-if="form.enable_reminders" class="space-y-4">
              <div>
                <label class="block text-white font-medium mb-2">Reminder Schedule</label>
                <div class="space-y-2">
                  <label v-for="day in ['14 days before', '7 days before', '3 days before', '1 day before', 'Day of expiry', '3 days after expiry']" :key="day" class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" v-model="form.reminder_days" :value="day" class="w-4 h-4 text-indigo-500 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                    <span class="text-gray-300">{{ day }}</span>
                  </label>
                </div>
              </div>
              <div>
                <label class="block text-white font-medium mb-2">Grace Period (days)</label>
                <input type="number" v-model="form.grace_period_days" min="0" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" />
                <p class="text-gray-400 text-sm mt-1">Days after expiry before account suspension</p>
              </div>
              <div>
                <label class="block text-white font-medium mb-2">Reminder Template</label>
                <textarea v-model="form.reminder_template" rows="4" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" placeholder="Your subscription expires on {{date}}. Please renew to avoid service interruption."></textarea>
                <p class="text-gray-400 text-sm mt-1">Available variables: {{name}}, {{email}}, {{date}}, {{amount}}, {{plan}}</p>
              </div>
            </div>
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

const props = defineProps({ settings: Object })

const form = useForm({
  invoice_prefix: props.settings.invoice_prefix ?? 'INV-',
  invoice_format: props.settings.invoice_format ?? 'Year-Month-Sequence',
  invoice_due_days: props.settings.invoice_due_days ?? 14,
  auto_generate_invoices: props.settings.auto_generate_invoices ?? true,
  invoice_time: props.settings.invoice_time ?? '00:00',
  invoice_delivery_email: props.settings.invoice_delivery_email ?? true,
  invoice_delivery_panel: props.settings.invoice_delivery_panel ?? true,
  enable_tax: props.settings.enable_tax ?? false,
  tax_name: props.settings.tax_name ?? 'VAT',
  tax_rate: props.settings.tax_rate ?? 20,
  tax_number: props.settings.tax_number ?? '',
  tax_type: props.settings.tax_type ?? 'Exclusive',
  display_tax: props.settings.display_tax ?? true,
  apply_to_subscriptions: props.settings.apply_to_subscriptions ?? true,
  apply_to_one_time: props.settings.apply_to_one_time ?? true,
  apply_to_vod: props.settings.apply_to_vod ?? true,
  enable_reminders: props.settings.enable_reminders ?? true,
  reminder_days: props.settings.reminder_days ?? ['7 days before', 'Day of expiry'],
  grace_period_days: props.settings.grace_period_days ?? 3,
  reminder_template: props.settings.reminder_template ?? 'Your subscription expires on {{date}}. Please renew to avoid service interruption.',
})
</script>
