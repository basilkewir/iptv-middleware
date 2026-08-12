<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto space-y-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">Integrations</h1>
        <p class="text-gray-400 mt-1">Configure third-party services, social media, and community integrations</p>
      </div>

      <form @submit.prevent="form.put(route('admin.settings.integrations.update'))" class="space-y-6">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Third-Party Integrations</h3>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="border-b border-gray-700">
                  <th class="text-left py-3 px-4 text-sm font-medium text-gray-400">Integration</th>
                  <th class="text-left py-3 px-4 text-sm font-medium text-gray-400">Status</th>
                  <th class="text-left py-3 px-4 text-sm font-medium text-gray-400">Description</th>
                  <th class="text-right py-3 px-4 text-sm font-medium text-gray-400">Enabled</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="integration in integrationsList" :key="integration.key" class="border-b border-gray-700/50">
                  <td class="py-3 px-4 text-white font-medium">{{ integration.name }}</td>
                  <td class="py-3 px-4">
                    <span class="px-2 py-1 text-xs rounded-full" :class="form[integration.key + '_enabled'] ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'">
                      {{ form[integration.key + '_enabled'] ? 'Active' : 'Inactive' }}
                    </span>
                  </td>
                  <td class="py-3 px-4 text-gray-400 text-sm">{{ integration.description }}</td>
                  <td class="py-3 px-4 text-right">
                    <input v-model="form[integration.key + '_enabled']" type="checkbox" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Google Analytics</h3>
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Tracking ID</label>
                <input v-model="form.ga_tracking_id" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" placeholder="UA-XXXXXXXXX-X" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Measurement ID</label>
                <input v-model="form.ga_measurement_id" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" placeholder="G-XXXXXXXXXX" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">API Secret</label>
              <input v-model="form.ga_api_secret" type="password" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div class="flex gap-6">
              <div class="flex items-center gap-3">
                <input v-model="form.ga_ecommerce" type="checkbox" id="ga_ecommerce" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                <label for="ga_ecommerce" class="text-gray-300">E-commerce Tracking</label>
              </div>
              <div class="flex items-center gap-3">
                <input v-model="form.ga_user_tracking" type="checkbox" id="ga_user_tracking" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                <label for="ga_user_tracking" class="text-gray-300">User Tracking</label>
              </div>
              <div class="flex items-center gap-3">
                <input v-model="form.ga_event_tracking" type="checkbox" id="ga_event_tracking" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                <label for="ga_event_tracking" class="text-gray-300">Event Tracking</label>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Social Media</h3>
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Facebook App ID</label>
                <input v-model="form.facebook_app_id" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Facebook App Secret</label>
                <input v-model="form.facebook_app_secret" type="password" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Twitter API Key</label>
                <input v-model="form.twitter_api_key" type="password" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Twitter API Secret</label>
                <input v-model="form.twitter_api_secret" type="password" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Instagram Access Token</label>
              <input v-model="form.instagram_token" type="password" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div class="flex gap-6">
              <div class="flex items-center gap-3">
                <input v-model="form.social_login" type="checkbox" id="social_login" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                <label for="social_login" class="text-gray-300">Social Login</label>
              </div>
              <div class="flex items-center gap-3">
                <input v-model="form.social_sharing" type="checkbox" id="social_sharing" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                <label for="social_sharing" class="text-gray-300">Social Sharing</label>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Community</h3>
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Discord Webhook URL</label>
                <input v-model="form.discord_webhook" type="url" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Slack Webhook URL</label>
                <input v-model="form.slack_webhook" type="url" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Telegram Bot Token</label>
                <input v-model="form.telegram_bot_token" type="password" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Telegram Chat ID</label>
                <input v-model="form.telegram_chat_id" type="text" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-indigo-500" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Notification Events</label>
              <div class="grid grid-cols-2 gap-3 mt-2">
                <div v-for="evt in notificationEvents" :key="evt.key" class="flex items-center gap-3">
                  <input v-model="form.notification_events" :value="evt.key" type="checkbox" :id="evt.key" class="w-4 h-4 rounded bg-gray-700 border-gray-600 text-indigo-600 focus:ring-indigo-500" />
                  <label :for="evt.key" class="text-gray-300">{{ evt.label }}</label>
                </div>
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
import { useForm } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ settings: Object })

const integrationsList = [
  { key: 'google_analytics', name: 'Google Analytics', description: 'Track user behavior and conversions' },
  { key: 'facebook_pixel', name: 'Facebook Pixel', description: 'Facebook advertising analytics' },
  { key: 'livechat', name: 'LiveChat', description: 'Real-time customer support chat' },
  { key: 'discord', name: 'Discord', description: 'Community notifications and alerts' },
  { key: 'slack', name: 'Slack', description: 'Team notifications and alerts' },
]

const notificationEvents = [
  { key: 'user_signup', label: 'User Signup' },
  { key: 'payment', label: 'Payment' },
  { key: 'system_alerts', label: 'System Alerts' },
  { key: 'server_issues', label: 'Server Issues' },
]

const form = useForm({
  google_analytics_enabled: props.settings.google_analytics_enabled ?? false,
  facebook_pixel_enabled: props.settings.facebook_pixel_enabled ?? false,
  livechat_enabled: props.settings.livechat_enabled ?? false,
  discord_enabled: props.settings.discord_enabled ?? false,
  slack_enabled: props.settings.slack_enabled ?? false,
  ga_tracking_id: props.settings.ga_tracking_id || '',
  ga_measurement_id: props.settings.ga_measurement_id || '',
  ga_api_secret: props.settings.ga_api_secret || '',
  ga_ecommerce: props.settings.ga_ecommerce ?? false,
  ga_user_tracking: props.settings.ga_user_tracking ?? false,
  ga_event_tracking: props.settings.ga_event_tracking ?? false,
  facebook_app_id: props.settings.facebook_app_id || '',
  facebook_app_secret: props.settings.facebook_app_secret || '',
  twitter_api_key: props.settings.twitter_api_key || '',
  twitter_api_secret: props.settings.twitter_api_secret || '',
  instagram_token: props.settings.instagram_token || '',
  social_login: props.settings.social_login ?? false,
  social_sharing: props.settings.social_sharing ?? false,
  discord_webhook: props.settings.discord_webhook || '',
  slack_webhook: props.settings.slack_webhook || '',
  telegram_bot_token: props.settings.telegram_bot_token || '',
  telegram_chat_id: props.settings.telegram_chat_id || '',
  notification_events: props.settings.notification_events || ['system_alerts', 'server_issues'],
})
</script>
