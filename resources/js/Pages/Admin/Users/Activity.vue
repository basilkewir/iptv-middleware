<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto">
      <div class="mb-6">
        <Link :href="route('admin.users.edit', user.id)" class="text-gray-400 hover:text-white text-sm flex items-center gap-1 mb-2">
          <ArrowLeft class="w-4 h-4" /> Back to User
        </Link>
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 rounded-full bg-indigo-600 flex items-center justify-center text-white font-bold">
            {{ (user.first_name || user.username || 'U').charAt(0).toUpperCase() }}
          </div>
          <div>
            <h1 class="text-2xl font-bold text-white">Activity Log: {{ userFullName }}</h1>
            <p class="text-gray-400">{{ user.email }}</p>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="flex gap-1 bg-gray-800 rounded-lg p-1 mb-6 border border-gray-700">
        <button @click="tab = 'activity'" class="flex-1 px-4 py-2.5 rounded-md text-sm font-medium transition" :class="tab === 'activity' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white'">Activity Log</button>
        <button @click="tab = 'watch'" class="flex-1 px-4 py-2.5 rounded-md text-sm font-medium transition" :class="tab === 'watch' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white'">Watch History</button>
      </div>

      <!-- Activity Log -->
      <div v-if="tab === 'activity'" class="bg-gray-800 rounded-xl border border-gray-700">
        <div class="p-4 border-b border-gray-700 flex items-center justify-between">
          <h2 class="text-white font-semibold">Activity Log</h2>
          <div class="flex gap-2">
            <select v-model="filterAction" class="px-3 py-1.5 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm">
              <option value="">All Actions</option>
              <option value="login">Login</option>
              <option value="stream">Stream</option>
              <option value="subscription">Subscription</option>
              <option value="profile">Profile</option>
            </select>
          </div>
        </div>
        <div class="divide-y divide-gray-700">
          <div v-for="log in activityLog" :key="log.id" class="flex items-center gap-4 p-4 hover:bg-gray-700/50">
            <div class="w-10 h-10 rounded-full flex items-center justify-center" :class="actionBg(log.action)">
              <component :is="actionIcon(log.action)" class="w-5 h-5" :class="actionIconClass(log.action)" />
            </div>
            <div class="flex-1">
              <p class="text-white text-sm font-medium">{{ log.action }}</p>
              <p class="text-gray-400 text-xs">{{ log.description }}</p>
            </div>
            <div class="text-right">
              <p class="text-gray-400 text-xs">{{ log.ip_address }}</p>
              <p class="text-gray-500 text-xs">{{ log.created_at }}</p>
            </div>
          </div>
          <p v-if="!activityLog?.length" class="text-gray-400 text-center py-8">No activity recorded</p>
        </div>
      </div>

      <!-- Watch History -->
      <div v-if="tab === 'watch'" class="bg-gray-800 rounded-xl border border-gray-700">
        <div class="p-4 border-b border-gray-700">
          <h2 class="text-white font-semibold">Watch History</h2>
        </div>
        <div class="divide-y divide-gray-700">
          <div v-for="item in watchHistory" :key="item.id" class="flex items-center gap-4 p-4 hover:bg-gray-700/50">
            <div class="w-10 h-10 rounded bg-blue-600/20 flex items-center justify-center">
              <Play class="w-5 h-5 text-blue-400" />
            </div>
            <div class="flex-1">
              <p class="text-white text-sm font-medium">{{ item.title || item.channel_name || 'Unknown' }}</p>
              <p class="text-gray-400 text-xs">{{ item.type || 'stream' }}</p>
            </div>
            <div class="text-right">
              <p class="text-gray-400 text-xs">{{ item.watched_at }}</p>
              <p v-if="item.progress" class="text-gray-500 text-xs">{{ item.progress }}% watched</p>
            </div>
          </div>
          <p v-if="!watchHistory?.length" class="text-gray-400 text-center py-8">No watch history</p>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ArrowLeft, LogIn, Tv, CreditCard, User, Play } from 'lucide-vue-next'

const props = defineProps({
  user: { type: Object, required: true },
  activityLog: { type: Array, default: () => [] },
  watchHistory: { type: Array, default: () => [] },
})

const tab = ref('activity')
const filterAction = ref('')

const userFullName = computed(() => {
  const parts = [props.user.first_name, props.user.last_name].filter(Boolean)
  return parts.length ? parts.join(' ') : props.user.username
})

const actionBg = (action) => {
  const map = { login: 'bg-green-600/20', stream: 'bg-blue-600/20', subscription: 'bg-purple-600/20', profile: 'bg-yellow-600/20' }
  return map[action] || 'bg-gray-600/20'
}

const actionIcon = (action) => {
  const map = { login: LogIn, stream: Tv, subscription: CreditCard, profile: User }
  return map[action] || LogIn
}

const actionIconClass = (action) => {
  const map = { login: 'text-green-400', stream: 'text-blue-400', subscription: 'text-purple-400', profile: 'text-yellow-400' }
  return map[action] || 'text-gray-400'
}
</script>
