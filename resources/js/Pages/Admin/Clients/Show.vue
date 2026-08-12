<template>
  <AdminLayout>
    <div class="p-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <Link :href="route('admin.clients.index')" class="text-gray-400 hover:text-white">
            <ArrowLeft class="w-5 h-5" />
          </Link>
          <div>
            <h1 class="text-2xl font-bold text-white">{{ client.username }}</h1>
            <p class="text-gray-400 text-sm">Client Profile</p>
          </div>
        </div>
        <div class="flex gap-2">
          <Link :href="route('admin.clients.edit', client.id)" class="btn-secondary">
            <Pencil class="w-4 h-4 inline mr-1" /> Edit
          </Link>
          <button @click="toggleStatus" class="btn-secondary"
            :class="client.is_active ? 'text-yellow-400' : 'text-green-400'">
            <Pause v-if="client.is_active" class="w-4 h-4 inline mr-1" />
            <Play v-else class="w-4 h-4 inline mr-1" />
            {{ client.is_active ? 'Suspend' : 'Activate' }}
          </button>
          <button @click="sendCredentials" class="btn-secondary">
            <Mail class="w-4 h-4 inline mr-1" /> Send Credentials
          </button>
          <Link :href="route('admin.clients.report', client.id)" class="btn-secondary">
            <Download class="w-4 h-4 inline mr-1" /> Report
          </Link>
        </div>
      </div>

      <!-- Status Banner -->
      <div class="card mb-6"
        :class="client.is_active && subscriptionActive ? 'border-green-500/30 bg-green-500/5' : 'border-red-500/30 bg-red-500/5'">
        <div class="flex items-center gap-3">
          <div class="w-3 h-3 rounded-full"
            :class="client.is_active && subscriptionActive ? 'bg-green-500' : 'bg-red-500'"></div>
          <span class="text-white font-medium">
            {{ client.is_active && subscriptionActive ? 'Active' : client.is_active ? 'Expired' : 'Suspended' }}
          </span>
          <span v-if="client.subscription_end_date" class="text-gray-400 text-sm">
            | Expires: {{ formatDate(client.subscription_end_date) }}
          </span>
        </div>
      </div>

      <!-- Content Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Account Details -->
        <div class="card lg:col-span-1">
          <h2 class="text-lg font-semibold text-white mb-4">Account Details</h2>
          <div class="space-y-3">
            <div class="flex justify-between">
              <span class="text-gray-400">Username</span>
              <span class="text-white font-medium">{{ client.username }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Email</span>
              <span class="text-white">{{ client.email }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Name</span>
              <span class="text-white">{{ client.first_name }} {{ client.last_name }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Phone</span>
              <span class="text-white">{{ client.phone || 'N/A' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Reseller</span>
              <span class="text-white">{{ client.reseller?.username || 'Direct' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Created</span>
              <span class="text-white">{{ formatDate(client.created_at) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">MAC Address</span>
              <span class="text-white font-mono">{{ client.mac_address || 'N/A' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Country</span>
              <span class="text-white">{{ client.country || 'All' }}</span>
            </div>
            <div class="pt-3 border-t border-gray-700">
              <button @click="regenerateM3U" class="btn-primary w-full text-sm">
                <RefreshCw class="w-4 h-4 inline mr-1" /> Regenerate M3U URL
              </button>
              <div v-if="m3uUrl" class="mt-3">
                <div class="flex items-center gap-2 bg-gray-800 rounded p-2">
                  <input :value="m3uUrl" type="text" readonly class="bg-transparent text-green-400 text-xs flex-1 outline-none" />
                  <button @click="copyToClipboard(m3uUrl)" class="text-gray-400 hover:text-white p-1">
                    <Copy class="w-4 h-4" />
                  </button>
                </div>
                <a :href="m3uUrl" download class="text-purple-400 hover:text-purple-300 text-xs mt-1 inline-block">
                  <Download class="w-3 h-3 inline mr-1" /> Download M3U File
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Subscription Info -->
          <div class="card">
            <h2 class="text-lg font-semibold text-white mb-4">Subscription Info</h2>
            <div v-if="client.subscriptions && client.subscriptions.length > 0">
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead>
                    <tr class="border-b border-gray-700">
                      <th class="p-2 text-left text-gray-400">Package</th>
                      <th class="p-2 text-left text-gray-400">Status</th>
                      <th class="p-2 text-left text-gray-400">Start</th>
                      <th class="p-2 text-left text-gray-400">End</th>
                      <th class="p-2 text-left text-gray-400">Auto Renew</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="sub in client.subscriptions" :key="sub.id" class="border-b border-gray-700/50">
                      <td class="p-2 text-white">{{ sub.subscription_package?.name }}</td>
                      <td class="p-2">
                        <span class="px-2 py-0.5 rounded-full text-xs"
                          :class="sub.status === 'active' ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'">
                          {{ sub.status }}
                        </span>
                      </td>
                      <td class="p-2 text-gray-300">{{ formatDate(sub.start_date) }}</td>
                      <td class="p-2 text-gray-300">{{ formatDate(sub.end_date) }}</td>
                      <td class="p-2 text-gray-300">{{ sub.auto_renew ? 'Yes' : 'No' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <p v-else class="text-gray-500 text-center py-4">No subscriptions</p>
            <div class="mt-4 flex gap-2">
              <button @click="showChangePackage = true" class="btn-secondary text-sm">Change Package</button>
              <button @click="showExtendExpiry = true" class="btn-secondary text-sm">Extend Expiry</button>
              <button @click="showResetPassword = true" class="btn-secondary text-sm">Reset Password</button>
            </div>
          </div>

          <!-- Bouquets -->
          <div class="card">
            <h2 class="text-lg font-semibold text-white mb-4">Assigned Bouquets</h2>
            <div v-if="client.bouquets && client.bouquets.length > 0" class="flex flex-wrap gap-2">
              <span v-for="bouquet in client.bouquets" :key="bouquet.id"
                class="px-3 py-1 bg-purple-500/10 border border-purple-500/30 text-purple-300 rounded-full text-sm">
                {{ bouquet.name }}
              </span>
            </div>
            <p v-else class="text-gray-500 text-center py-4">No bouquets assigned</p>
          </div>

          <!-- M3U & Stream URL Info -->
          <div class="card">
            <h2 class="text-lg font-semibold text-white mb-4">Stream Access</h2>
            <p class="text-gray-400 text-sm mb-4">Client can use these URLs to access streams in VLC or any IPTV player:</p>
            <div v-if="client.m3u_token" class="space-y-3">
              <div>
                <label class="block text-sm text-gray-400 mb-1">Xtream Codes API</label>
                <div class="flex items-center gap-2 bg-gray-800 rounded p-2">
                  <input :value="xtreamUrl" type="text" readonly class="bg-transparent text-green-400 text-xs flex-1 outline-none" />
                  <button @click="copyToClipboard(xtreamUrl)" class="text-gray-400 hover:text-white p-1">
                    <Copy class="w-4 h-4" />
                  </button>
                </div>
              </div>
              <div>
                <label class="block text-sm text-gray-400 mb-1">Single Channel URL Format</label>
                <div class="flex items-center gap-2 bg-gray-800 rounded p-2">
                  <input :value="singleStreamUrl" type="text" readonly class="bg-transparent text-green-400 text-xs flex-1 outline-none" />
                  <button @click="copyToClipboard(singleStreamUrl)" class="text-gray-400 hover:text-white p-1">
                    <Copy class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
            <div v-else class="p-3 bg-yellow-500/10 border border-yellow-500/30 rounded-lg mb-3">
              <p class="text-yellow-300 text-xs">
                <Info class="w-3 h-3 inline mr-1" />
                No M3U token generated yet. Use the "Regenerate M3U URL" button above to generate one.
              </p>
            </div>
            <div class="mt-4 p-3 bg-blue-500/10 border border-blue-500/30 rounded-lg">
              <p class="text-blue-300 text-xs">
                <Info class="w-3 h-3 inline mr-1" />
                For VLC: Media → Open Network Stream → paste the M3U URL above
              </p>
            </div>
          </div>

          <!-- Connection Logs -->
          <div class="card">
            <h2 class="text-lg font-semibold text-white mb-4">Recent Connection Logs</h2>
            <div v-if="connectionLogs.length > 0">
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead>
                    <tr class="border-b border-gray-700">
                      <th class="p-2 text-left text-gray-400">IP</th>
                      <th class="p-2 text-left text-gray-400">Stream</th>
                      <th class="p-2 text-left text-gray-400">Time</th>
                      <th class="p-2 text-left text-gray-400">Duration</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="log in connectionLogs" :key="log.id" class="border-b border-gray-700/50">
                      <td class="p-2 text-gray-300 font-mono text-xs">{{ log.ip_address }}</td>
                      <td class="p-2 text-gray-300">{{ log.stream_name || 'N/A' }}</td>
                      <td class="p-2 text-gray-300">{{ formatDate(log.created_at) }}</td>
                      <td class="p-2 text-gray-300">{{ log.duration ? log.duration + 's' : 'N/A' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <p v-else class="text-gray-500 text-center py-4">No connection logs</p>
          </div>

          <!-- Activity Logs -->
          <div class="card">
            <h2 class="text-lg font-semibold text-white mb-4">Activity Log</h2>
            <div v-if="activityLogs.length > 0" class="space-y-2">
              <div v-for="log in activityLogs" :key="log.id" class="flex items-start gap-3 p-2 rounded hover:bg-gray-700/30">
                <div class="w-2 h-2 rounded-full bg-purple-500 mt-2"></div>
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

    <!-- Modals -->
    <teleport to="body">
      <!-- Change Package Modal -->
      <div v-if="showChangePackage" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" @click.self="showChangePackage = false">
        <div class="bg-gray-900 border border-gray-700 rounded-xl p-6 w-full max-w-md mx-4">
          <h3 class="text-lg font-semibold text-white mb-4">Change Package</h3>
          <select v-model="packageForm.package_id" class="input-field mb-4">
            <option value="">Select Package</option>
            <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">{{ pkg.name }}</option>
          </select>
          <input v-model="packageForm.expiry_date" type="date" class="input-field mb-4" placeholder="New expiry date" />
          <div class="flex justify-end gap-2">
            <button @click="showChangePackage = false" class="btn-secondary">Cancel</button>
            <button @click="changePackage" class="btn-primary">Save</button>
          </div>
        </div>
      </div>

      <!-- Extend Expiry Modal -->
      <div v-if="showExtendExpiry" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" @click.self="showExtendExpiry = false">
        <div class="bg-gray-900 border border-gray-700 rounded-xl p-6 w-full max-w-md mx-4">
          <h3 class="text-lg font-semibold text-white mb-4">Extend Expiry Date</h3>

          <!-- Current Expiry -->
          <div class="bg-gray-800 rounded-lg p-3 mb-4">
            <span class="text-gray-400 text-sm block">Current Expiry Date</span>
            <span class="text-white font-medium text-lg">{{ currentExpiryDate }}</span>
          </div>

          <!-- Preset Duration Buttons -->
          <p class="text-gray-400 text-sm mb-2">Quick Select Duration</p>
          <div class="grid grid-cols-2 gap-2 mb-4">
            <button @click="setExtendDuration(30)" class="btn-secondary text-sm py-2"
              :class="selectedExtendDays === 30 ? 'ring-2 ring-purple-500' : ''">1 Month</button>
            <button @click="setExtendDuration(90)" class="btn-secondary text-sm py-2"
              :class="selectedExtendDays === 90 ? 'ring-2 ring-purple-500' : ''">3 Months</button>
            <button @click="setExtendDuration(180)" class="btn-secondary text-sm py-2"
              :class="selectedExtendDays === 180 ? 'ring-2 ring-purple-500' : ''">6 Months</button>
            <button @click="setExtendDuration(270)" class="btn-secondary text-sm py-2"
              :class="selectedExtendDays === 270 ? 'ring-2 ring-purple-500' : ''">9 Months</button>
            <button @click="setExtendDuration(365)" class="btn-secondary text-sm py-2 col-span-2"
              :class="selectedExtendDays === 365 ? 'ring-2 ring-purple-500' : ''">1 Year</button>
          </div>

          <!-- Never Expire Option -->
          <label class="flex items-center gap-3 cursor-pointer mb-4 p-3 bg-gray-800 rounded-lg">
            <input v-model="extendForm.never_expire" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-purple-600 focus:ring-purple-500" />
            <span class="text-gray-300">Never Expire (Lifetime)</span>
          </label>

          <!-- Custom Date Picker (disabled when never_expire is checked) -->
          <p class="text-gray-400 text-sm mb-2">Or Choose Custom Date</p>
          <input v-model="extendForm.expiry_date" type="date" class="input-field mb-4" :min="minDate" :disabled="extendForm.never_expire" />

          <!-- New Expiry Preview -->
          <div v-if="extendForm.never_expire" class="bg-purple-500/10 border border-purple-500/30 rounded-lg p-3 mb-4">
            <span class="text-purple-300 text-sm block">New Expiry Date</span>
            <span class="text-purple-200 font-medium text-lg">Never Expires (Lifetime)</span>
          </div>
          <div v-else-if="extendForm.expiry_date" class="bg-purple-500/10 border border-purple-500/30 rounded-lg p-3 mb-4">
            <span class="text-purple-300 text-sm block">New Expiry Date</span>
            <span class="text-purple-200 font-medium text-lg">{{ formatDate(extendForm.expiry_date) }}</span>
          </div>

          <div class="flex justify-end gap-2">
            <button @click="showExtendExpiry = false" class="btn-secondary">Cancel</button>
            <button @click="extendExpiry" class="btn-primary">Extend</button>
          </div>
        </div>
      </div>

      <!-- Reset Password Modal -->
      <div v-if="showResetPassword" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" @click.self="showResetPassword = false">
        <div class="bg-gray-900 border border-gray-700 rounded-xl p-6 w-full max-w-md mx-4">
          <h3 class="text-lg font-semibold text-white mb-4">Reset Password</h3>
          <input v-model="passwordForm.password" type="password" class="input-field mb-4" placeholder="New password" />
          <input v-model="passwordForm.password_confirmation" type="password" class="input-field mb-4" placeholder="Confirm password" />
          <div class="flex justify-end gap-2">
            <button @click="showResetPassword = false" class="btn-secondary">Cancel</button>
            <button @click="resetPassword" class="btn-primary">Reset</button>
          </div>
        </div>
      </div>
    </teleport>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
  ArrowLeft, Pencil, Pause, Play, Mail, Download,
  RefreshCw, Copy, Info
} from 'lucide-vue-next'

const props = defineProps({
  client: { type: Object, required: true },
  activityLogs: { type: Array, default: () => [] },
  watchHistory: { type: Array, default: () => [] },
  connectionLogs: { type: Array, default: () => [] },
  packages: { type: Array, default: () => [] },
  bouquets: { type: Array, default: () => [] },
  serverBaseUrl: { type: String, default: '' },
})

const m3uUrl = computed(() => {
  if (!props.client.m3u_token) return ''
  return `/playlist/${props.client.m3u_token}/m3u`
})
const showChangePackage = ref(false)
const showExtendExpiry = ref(false)
const showResetPassword = ref(false)

const subscriptionActive = computed(() => {
  return props.client.subscriptions?.some(s => s.status === 'active' && (!s.end_date || new Date(s.end_date) > new Date()))
})

const xtreamUrl = computed(() => {
  const token = props.client.m3u_token
  if (!token) return null
  const base = props.serverBaseUrl || window.location.origin
  return `${base}/get.php?username=${props.client.username}&password=${token}&type=m3u_plus`
})

const singleStreamUrl = computed(() => {
  const token = props.client.m3u_token
  if (!token) return null
  const base = props.serverBaseUrl || window.location.origin
  return `${base}/live/${props.client.username}/${token}/STREAM_ID`
})

const minDate = computed(() => {
  const d = new Date()
  d.setDate(d.getDate() + 1)
  return d.toISOString().split('T')[0]
})

const packageForm = useForm({
  package_id: '',
  expiry_date: '',
})

const extendForm = useForm({
  expiry_date: '',
  never_expire: false,
})

const selectedExtendDays = ref(null)

const currentExpiryDate = computed(() => {
  const activeSub = props.client.subscriptions?.find(s => s.status === 'active')
  if (!activeSub) return 'No active subscription'
  if (!activeSub.end_date) return 'Never Expires (Lifetime)'
  return formatDate(activeSub.end_date)
})

const setExtendDuration = (days) => {
  selectedExtendDays.value = days
  const d = new Date()
  d.setDate(d.getDate() + days)
  extendForm.expiry_date = d.toISOString().split('T')[0]
}

const passwordForm = useForm({
  password: '',
  password_confirmation: '',
})

const formatDate = (date) => {
  if (!date) return 'N/A'
  return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

const copyToClipboard = (text) => {
  navigator.clipboard.writeText(text)
}

const toggleStatus = () => {
  if (confirm(`Are you sure you want to ${props.client.is_active ? 'suspend' : 'activate'} this client?`)) {
    router.post(route('admin.clients.toggleStatus', props.client.id), {}, { preserveScroll: true })
  }
}

const sendCredentials = () => {
  if (confirm(`Send credentials to ${props.client.email}?`)) {
    router.post(route('admin.clients.sendCredentials', props.client.id), {}, { preserveScroll: true })
  }
}

const regenerateM3U = () => {
  router.post(route('admin.clients.generateM3u', props.client.id), {}, {
    preserveScroll: true,
  })
}

const changePackage = () => {
  packageForm.post(route('admin.clients.changePackage', props.client.id), {
    preserveScroll: true,
    onSuccess: () => {
      showChangePackage.value = false
    }
  })
}

const extendExpiry = () => {
  extendForm.post(route('admin.clients.extendExpiry', props.client.id), {
    preserveScroll: true,
    onSuccess: () => {
      showExtendExpiry.value = false
      extendForm.reset()
      selectedExtendDays.value = null
    }
  })
}

const resetPassword = () => {
  passwordForm.post(route('admin.clients.resetPassword', props.client.id), {
    preserveScroll: true,
    onSuccess: () => {
      showResetPassword.value = false
      passwordForm.reset()
    }
  })
}
</script>