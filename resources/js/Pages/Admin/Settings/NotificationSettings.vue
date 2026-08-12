<script setup>
import { useForm } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Bell, MessageSquare, Webhook } from 'lucide-vue-next'

const props = defineProps({ settings: Object })

const form = useForm({
    enable_email_notifications: props.settings.enable_email_notifications ?? true,
    enable_sms_notifications: props.settings.enable_sms_notifications ?? false,
    enable_push_notifications: props.settings.enable_push_notifications ?? false,
    enable_dashboard_notifications: props.settings.enable_dashboard_notifications ?? true,
    enable_webhook_notifications: props.settings.enable_webhook_notifications ?? false,

    sms_provider: props.settings.sms_provider ?? 'Twilio',
    sms_account_sid: props.settings.sms_account_sid ?? '',
    sms_auth_token: props.settings.sms_auth_token ?? '',
    sms_from_number: props.settings.sms_from_number ?? '',

    event_user_registration: props.settings.event_user_registration ?? true,
    event_user_login: props.settings.event_user_login ?? true,
    event_password_reset: props.settings.event_password_reset ?? true,
    event_account_suspended: props.settings.event_account_suspended ?? true,
    event_account_activated: props.settings.event_account_activated ?? true,
    event_subscription_created: props.settings.event_subscription_created ?? true,
    event_subscription_renewed: props.settings.event_subscription_renewed ?? true,
    event_subscription_expired: props.settings.event_subscription_expired ?? true,
    event_subscription_cancelled: props.settings.event_subscription_cancelled ?? true,
    event_payment_successful: props.settings.event_payment_successful ?? true,
    event_payment_failed: props.settings.event_payment_failed ?? true,
    event_invoice_generated: props.settings.event_invoice_generated ?? true,
    event_new_content: props.settings.event_new_content ?? false,
    event_server_alert: props.settings.event_server_alert ?? true,
    event_maintenance: props.settings.event_maintenance ?? true,

    webhook_url: props.settings.webhook_url ?? '',
    webhook_secret: props.settings.webhook_secret ?? '',
    webhook_event_user_created: props.settings.webhook_event_user_created ?? true,
    webhook_event_user_deleted: props.settings.webhook_event_user_deleted ?? true,
    webhook_event_subscription_started: props.settings.webhook_event_subscription_started ?? true,
    webhook_event_subscription_ended: props.settings.webhook_event_subscription_ended ?? true,
    webhook_event_payment_received: props.settings.webhook_event_payment_received ?? true,
    retry_webhooks: props.settings.retry_webhooks ?? true,
    max_webhook_retries: props.settings.max_webhook_retries ?? 5,
})
</script>

