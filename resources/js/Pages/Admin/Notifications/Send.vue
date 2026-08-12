<template>
  <AdminLayout>
    <div class="p-6 max-w-3xl mx-auto space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <Link :href="route('admin.dashboard')" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
            <ArrowLeft class="w-4 h-4" /> Back to Dashboard
          </Link>
          <h1 class="text-2xl font-bold text-white">Send Notification</h1>
          <p class="text-gray-400 mt-1">Send push notifications to users or groups</p>
        </div>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-indigo-500/20 rounded-lg">
              <Send class="w-5 h-5 text-indigo-400" />
            </div>
            <div>
              <p class="text-gray-400 text-sm">Sent Today</p>
              <p class="text-2xl font-bold text-white">{{ stats?.sent_today || 0 }}</p>
            </div>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-green-500/20 rounded-lg">
              <CheckCircle class="w-5 h-5 text-green-400" />
            </div>
            <div>
              <p class="text-gray-400 text-sm">Delivered</p>
              <p class="text-2xl font-bold text-green-400">{{ stats?.delivered || 0 }}</p>
            </div>
          </div>
        </div>
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-purple-500/20 rounded-lg">
              <Users class="w-5 h-5 text-purple-400" />
            </div>
            <div>
              <p class="text-gray-400 text-sm">Total Recipients</p>
              <p class="text-2xl font-bold text-white">{{ stats?.total_recipients || 0 }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Notification Form -->
      <div class="bg-gray-800 rounded-xl p-6 border border-gray-700 space-y-6">
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Notification Type</label>
          <div class="grid grid-cols-4 gap-3">
            <label v-for="type in notificationTypes" :key="type.id" class="flex flex-col items-center gap-2 p-3 rounded-lg border cursor-pointer transition"
              :class="form.type === type.id ? 'border-indigo-500 bg-indigo-500/10' : 'border-gray-600 hover:border-gray-500'"
            >
              <input v-model="form.type" type="radio" :value="type.id" class="hidden" />
              <component :is="type.icon" class="w-6 h-6" :class="form.type === type.id ? 'text-indigo-400' : 'text-gray-400'" />
              <span class="text-xs text-center" :class="form.type === type.id ? 'text-indigo-400' : 'text-gray-300'">{{ type.label }}</span>
            </label>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Recipients</label>
          <select v-model="form.recipients" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            <option value="all">All Users</option>
            <option value="active">Active Subscriptions</option>
            <option value="expiring">Expiring Soon (3 days)</option>
            <option value="expired">Expired Subscriptions</option>
            <option value="resellers">Resellers Only</option>
            <option value="clients">Clients Only</option>
            <option value="custom">Custom Selection</option>
          </select>
        </div>

        <div v-if="form.recipients === 'custom'">
          <label class="block text-sm font-medium text-gray-300 mb-2">Select Users</label>
          <div class="bg-gray-700/50 rounded-lg p-4 max-h-48 overflow-y-auto">
            <div v-for="user in users" :key="user.id" class="flex items-center gap-3 py-2 border-b border-gray-600 last:border-0">
              <input v-model="form.selectedUsers" type="checkbox" :value="user.id" class="rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
              <div>
                <p class="text-white text-sm">{{ user.first_name }} {{ user.last_name }}</p>
                <p class="text-gray-400 text-xs">{{ user.email }}</p>
              </div>
            </div>
          </div>
          <p class="text-gray-500 text-xs mt-1">{{ form.selectedUsers.length }} users selected</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Subject / Title</label>
          <input v-model="form.title" type="text" placeholder="Notification subject..." class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Message</label>
          <textarea v-model="form.message" rows="4" placeholder="Type your notification message..." class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-indigo-500 resize-none" />
          <p class="text-gray-500 text-xs mt-1">{{ form.message.length }}/500 characters</p>
        </div>

        <!-- Channel Assignment -->
        <div v-if="form.type === 'channel_update'">
          <label class="block text-sm font-medium text-gray-300 mb-2">Related Channel</label>
          <select v-model="form.channelId" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            <option value="">Select channel...</option>
            <option v-for="ch in channels" :key="ch.id" :value="ch.id">{{ ch.name }}</option>
          </select>
        </div>

        <div v-if="form.type === 'subscription_expiry'">
          <label class="block text-sm font-medium text-gray-300 mb-2">Expiry Warning (days before)</label>
          <div class="flex gap-3">
            <label v-for="d in [1, 3, 7]" :key="d" class="flex items-center gap-2 cursor-pointer">
              <input v-model="form.warningDays" type="radio" :value="d" class="text-indigo-600 focus:ring-indigo-500" />
              <span class="text-gray-300">{{ d }} day{{ d > 1 ? 's' : '' }}</span>
            </label>
          </div>
        </div>

        <!-- Priority & Schedule -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Priority</label>
            <select v-model="form.priority" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
              <option value="low">Low</option>
              <option value="normal">Normal</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Schedule</label>
            <select v-model="form.schedule" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500">
              <option value="now">Send Now</option>
              <option value="later">Schedule for Later</option>
            </select>
          </div>
        </div>

        <div v-if="form.schedule === 'later'">
          <label class="block text-sm font-medium text-gray-300 mb-2">Scheduled Date & Time</label>
          <input v-model="form.scheduledAt" type="datetime-local" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
        </div>

        <!-- Preview -->
        <div class="bg-gray-700/50 rounded-lg p-4">
          <h3 class="text-white font-medium mb-3">Preview</h3>
          <div class="bg-gray-900 rounded-lg p-4 border border-gray-600">
            <div class="flex items-start gap-3">
              <div class="p-2 rounded-lg" :class="previewIconBg">
                <component :is="previewIcon" class="w-5 h-5" :class="previewIconColor" />
              </div>
              <div class="flex-1">
                <p class="text-white font-medium">{{ form.title || 'Notification Title' }}</p>
                <p class="text-gray-400 text-sm mt-1">{{ form.message || 'Your notification message will appear here...' }}</p>
                <p class="text-gray-500 text-xs mt-2">{{ previewRecipientCount }} recipients</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Error -->
        <div v-if="error" class="p-4 rounded-lg bg-red-500/20 text-red-400 flex items-center gap-2">
          <AlertCircle class="w-4 h-4" />
          {{ error }}
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <Link :href="route('admin.dashboard')" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">Cancel</Link>
          <button @click="sendNotification" :disabled="sending || !form.title || !form.message" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50 flex items-center gap-2">
            <Send class="w-4 h-4" />
            {{ sending ? 'Sending...' : form.schedule === 'later' ? 'Schedule Notification' : 'Send Now' }}
          </button>
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
import {
  ArrowLeft, Send, CheckCircle, Users, AlertCircle, Bell, Info,
  AlertTriangle, Tv, Clock, Star
} from 'lucide-vue-next'

const props = defineProps({
  users: { type: Array, default: () => [] },
  channels: { type: Array, default: () => [] },
  stats: { type: Object, default: () => ({}) },
})

const sending = ref(false)
const error = ref(null)

const form = ref({
  type: 'info',
  recipients: 'all',
  selectedUsers: [],
  title: '',
  message: '',
  channelId: '',
  warningDays: 3,
  priority: 'normal',
  schedule: 'now',
  scheduledAt: '',
})

const notificationTypes = [
  { id: 'info', label: 'Information', icon: Info },
  { id: 'channel_update', label: 'Channel Update', icon: Tv },
  { id: 'subscription_expiry', label: 'Expiry Warning', icon: Clock },
  { id: 'maintenance', label: 'Maintenance', icon: AlertTriangle },
  { id: 'promotion', label: 'Promotion', icon: Star },
  { id: 'system', label: 'System Alert', icon: Bell },
]

const previewIcon = computed(() => {
  const type = notificationTypes.find(t => t.id === form.value.type)
  return type?.icon || Bell
})

const previewIconBg = computed(() => ({
  'bg-indigo-500/20': form.value.type === 'info',
  'bg-green-500/20': form.value.type === 'channel_update',
  'bg-yellow-500/20': form.value.type === 'subscription_expiry',
  'bg-red-500/20': form.value.type === 'maintenance' || form.value.type === 'system',
  'bg-purple-500/20': form.value.type === 'promotion',
}))

const previewIconColor = computed(() => ({
  'text-indigo-400': form.value.type === 'info',
  'text-green-400': form.value.type === 'channel_update',
  'text-yellow-400': form.value.type === 'subscription_expiry',
  'text-red-400': form.value.type === 'maintenance' || form.value.type === 'system',
  'text-purple-400': form.value.type === 'promotion',
}))

const previewRecipientCount = computed(() => {
  if (form.value.recipients === 'custom') return form.value.selectedUsers.length
  const map = { all: props.users?.length || 0, active: Math.floor((props.users?.length || 0) * 0.7), expiring: 12, expired: 8, resellers: 5, clients: 50 }
  return map[form.value.recipients] || 0
})

const sendNotification = () => {
  error.value = null
  if (!form.value.title) { error.value = 'Title is required'; return }
  if (!form.value.message) { error.value = 'Message is required'; return }
  if (form.value.recipients === 'custom' && !form.value.selectedUsers.length) { error.value = 'Select at least one recipient'; return }

  sending.value = true
  router.post(route('admin.notifications.send'), form.value, {
    onFinish: () => { sending.value = false },
    onSuccess: () => { router.reload() },
    onError: (errors) => { error.value = errors.message || 'Failed to send notification.' },
  })
}
</script>
