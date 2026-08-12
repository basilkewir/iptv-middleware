<template>
  <AdminLayout>
    <div class="p-6">
      <div class="flex items-center gap-3 mb-6">
        <Link :href="route('admin.clients.index')" class="text-gray-400 hover:text-white">
          <ArrowLeft class="w-5 h-5" />
        </Link>
        <div>
          <h1 class="text-2xl font-bold text-white">Create Client</h1>
          <p class="text-gray-400 text-sm mt-1">Set up a new IPTV client account</p>
        </div>
      </div>

      <!-- Step Progress -->
      <div class="card mb-6">
        <div class="flex items-center justify-between px-2">
          <div v-for="(step, index) in steps" :key="index"
            class="flex items-center flex-1"
            :class="{ 'opacity-50': currentStep < index }">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold"
                :class="currentStep > index ? 'bg-green-500 text-white' : currentStep === index ? 'bg-purple-600 text-white' : 'bg-gray-700 text-gray-400'">
                <Check v-if="currentStep > index" class="w-4 h-4" />
                <span v-else>{{ index + 1 }}</span>
              </div>
              <span class="text-sm font-medium hidden sm:block"
                :class="currentStep >= index ? 'text-white' : 'text-gray-500'">{{ step }}</span>
            </div>
            <div v-if="index < steps.length - 1" class="flex-1 h-px mx-4 bg-gray-700"></div>
          </div>
        </div>
      </div>

      <form @submit.prevent="submitForm">
        <!-- Step 1: Basic Info -->
        <div v-show="currentStep === 0" class="card space-y-4">
          <h2 class="text-lg font-semibold text-white mb-4">Basic Information</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Username</label>
              <div class="flex items-center gap-2">
                <input v-model="form.username" type="text" class="input-field flex-1" placeholder="Auto-generated if empty" />
                <label class="flex items-center gap-1 text-xs text-gray-400">
                  <input type="checkbox" v-model="form.auto_generate_username" class="w-3 h-3" />
                  Auto
                </label>
              </div>
              <p v-if="form.errors.username" class="text-red-400 text-xs mt-1">{{ form.errors.username }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Email</label>
              <input v-model="form.email" type="email" class="input-field" placeholder="client@example.com (optional)" />
              <p v-if="form.errors.email" class="text-red-400 text-xs mt-1">{{ form.errors.email }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Password</label>
              <div class="relative">
                <input v-model="form.password" :type="showPassword ? 'text' : 'password'" class="input-field pr-10" placeholder="Min 8 characters (auto if empty)" />
                <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white">
                  <Eye v-if="!showPassword" class="w-4 h-4" />
                  <EyeOff v-else class="w-4 h-4" />
                </button>
              </div>
              <div class="mt-1 flex items-center gap-2">
                <div v-for="i in 4" :key="i" class="h-1 flex-1 rounded"
                  :class="passwordStrength >= i ? strengthColor : 'bg-gray-700'"></div>
                <label class="flex items-center gap-1 text-xs text-gray-400">
                  <input type="checkbox" v-model="form.auto_generate_password" class="w-3 h-3" />
                  Auto
                </label>
              </div>
              <p v-if="form.errors.password" class="text-red-400 text-xs mt-1">{{ form.errors.password }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Confirm Password</label>
              <input v-model="form.password_confirmation" type="password" class="input-field" placeholder="Confirm password" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">First Name</label>
              <input v-model="form.first_name" type="text" class="input-field" placeholder="First name" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Last Name</label>
              <input v-model="form.last_name" type="text" class="input-field" placeholder="Last name" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Phone</label>
              <input v-model="form.phone" type="text" class="input-field" placeholder="Phone number" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Reseller</label>
              <select v-model="form.reseller_id" class="input-field">
                <option value="">None (Direct)</option>
                <option v-for="r in resellers" :key="r.id" :value="r.id">{{ r.username }} - {{ r.first_name }} {{ r.last_name }}</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Step 2: Subscription -->
        <div v-show="currentStep === 1" class="card space-y-4">
          <h2 class="text-lg font-semibold text-white mb-4">Subscription Settings</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Package <span class="text-red-400">*</span></label>
              <select v-model="form.package_id" class="input-field" required>
                <option value="">Select Package</option>
                <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">
                  {{ pkg.name }} - {{ pkg.price ? '$' + pkg.price : 'Free' }}
                </option>
              </select>
              <p v-if="form.errors.package_id" class="text-red-400 text-xs mt-1">{{ form.errors.package_id }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Duration <span class="text-red-400">*</span></label>
              <select v-model="form.duration" class="input-field" @change="updateExpiryFromDuration">
                <option value="30">1 Month (30 days)</option>
                <option value="90">3 Months (90 days)</option>
                <option value="180">6 Months (180 days)</option>
                <option value="365">12 Months (365 days)</option>
                <option value="custom">Custom Date</option>
              </select>
              <p v-if="form.errors.duration" class="text-red-400 text-xs mt-1">{{ form.errors.duration }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Max Connections</label>
              <input v-model="form.max_connections" type="number" class="input-field" min="1" max="100" placeholder="Default: 2" />
            </div>
          </div>

          <!-- Never Expire -->
          <div class="flex items-center gap-3 mt-4">
            <input v-model="form.never_expire" type="checkbox" id="never_expire" class="w-4 h-4 rounded bg-gray-600 text-purple-600" />
            <label for="never_expire" class="text-sm font-medium text-gray-300">Never expire (lifetime subscription)</label>
          </div>

          <!-- Custom Expiry Date (shown when duration is 'custom' and never_expire is false) -->
          <div v-show="!form.never_expire && form.duration === 'custom'" class="mt-4">
            <label class="block text-sm font-medium text-gray-300 mb-2">Custom Expiry Date <span class="text-red-400">*</span></label>
            <input v-model="form.expiry_date" type="date" class="input-field" :min="minDate" />
            <p v-if="form.errors.expiry_date" class="text-red-400 text-xs mt-1">{{ form.errors.expiry_date }}</p>
          </div>
        </div>

        <!-- Step 3: Device Access -->
        <div v-show="currentStep === 2" class="card space-y-4">
          <h2 class="text-lg font-semibold text-white mb-4">Device Access Control</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">MAC Address</label>
              <input v-model="form.mac_address" type="text" class="input-field" placeholder="00:1A:2B:3C:4D:5E" maxlength="17" />
              <p class="text-gray-500 text-xs mt-1">Format: XX:XX:XX:XX:XX:XX</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">IP Restriction</label>
              <input v-model="form.ip_restriction" type="text" class="input-field" placeholder="192.168.1.0/24" />
              <p class="text-gray-500 text-xs mt-1">CIDR notation or single IP</p>
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

        <!-- Step 4: Bouquets -->
        <div v-show="currentStep === 3" class="card space-y-4">
          <h2 class="text-lg font-semibold text-white mb-4">Select Bouquets</h2>
          <p class="text-gray-400 text-sm mb-4">Choose which channel bouquets this client will have access to.</p>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            <label v-for="bouquet in bouquets" :key="bouquet.id"
              class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
              :class="form.bouquet_ids.includes(bouquet.id) ? 'border-purple-500 bg-purple-500/10' : 'border-gray-700 hover:border-gray-600'">
              <input type="checkbox" :value="bouquet.id" v-model="form.bouquet_ids" class="w-4 h-4 rounded bg-gray-600 text-purple-600" />
              <div>
                <span class="text-white text-sm font-medium">{{ bouquet.name }}</span>
                <p v-if="bouquet.channels_count !== undefined" class="text-gray-500 text-xs">{{ bouquet.channels_count }} channels</p>
              </div>
            </label>
          </div>
          <div v-if="bouquets.length === 0" class="text-center py-8 text-gray-500">
            No bouquets available. <Link :href="route('admin.bouquets.index')" class="text-purple-400 hover:underline">Create one</Link>
          </div>
        </div>

        <!-- Navigation Buttons -->
        <div class="flex justify-between mt-6">
          <button type="button" v-if="currentStep > 0" @click="prevStep" class="btn-secondary">
            <ArrowLeft class="w-4 h-4 inline mr-1" /> Previous
          </button>
          <div v-else></div>
          <div class="flex gap-3">
            <button type="button" v-if="currentStep < steps.length - 1" @click="nextStep" class="btn-primary">
              Next <ArrowRight class="w-4 h-4 inline ml-1" />
            </button>
            <button v-else type="submit" class="btn-primary" :disabled="form.processing">
              <Loader v-if="form.processing" class="w-4 h-4 animate-spin inline mr-1" />
              Create Client
            </button>
          </div>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeft, ArrowRight, Check, Eye, EyeOff, Loader } from 'lucide-vue-next'

const props = defineProps({
  packages: { type: Array, required: true },
  bouquets: { type: Array, default: () => [] },
  resellers: { type: Array, default: () => [] },
})

const steps = ['Basic Info', 'Subscription', 'Device Access', 'Bouquets']
const currentStep = ref(0)
const showPassword = ref(false)

const form = useForm({
  username: '',
  email: '',
  password: '',
  password_confirmation: '',
  first_name: '',
  last_name: '',
  phone: '',
  reseller_id: '',
  package_id: '',
  expiry_date: '',
  never_expire: false,
  duration: '30',
  max_connections: '',
  mac_address: '',
  ip_restriction: '',
  country: '',
  bouquet_ids: [],
  auto_generate_username: false,
  auto_generate_password: false,
})

// Auto-calculate expiry date on initial load
onMounted(() => {
  updateExpiryFromDuration()
})

const updateExpiryFromDuration = () => {
  if (form.duration === 'custom') {
    form.expiry_date = ''
  } else {
    const d = new Date()
    d.setDate(d.getDate() + parseInt(form.duration))
    form.expiry_date = d.toISOString().split('T')[0]
  }
}

const minDate = computed(() => {
  const d = new Date()
  d.setDate(d.getDate() + 1)
  return d.toISOString().split('T')[0]
})

const passwordStrength = computed(() => {
  const p = form.password
  if (!p) return 0
  let strength = 0
  if (p.length >= 8) strength++
  if (/[a-z]/.test(p) && /[A-Z]/.test(p)) strength++
  if (/\d/.test(p)) strength++
  if (/[^a-zA-Z0-9]/.test(p)) strength++
  return strength
})

const strengthColor = computed(() => {
  const s = passwordStrength.value
  if (s <= 1) return 'bg-red-500'
  if (s === 2) return 'bg-yellow-500'
  if (s === 3) return 'bg-blue-500'
  return 'bg-green-500'
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

const nextStep = () => {
  if (currentStep.value === 0) {
    // Username and password are optional (auto-generated if empty)
    if (form.password && form.password !== form.password_confirmation) {
      alert('Passwords do not match')
      return
    }
  }
  if (currentStep.value === 1) {
    if (!form.package_id) {
      alert('Please select a package')
      return
    }
    if (!form.never_expire && !form.expiry_date && !form.duration) {
      alert('Please select a duration or expiry date, or enable "Never expire"')
      return
    }
  }
  currentStep.value++
}

const prevStep = () => {
  currentStep.value--
}

const submitForm = () => {
  form.post(route('admin.clients.store'), {
    onSuccess: () => {
      form.reset()
    }
  })
}
</script>
