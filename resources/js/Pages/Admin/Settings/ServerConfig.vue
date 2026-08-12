<script setup>
import { useForm } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Server, Network, TrendingUp } from 'lucide-vue-next'

const props = defineProps({ settings: Object })

const form = useForm({
    server_name: props.settings.server_name ?? '',
    server_id: props.settings.server_id ?? '',
    server_environment: props.settings.server_environment ?? 'Production',
    server_timezone: props.settings.server_timezone ?? 'UTC',
    server_url: props.settings.server_url ?? '',
    api_base_url: props.settings.api_base_url ?? '',
    cdn_url: props.settings.cdn_url ?? '',

    enable_load_balancing: props.settings.enable_load_balancing ?? false,
    lb_strategy: props.settings.lb_strategy ?? 'Round Robin',
    session_stickiness: props.settings.session_stickiness ?? false,
    stickiness_duration: props.settings.stickiness_duration ?? 3600,

    enable_auto_scaling: props.settings.enable_auto_scaling ?? false,
    min_servers: props.settings.min_servers ?? 2,
    max_servers: props.settings.max_servers ?? 10,
    cpu_threshold: props.settings.cpu_threshold ?? 80,
    memory_threshold: props.settings.memory_threshold ?? 85,
    scaling_cooldown: props.settings.scaling_cooldown ?? 300,
    downscale_threshold: props.settings.downscale_threshold ?? 30,
})
</script>

<template>
    <AdminLayout>
        <div class="p-6 max-w-4xl mx-auto space-y-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-white">Server Configuration</h1>
                <p class="text-gray-400 mt-1">Configure server settings, load balancing, and auto-scaling</p>
            </div>

            <form @submit.prevent="form.put(route('admin.settings.server.update'))" class="space-y-6">
                <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Server Settings</h3>
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Server Name</label>
                                <input
                                    v-model="form.server_name"
                                    type="text"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                    placeholder="Server Name"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Server ID</label>
                                <input
                                    v-model="form.server_id"
                                    type="text"
                                    readonly
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white opacity-60 cursor-not-allowed"
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Environment</label>
                                <select
                                    v-model="form.server_environment"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                >
                                    <option value="Production">Production</option>
                                    <option value="Staging">Staging</option>
                                    <option value="Development">Development</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Timezone</label>
                                <select
                                    v-model="form.server_timezone"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                >
                                    <option value="UTC">UTC</option>
                                    <option value="America/New_York">Eastern Time</option>
                                    <option value="America/Chicago">Central Time</option>
                                    <option value="America/Denver">Mountain Time</option>
                                    <option value="America/Los_Angeles">Pacific Time</option>
                                    <option value="Europe/London">London</option>
                                    <option value="Europe/Berlin">Berlin</option>
                                    <option value="Asia/Dubai">Dubai</option>
                                    <option value="Asia/Tokyo">Tokyo</option>
                                    <option value="Australia/Sydney">Sydney</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Server URL</label>
                            <input
                                v-model="form.server_url"
                                type="url"
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                placeholder="https://server.example.com"
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">API Base URL</label>
                                <input
                                    v-model="form.api_base_url"
                                    type="url"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                    placeholder="https://api.example.com"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">CDN URL</label>
                                <input
                                    v-model="form.cdn_url"
                                    type="url"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                    placeholder="https://cdn.example.com"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Load Balancing</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-white font-medium">Enable Load Balancing</p>
                                <p class="text-gray-400 text-sm">Distribute traffic across multiple servers</p>
                            </div>
                            <button
                                type="button"
                                @click="form.enable_load_balancing = !form.enable_load_balancing"
                                :class="form.enable_load_balancing ? 'bg-indigo-600' : 'bg-gray-600'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                            >
                                <span
                                    :class="form.enable_load_balancing ? 'translate-x-6' : 'translate-x-1'"
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                                />
                            </button>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Strategy</label>
                            <div class="space-y-2">
                                <label
                                    v-for="strategy in ['Round Robin', 'Least Connections', 'Weighted Round Robin', 'Geographic', 'Least Response Time']"
                                    :key="strategy"
                                    class="flex items-center space-x-3 cursor-pointer"
                                >
                                    <input
                                        type="radio"
                                        :value="strategy"
                                        v-model="form.lb_strategy"
                                        class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 focus:ring-indigo-500"
                                    />
                                    <span class="text-gray-300">{{ strategy }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-white font-medium">Session Stickiness</p>
                                <p class="text-gray-400 text-sm">Route same user to same backend server</p>
                            </div>
                            <button
                                type="button"
                                @click="form.session_stickiness = !form.session_stickiness"
                                :class="form.session_stickiness ? 'bg-indigo-600' : 'bg-gray-600'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                            >
                                <span
                                    :class="form.session_stickiness ? 'translate-x-6' : 'translate-x-1'"
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                                />
                            </button>
                        </div>

                        <div v-if="form.session_stickiness">
                            <label class="block text-sm font-medium text-gray-300 mb-1">Stickiness Duration (seconds)</label>
                            <input
                                v-model="form.stickiness_duration"
                                type="number"
                                min="0"
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                            />
                        </div>
                    </div>
                </div>

                <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Auto-Scaling</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-white font-medium">Enable Auto-Scaling</p>
                                <p class="text-gray-400 text-sm">Automatically scale servers based on load</p>
                            </div>
                            <button
                                type="button"
                                @click="form.enable_auto_scaling = !form.enable_auto_scaling"
                                :class="form.enable_auto_scaling ? 'bg-indigo-600' : 'bg-gray-600'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                            >
                                <span
                                    :class="form.enable_auto_scaling ? 'translate-x-6' : 'translate-x-1'"
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                                />
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Min Servers</label>
                                <input
                                    v-model="form.min_servers"
                                    type="number"
                                    min="1"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Max Servers</label>
                                <input
                                    v-model="form.max_servers"
                                    type="number"
                                    min="1"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">CPU Threshold (%)</label>
                                <input
                                    v-model="form.cpu_threshold"
                                    type="number"
                                    min="1"
                                    max="100"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Memory Threshold (%)</label>
                                <input
                                    v-model="form.memory_threshold"
                                    type="number"
                                    min="1"
                                    max="100"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Scaling Cooldown (seconds)</label>
                                <input
                                    v-model="form.scaling_cooldown"
                                    type="number"
                                    min="0"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Downscale Threshold (%)</label>
                                <input
                                    v-model="form.downscale_threshold"
                                    type="number"
                                    min="1"
                                    max="100"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                />
                            </div>
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
