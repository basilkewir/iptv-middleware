<template>
  <AdminLayout>
    <div class="p-6">
      <div class="flex items-center gap-3 mb-6">
        <Link :href="route('admin.clients.show', client.id)" class="text-gray-400 hover:text-white">
          <ArrowLeft class="w-5 h-5" />
        </Link>
        <div>
          <h1 class="text-2xl font-bold text-white">Edit Client: {{ client.username }}</h1>
          <p class="text-gray-400 text-sm mt-1">Update client account details</p>
        </div>
      </div>

      <form @submit.prevent="submitForm">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Basic Info -->
          <div class="card space-y-4">
            <h2 class="text-lg font-semibold text-white mb-4">Basic Information</h2>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Username</label>
              <input v-model="form.username" type="text" class="input-field" required />
              <p v-if="form.errors.username" class="text-red-400 text-xs mt-1">{{ form.errors.username }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Email</label>
              <input v-model="form.email" type="email" class="input-field" required />
              <p v-if="form.errors.email" class="text-red-400 text-xs mt-1">{{ form.errors.email }}</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">First Name</label>
                <input v-model="form.first_name" type="text" class="input-field" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Last Name</label>
                <input v-model="form.last_name" type="text" class="input-field" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Phone</label>
              <input v-model="form.phone" type="text" class="input-field" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Reseller</label>
              <select v-model="form.reseller_id" class="input-field">
                <option value="">None (Direct)</option>
                <option v-for="r in resellers" :key="r.id" :value="r.id">{{ r.username }}</option>
              </select>
            </div>
          </div>

          <!-- Subscription & Device -->
          <div class="card space-y-4">
            <h2 class="text-lg font-semibold text-white mb-4">Subscription & Device</h2>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Package</label>
              <select v-model="form.package_id" class="input-field">
                <option value="">No Change</option>
                <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">{{ pkg.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Expiry Date</label>
              <input v-model="form.expiry_date" type="date" class="input-field" :disabled="form.never_expire" />
            </div>
            <div class="flex items-center gap-3">
              <input type="checkbox" id="never_expire" v-model="form.never_expire" class="w-4 h-4 rounded bg-gray-600 text-purple-600" />
              <label for="never_expire" class="text-sm font-medium text-gray-300 cursor-pointer">Never Expire</label>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Max Connections</label>
              <input v-model="form.max_connections" type="number" class="input-field" min="1" max="100" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">MAC Address</label>
              <input v-model="form.mac_address" type="text" class="input-field" placeholder="00:1A:2B:3C:4D:5E" maxlength="17" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">IP Restriction</label>
              <input v-model="form.ip_restriction" type="text" class="input-field" placeholder="192.168.1.0/24" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Country</label>
              <select v-model="form.country" class="input-field">
                <option value="">All Countries</option>
                <option v-for="c in countries" :key="c.code" :value="c.code">{{ c.name }}</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Bouquets -->
        <div class="card mt-6">
          <h2 class="text-lg font-semibold text-white mb-4">Assigned Bouquets</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            <label v-for="bouquet in bouquets" :key="bouquet.id"
              class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
              :class="form.bouquet_ids.includes(bouquet.id) ? 'border-purple-500 bg-purple-500/10' : 'border-gray-700 hover:border-gray-600'">
              <input type="checkbox" :value="bouquet.id" v-model="form.bouquet_ids" class="w-4 h-4 rounded bg-gray-600 text-purple-600" />
              <span class="text-white text-sm">{{ bouquet.name }}</span>
            </label>
          </div>
        </div>

        <!-- Submit -->
        <div class="flex justify-end gap-3 mt-6">
          <Link :href="route('admin.clients.show', client.id)" class="btn-secondary">Cancel</Link>
          <button type="submit" class="btn-primary" :disabled="form.processing">
            <Loader v-if="form.processing" class="w-4 h-4 animate-spin inline mr-1" />
            Save Changes
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeft, Loader } from 'lucide-vue-next'

const props = defineProps({
  client: { type: Object, required: true },
  packages: { type: Array, default: () => [] },
  bouquets: { type: Array, default: () => [] },
  resellers: { type: Array, default: () => [] },
})

const activeSubscription = props.client.subscriptions?.find(s => s.status === 'active' && (s.end_date === null || new Date(s.end_date) >= new Date()))

const form = useForm({
  username: props.client.username || '',
  email: props.client.email || '',
  first_name: props.client.first_name || '',
  last_name: props.client.last_name || '',
  phone: props.client.phone || '',
  reseller_id: props.client.reseller_id || '',
  package_id: activeSubscription?.subscription_package_id || '',
  expiry_date: activeSubscription?.end_date ? activeSubscription.end_date.split('T')[0] : '',
  never_expire: activeSubscription ? activeSubscription.end_date === null : false,
  max_connections: props.client.max_connections || '',
  mac_address: props.client.mac_address || '',
  ip_restriction: props.client.ip_restriction || '',
  country: props.client.country || '',
  bouquet_ids: props.client.bouquets?.map(b => b.id) || [],
})

const countries = [
  { code: 'US', name: 'United States' }, { code: 'GB', name: 'United Kingdom' },
  { code: 'CA', name: 'Canada' }, { code: 'AU', name: 'Australia' },
  { code: 'DE', name: 'Germany' }, { code: 'FR', name: 'France' },
  { code: 'IT', name: 'Italy' }, { code: 'ES', name: 'Spain' },
  { code: 'NL', name: 'Netherlands' }, { code: 'BR', name: 'Brazil' },
  { code: 'IN', name: 'India' }, { code: 'JP', name: 'Japan' },
  { code: 'NG', name: 'Nigeria' }, { code: 'ZA', name: 'South Africa' },
  { code: 'KE', name: 'Kenya' }, { code: 'EG', name: 'Egypt' },
  { code: 'AE', name: 'UAE' }, { code: 'SA', name: 'Saudi Arabia' },
  { code: 'SG', name: 'Singapore' }, { code: 'HK', name: 'Hong Kong' },
]

const submitForm = () => {
  form.put(route('admin.clients.update', props.client.id), {
    onSuccess: () => {
      form.reset('package_id', 'expiry_date')
    }
  })
}
</script>