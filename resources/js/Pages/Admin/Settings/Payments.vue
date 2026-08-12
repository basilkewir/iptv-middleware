<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto space-y-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">Payment Settings</h1>
        <p class="text-gray-400 mt-1">Configure payment gateways and processing options</p>
      </div>
      <form @submit.prevent="form.put(route('admin.settings.payments.update'))" class="space-y-6">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Available Gateways</h3>
          <div class="overflow-x-auto">
            <table class="w-full text-left">
              <thead>
                <tr class="border-b border-gray-700">
                  <th class="py-3 px-4 text-white font-semibold">Gateway</th>
                  <th class="py-3 px-4 text-white font-semibold">Status</th>
                  <th class="py-3 px-4 text-white font-semibold">Environment</th>
                  <th class="py-3 px-4 text-white font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="gateway in gateways" :key="gateway.name" class="border-b border-gray-700 hover:bg-gray-700/50">
                  <td class="py-3 px-4 text-white">{{ gateway.name }}</td>
                  <td class="py-3 px-4">
                    <span :class="gateway.enabled ? 'bg-green-600' : 'bg-gray-600'" class="px-2 py-1 text-xs text-white rounded-full">
                      {{ gateway.enabled ? 'Enabled' : 'Disabled' }}
                    </span>
                  </td>
                  <td class="py-3 px-4">
                    <span :class="gateway.environment === 'Production' ? 'bg-blue-600' : 'bg-yellow-600'" class="px-2 py-1 text-xs text-white rounded-full">
                      {{ gateway.environment }}
                    </span>
                  </td>
                  <td class="py-3 px-4">
                    <div class="flex space-x-2">
                      <button type="button" @click="selectGateway(gateway)" class="px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white text-sm rounded-lg transition">
                        Configure
                      </button>
                      <button type="button" @click="toggleGateway(gateway)" class="px-3 py-1 bg-gray-600 hover:bg-gray-500 text-white text-sm rounded-lg transition">
                        {{ gateway.enabled ? 'Disable' : 'Enable' }}
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">{{ selectedGateway }} Configuration</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-white font-medium mb-2">Gateway Name</label>
              <input type="text" v-model="form.gateway_name" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-white font-medium mb-2">Publishable Key</label>
                <input type="text" v-model="form.publishable_key" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" placeholder="pk_test_..." />
              </div>
              <div>
                <label class="block text-white font-medium mb-2">Secret Key</label>
                <input type="password" v-model="form.secret_key" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" placeholder="sk_test_..." />
              </div>
            </div>
            <div>
              <label class="block text-white font-medium mb-2">Webhook Secret</label>
              <input type="password" v-model="form.webhook_secret" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" placeholder="whsec_..." />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-white font-medium mb-2">Environment</label>
                <div class="space-y-2">
                  <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="radio" v-model="form.environment" value="Production" class="w-4 h-4 text-indigo-500 bg-gray-700 border-gray-600 focus:ring-indigo-500" />
                    <span class="text-gray-300">Production</span>
                  </label>
                  <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="radio" v-model="form.environment" value="Sandbox" class="w-4 h-4 text-indigo-500 bg-gray-700 border-gray-600 focus:ring-indigo-500" />
                    <span class="text-gray-300">Sandbox</span>
                  </label>
                </div>
              </div>
              <div>
                <label class="block text-white font-medium mb-2">Currency</label>
                <select v-model="form.currency" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500">
                  <option value="USD">USD - US Dollar</option>
                  <option value="EUR">EUR - Euro</option>
                  <option value="GBP">GBP - British Pound</option>
                  <option value="CAD">CAD - Canadian Dollar</option>
                  <option value="AUD">AUD - Australian Dollar</option>
                </select>
              </div>
            </div>
            <div>
              <label class="block text-white font-medium mb-2">Payment Methods</label>
              <div class="grid grid-cols-2 gap-2">
                <label v-for="method in ['Credit Card', 'Debit Card', 'Bank Transfer', 'Apple Pay', 'Google Pay', 'SEPA']" :key="method" class="flex items-center space-x-3 cursor-pointer p-2 rounded-lg hover:bg-gray-700">
                  <input type="checkbox" v-model="form.payment_methods" :value="method" class="w-4 h-4 text-indigo-500 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                  <span class="text-gray-300">{{ method }}</span>
                </label>
              </div>
            </div>
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <div>
                  <label class="text-white font-medium">Enable Subscriptions</label>
                  <p class="text-gray-400 text-sm">Allow recurring subscription payments</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="form.enable_subscriptions" class="sr-only peer" />
                  <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
              </div>
              <div class="flex items-center justify-between">
                <div>
                  <label class="text-white font-medium">Enable Recurring</label>
                  <p class="text-gray-400 text-sm">Allow recurring billing cycles</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="form.enable_recurring" class="sr-only peer" />
                  <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
              </div>
            </div>
            <div>
              <label class="block text-white font-medium mb-2">Statement Descriptor</label>
              <input type="text" v-model="form.statement_descriptor" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" placeholder="Your Company Name" />
            </div>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Payment Processing</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-white font-medium mb-2">Payment Timeout (minutes)</label>
              <input type="number" v-model="form.payment_timeout" min="5" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" />
            </div>
            <div class="flex items-center justify-between">
              <div>
                <label class="text-white font-medium">Retry Failed Payments</label>
                <p class="text-gray-400 text-sm">Automatically retry failed payments</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="form.retry_failed" class="sr-only peer" />
                <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
              </label>
            </div>
            <div v-if="form.retry_failed" class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-white font-medium mb-2">Max Retries</label>
                <input type="number" v-model="form.max_retries" min="1" max="10" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-white font-medium mb-2">Retry Delay (hours)</label>
                <input type="number" v-model="form.retry_delay" min="1" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" />
              </div>
            </div>
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <div>
                  <label class="text-white font-medium">Payment Confirmation Email</label>
                  <p class="text-gray-400 text-sm">Send email on successful payment</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="form.payment_confirmation" class="sr-only peer" />
                  <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
              </div>
              <div class="flex items-center justify-between">
                <div>
                  <label class="text-white font-medium">Payment Failed Notification</label>
                  <p class="text-gray-400 text-sm">Notify admin on payment failure</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="form.payment_failed_notification" class="sr-only peer" />
                  <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
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
import { ref } from 'vue'