<template>
    <AdminLayout>
        <div class="p-6 max-w-4xl mx-auto space-y-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-white">Notification Settings</h1>
                <p class="text-gray-400 mt-1">Configure notification channels, events, and webhooks</p>
            </div>

            <form @submit.prevent="form.put(route('admin.settings.notifications.update'))" class="space-y-6">
                <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Notification Channels</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-white font-medium">Email Notifications</p>
                                <p class="text-gray-400 text-sm">Send notifications via email</p>
                            </div>
                            <button
                                type="button"
                                @click="form.enable_email_notifications = !form.enable_email_notifications"
                                :class="form.enable_email_notifications ? 'bg-indigo-600' : 'bg-gray-600'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                            >
                                <span
                                    :class="form.enable_email_notifications ? 'translate-x-6' : 'translate-x-1'"
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                                />
                            </button>
                        </div>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-white font-medium">SMS Notifications</p>
                                <p class="text-gray-400 text-sm">Send notifications via SMS</p>
                            </div>
                            <button
                                type="button"
                                @click="form.enable_sms_notifications = !form.enable_sms_notifications"
                                :class="form.enable_sms_notifications ? 'bg-indigo-600' : 'bg-gray-600'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                            >
                                <span
                                    :class="form.enable_sms_notifications ? 'translate-x-6' : 'translate-x-1'"
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                                />
                            </button>
                        </div>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-white font-medium">Push Notifications</p>
                                <p class="text-gray-400 text-sm">Send browser/mobile push notifications</p>
                            </div>
                            <button
                                type="button"
                                @click="form.enable_push_notifications = !form.enable_push_notifications"
                                :class="form.enable_push_notifications ? 'bg-indigo-600' : 'bg-gray-600'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                            >
                                <span
                                    :class="form.enable_push_notifications ? 'translate-x-6' : 'translate-x-1'"
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                                />
                            </button>
                        </div>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-white font-medium">Dashboard Notifications</p>
                                <p class="text-gray-400 text-sm">Show notifications in the admin dashboard</p>
                            </div>
                            <button
                                type="button"
                                @click="form.enable_dashboard_notifications = !form.enable_dashboard_notifications"
                                :class="form.enable_dashboard_notifications ? 'bg-indigo-600' : 'bg-gray-600'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                            >
                                <span
                                    :class="form.enable_dashboard_notifications ? 'translate-x-6' : 'translate-x-1'"
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                                />
                            </button>
                        </div>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-white font-medium">Webhook Notifications</p>
                                <p class="text-gray-400 text-sm">Send notifications to external webhooks</p>
                            </div>
                            <button
                                type="button"
                                @click="form.enable_webhook_notifications = !form.enable_webhook_notifications"
                                :class="form.enable_webhook_notifications ? 'bg-indigo-600' : 'bg-gray-600'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                            >
                                <span
                                    :class="form.enable_webhook_notifications ? 'translate-x-6' : 'translate-x-1'"
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                                />
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="form.enable_sms_notifications" class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">SMS Configuration</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">SMS Provider</label>
                            <select
                                v-model="form.sms_provider"
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                            >
                                <option value="Twilio">Twilio</option>
                                <option value="Vonage">Vonage</option>
                                <option value="MessageBird">MessageBird</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Account SID</label>
                                <input
                                    v-model="form.sms_account_sid"
                                    type="text"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Auth Token</label>
                                <input
                                    v-model="form.sms_auth_token"
                                    type="password"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">From Number</label>
                            <input
                                v-model="form.sms_from_number"
                                type="tel"
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                placeholder="+1234567890"
                            />
                        </div>
                    </div>
                </div>

                <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Notification Events</h3>
                    <div class="space-y-2">
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" v-model="form.event_user_registration" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                            <span class="text-gray-300">User Registration</span>
                        </label>
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" v-model="form.event_user_login" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                            <span class="text-gray-300">User Login</span>
                        </label>
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" v-model="form.event_password_reset" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                            <span class="text-gray-300">Password Reset</span>
                        </label>
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" v-model="form.event_account_suspended" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                            <span class="text-gray-300">Account Suspended</span>
                        </label>
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" v-model="form.event_account_activated" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                            <span class="text-gray-300">Account Activated</span>
                        </label>
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" v-model="form.event_subscription_created" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                            <span class="text-gray-300">Subscription Created</span>
                        </label>
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" v-model="form.event_subscription_renewed" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                            <span class="text-gray-300">Subscription Renewed</span>
                        </label>
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" v-model="form.event_subscription_expired" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                            <span class="text-gray-300">Subscription Expired</span>
                        </label>
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" v-model="form.event_subscription_cancelled" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                            <span class="text-gray-300">Subscription Cancelled</span>
                        </label>
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" v-model="form.event_payment_successful" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                            <span class="text-gray-300">Payment Successful</span>
                        </label>
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" v-model="form.event_payment_failed" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                            <span class="text-gray-300">Payment Failed</span>
                        </label>
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" v-model="form.event_invoice_generated" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                            <span class="text-gray-300">Invoice Generated</span>
                        </label>
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" v-model="form.event_new_content" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                            <span class="text-gray-300">New Content</span>
                        </label>
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" v-model="form.event_server_alert" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                            <span class="text-gray-300">Server Alert</span>
                        </label>
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" v-model="form.event_maintenance" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                            <span class="text-gray-300">Maintenance</span>
                        </label>
                    </div>
                </div>

                <div v-if="form.enable_webhook_notifications" class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Webhook Configuration</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Webhook URL</label>
                            <input
                                v-model="form.webhook_url"
                                type="url"
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                placeholder="https://your-webhook-url.com/hook"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Webhook Secret</label>
                            <input
                                v-model="form.webhook_secret"
                                type="password"
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Webhook Events</label>
                            <div class="space-y-2">
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input type="checkbox" v-model="form.webhook_event_user_created" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                                    <span class="text-gray-300">User Created</span>
                                </label>
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input type="checkbox" v-model="form.webhook_event_user_deleted" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                                    <span class="text-gray-300">User Deleted</span>
                                </label>
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input type="checkbox" v-model="form.webhook_event_subscription_started" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                                    <span class="text-gray-300">Subscription Started</span>
                                </label>
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input type="checkbox" v-model="form.webhook_event_subscription_ended" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                                    <span class="text-gray-300">Subscription Ended</span>
                                </label>
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input type="checkbox" v-model="form.webhook_event_payment_received" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                                    <span class="text-gray-300">Payment Received</span>
                                </label>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-white font-medium">Retry Webhooks</p>
                                <p class="text-gray-400 text-sm">Automatically retry failed webhook deliveries</p>
                            </div>
                            <button
                                type="button"
                                @click="form.retry_webhooks = !form.retry_webhooks"
                                :class="form.retry_webhooks ? 'bg-indigo-600' : 'bg-gray-600'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                            >
                                <span
                                    :class="form.retry_webhooks ? 'translate-x-6' : 'translate-x-1'"
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                                />
                            </button>
                        </div>
                        <div v-if="form.retry_webhooks">
                            <label class="block text-sm font-medium text-gray-300 mb-1">Max Webhook Retries</label>
                            <input
                                v-model="form.max_webhook_retries"
                                type="number"
                                min="0"
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition disabled:opacity-50"
                    >
                        {{ form.processing ? 'Saving...' : 'Save Settings' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
