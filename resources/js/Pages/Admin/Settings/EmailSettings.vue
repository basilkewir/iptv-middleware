<script setup>
import { useForm } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Mail, FileText, Clock } from 'lucide-vue-next'

const props = defineProps({ settings: Object })

const form = useForm({
    mail_driver: props.settings.mail_driver ?? 'SMTP',
    smtp_host: props.settings.smtp_host ?? '',
    smtp_port: props.settings.smtp_port ?? 587,
    smtp_encryption: props.settings.smtp_encryption ?? 'TLS',
    smtp_username: props.settings.smtp_username ?? '',
    smtp_password: props.settings.smtp_password ?? '',
    sender_name: props.settings.sender_name ?? '',
    sender_email: props.settings.sender_email ?? '',
    reply_to: props.settings.reply_to ?? '',

    email_template: props.settings.email_template ?? 'Welcome',
    template_content: props.settings.template_content ?? '',

    email_queue_driver: props.settings.email_queue_driver ?? 'Database',
    queue_connection: props.settings.queue_connection ?? 'default',
    queue_name: props.settings.queue_name ?? 'emails',
    batch_size: props.settings.batch_size ?? 100,
    max_attempts: props.settings.max_attempts ?? 3,
    retry_delay: props.settings.retry_delay ?? 300,
    send_immediately: props.settings.send_immediately ?? false,
})

const templateVariables = [
    '{{user_name}}',
    '{{user_email}}',
    '{{company_name}}',
    '{{subscription_plan}}',
    '{{expiry_date}}',
    '{{invoice_number}}',
    '{{reset_link}}',
    '{{amount}}',
]

const templates = ['Welcome', 'Password Reset', 'Invoice', 'Subscription', 'Expiry']

const sendTestEmail = () => {
    form.post(route('admin.settings.email.test'))
}
</script>

<template>
    <AdminLayout>
        <div class="p-6 max-w-4xl mx-auto space-y-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-white">Email Settings</h1>
                <p class="text-gray-400 mt-1">Configure mail drivers, templates, and queue settings</p>
            </div>

            <form @submit.prevent="form.put(route('admin.settings.email.update'))" class="space-y-6">
                <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Mail Configuration</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Mail Driver</label>
                            <div class="space-y-2">
                                <label
                                    v-for="driver in ['SMTP', 'SendGrid', 'Mailgun', 'Amazon SES', 'Postmark', 'Mail']"
                                    :key="driver"
                                    class="flex items-center space-x-3 cursor-pointer"
                                >
                                    <input
                                        type="radio"
                                        :value="driver"
                                        v-model="form.mail_driver"
                                        class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 focus:ring-indigo-500"
                                    />
                                    <span class="text-gray-300">{{ driver }}</span>
                                </label>
                            </div>
                        </div>

                        <template v-if="form.mail_driver === 'SMTP'">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-1">SMTP Host</label>
                                    <input
                                        v-model="form.smtp_host"
                                        type="text"
                                        class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                        placeholder="smtp.example.com"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-1">SMTP Port</label>
                                    <input
                                        v-model="form.smtp_port"
                                        type="number"
                                        min="1"
                                        max="65535"
                                        class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                    />
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Encryption</label>
                                <select
                                    v-model="form.smtp_encryption"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                >
                                    <option value="TLS">TLS</option>
                                    <option value="SSL">SSL</option>
                                    <option value="None">None</option>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-1">Username</label>
                                    <input
                                        v-model="form.smtp_username"
                                        type="text"
                                        class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-1">Password</label>
                                    <input
                                        v-model="form.smtp_password"
                                        type="password"
                                        class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                    />
                                </div>
                            </div>
                        </template>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Sender Name</label>
                                <input
                                    v-model="form.sender_name"
                                    type="text"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                    placeholder="My App"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Sender Email</label>
                                <input
                                    v-model="form.sender_email"
                                    type="email"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                    placeholder="no-reply@example.com"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Reply To</label>
                            <input
                                v-model="form.reply_to"
                                type="email"
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                placeholder="support@example.com"
                            />
                        </div>
                    </div>
                </div>

                <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Email Templates</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Template</label>
                            <select
                                v-model="form.email_template"
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                            >
                                <option v-for="tpl in templates" :key="tpl" :value="tpl">{{ tpl }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Template Content</label>
                            <textarea
                                v-model="form.template_content"
                                rows="10"
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none font-mono text-sm"
                                placeholder="Write your email template here..."
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Available Variables</label>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="variable in templateVariables"
                                    :key="variable"
                                    class="px-3 py-1 bg-gray-700 border border-gray-600 rounded-lg text-gray-300 text-sm font-mono cursor-pointer hover:bg-gray-600 transition"
                                >
                                    {{ variable }}
                                </span>
                            </div>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button
                                type="button"
                                class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition"
                            >
                                Reset
                            </button>
                            <button
                                type="button"
                                class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition"
                            >
                                Preview
                            </button>
                            <button
                                type="button"
                                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition"
                            >
                                Save Template
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Email Queue</h3>
                    <div class="space-y-4">
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Queue Driver</label>
                                <select
                                    v-model="form.email_queue_driver"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                >
                                    <option value="Database">Database</option>
                                    <option value="Redis">Redis</option>
                                    <option value="Sync">Sync</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Connection</label>
                                <input
                                    v-model="form.queue_connection"
                                    type="text"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Queue Name</label>
                                <input
                                    v-model="form.queue_name"
                                    type="text"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Batch Size</label>
                                <input
                                    v-model="form.batch_size"
                                    type="number"
                                    min="1"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Max Attempts</label>
                                <input
                                    v-model="form.max_attempts"
                                    type="number"
                                    min="1"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Retry Delay (seconds)</label>
                                <input
                                    v-model="form.retry_delay"
                                    type="number"
                                    min="0"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                />
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-white font-medium">Send Immediately</p>
                                <p class="text-gray-400 text-sm">Bypass queue and send emails right away</p>
                            </div>
                            <button
                                type="button"
                                @click="form.send_immediately = !form.send_immediately"
                                :class="form.send_immediately ? 'bg-indigo-600' : 'bg-gray-600'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                            >
                                <span
                                    :class="form.send_immediately ? 'translate-x-6' : 'translate-x-1'"
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                                />
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button
                        type="button"
                        @click="sendTestEmail"
                        class="px-6 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition"
                    >
                        Send Test Email
                    </button>
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