const props = defineProps({ settings: Object })

const gateways = ref([
  { name: 'Stripe', enabled: true, environment: 'Sandbox' },
  { name: 'PayPal', enabled: false, environment: 'Sandbox' },
  { name: 'Square', enabled: false, environment: 'Sandbox' },
  { name: 'Authorize.net', enabled: false, environment: 'Sandbox' },
])

const selectedGateway = ref('Stripe')

const form = useForm({
  gateway_configs: props.settings.gateway_configs ?? {
    Stripe: {
      gateway_name: 'Stripe',
      publishable_key: '',
      secret_key: '',
      webhook_secret: '',
      environment: 'Sandbox',
      currency: 'USD',
      payment_methods: ['Credit Card', 'Debit Card'],
      enable_subscriptions: true,
      enable_recurring: true,
      statement_descriptor: '',
    },
  },
  gateway_name: props.settings.gateway_name ?? 'Stripe',
  publishable_key: props.settings.publishable_key ?? '',
  secret_key: props.settings.secret_key ?? '',
  webhook_secret: props.settings.webhook_secret ?? '',
  environment: props.settings.environment ?? 'Sandbox',
  currency: props.settings.currency ?? 'USD',
  payment_methods: props.settings.payment_methods ?? ['Credit Card', 'Debit Card'],
  enable_subscriptions: props.settings.enable_subscriptions ?? true,
  enable_recurring: props.settings.enable_recurring ?? true,
  statement_descriptor: props.settings.statement_descriptor ?? '',
  payment_timeout: props.settings.payment_timeout ?? 30,
  retry_failed: props.settings.retry_failed ?? true,
  max_retries: props.settings.max_retries ?? 3,
  retry_delay: props.settings.retry_delay ?? 24,
  payment_confirmation: props.settings.payment_confirmation ?? true,
  payment_failed_notification: props.settings.payment_failed_notification ?? true,
})

function selectGateway(gateway) {
  selectedGateway.value = gateway.name
  const config = form.gateway_configs[gateway.name] ?? {}
  form.gateway_name = config.gateway_name ?? gateway.name
  form.publishable_key = config.publishable_key ?? ''
  form.secret_key = config.secret_key ?? ''
  form.webhook_secret = config.webhook_secret ?? ''
  form.environment = config.environment ?? 'Sandbox'
  form.currency = config.currency ?? 'USD'
  form.payment_methods = config.payment_methods ?? ['Credit Card', 'Debit Card']
  form.enable_subscriptions = config.enable_subscriptions ?? true
  form.enable_recurring = config.enable_recurring ?? true
  form.statement_descriptor = config.statement_descriptor ?? ''
}

function toggleGateway(gateway) {
  gateway.enabled = !gateway.enabled
}
</script>
