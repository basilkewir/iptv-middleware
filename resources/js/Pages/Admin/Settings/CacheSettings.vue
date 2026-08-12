<script setup>
import { useForm } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Database, Shield, Flame } from 'lucide-vue-next'

const props = defineProps({ settings: Object })

const form = useForm({
    cache_driver: props.settings.cache_driver ?? 'Redis',
    redis_host: props.settings.redis_host ?? '127.0.0.1',
    redis_port: props.settings.redis_port ?? 6379,
    redis_password: props.settings.redis_password ?? '',
    redis_database: props.settings.redis_database ?? 0,
    redis_persistent: props.settings.redis_persistent ?? false,

    default_ttl: props.settings.default_ttl ?? 3600,
    max_cache_size: props.settings.max_cache_size ?? 1024,
    cleanup_interval: props.settings.cleanup_interval ?? 3600,
    cache_prefix: props.settings.cache_prefix ?? 'iptv_',
    cacheable_channels: props.settings.cacheable_channels ?? true,
    cacheable_epg: props.settings.cacheable_epg ?? true,
    cacheable_m3u: props.settings.cacheable_m3u ?? true,
    cacheable_api_responses: props.settings.cacheable_api_responses ?? true,
    cacheable_vod: props.settings.cacheable_vod ?? true,
    cacheable_sessions: props.settings.cacheable_sessions ?? false,
    cacheable_config: props.settings.cacheable_config ?? true,

    enable_warming: props.settings.enable_warming ?? false,
    warmup_schedule: props.settings.warmup_schedule ?? 'Daily',
    warmup_items: props.settings.warmup_items ?? 1000,
    warmup_priority: props.settings.warmup_priority ?? 'Medium',
    prewarm_on_start: props.settings.prewarm_on_start ?? false,
})

const clearCache = () => {
    form.post(route('admin.settings.cache.clear'))
}
</script>

<template>
    <AdminLayout>
        <div class="p-6 max-w-4xl mx-auto space-y-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-white">Cache Settings</h1>
                <p class="text-gray-400 mt-1">Configure caching backend, policies, and warming strategies</p>
            </div>

            <form @submit.prevent="form.put(route('admin.settings.cache.update'))" class="space-y-6">
                <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Cache Backend</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Cache Driver</label>
                            <div class="space-y-2">
                                <label
                                    v-for="driver in ['Redis', 'Memcached', 'Database', 'File', 'Array']"
                                    :key="driver"
                                    class="flex items-center space-x-3 cursor-pointer"
                                >
                                    <input
                                        type="radio"
                                        :value="driver"
                                        v-model="form.cache_driver"
                                        class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 focus:ring-indigo-500"
                                    />
                                    <span class="text-gray-300">{{ driver }}</span>
                                </label>
                            </div>
                        </div>

                        <template v-if="form.cache_driver === 'Redis'">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-1">Redis Host</label>
                                    <input
                                        v-model="form.redis_host"
                                        type="text"
                                        class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-1">Redis Port</label>
                                    <input
                                        v-model="form.redis_port"
                                        type="number"
                                        min="1"
                                        max="65535"
                                        class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                    />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-1">Redis Password</label>
                                    <input
                                        v-model="form.redis_password"
                                        type="password"
                                        class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                        placeholder="Leave empty if none"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-1">Redis Database</label>
                                    <input
                                        v-model="form.redis_database"
                                        type="number"
                                        min="0"
                                        max="15"
                                        class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                    />
                                </div>
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-white font-medium">Persistent Connection</p>
                                    <p class="text-gray-400 text-sm">Keep Redis connection open between requests</p>
                                </div>
                                <button
                                    type="button"
                                    @click="form.redis_persistent = !form.redis_persistent"
                                    :class="form.redis_persistent ? 'bg-indigo-600' : 'bg-gray-600'"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                                >
                                    <span
                                        :class="form.redis_persistent ? 'translate-x-6' : 'translate-x-1'"
                                        class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                                    />
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Cache Policies</h3>
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Default TTL (seconds)</label>
                                <input
                                    v-model="form.default_ttl"
                                    type="number"
                                    min="0"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Max Cache Size (MB)</label>
                                <input
                                    v-model="form.max_cache_size"
                                    type="number"
                                    min="0"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Cleanup Interval (seconds)</label>
                                <input
                                    v-model="form.cleanup_interval"
                                    type="number"
                                    min="0"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Cache Prefix</label>
                                <input
                                    v-model="form.cache_prefix"
                                    type="text"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Cacheable Content</label>
                            <div class="space-y-2">
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input type="checkbox" v-model="form.cacheable_channels" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                                    <span class="text-gray-300">Channel Lists</span>
                                </label>
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input type="checkbox" v-model="form.cacheable_epg" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                                    <span class="text-gray-300">EPG Data</span>
                                </label>
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input type="checkbox" v-model="form.cacheable_m3u" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                                    <span class="text-gray-300">M3U</span>
                                </label>
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input type="checkbox" v-model="form.cacheable_api_responses" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                                    <span class="text-gray-300">API Responses</span>
                                </label>
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input type="checkbox" v-model="form.cacheable_vod" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                                    <span class="text-gray-300">VOD Metadata</span>
                                </label>
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input type="checkbox" v-model="form.cacheable_sessions" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                                    <span class="text-gray-300">Sessions</span>
                                </label>
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input type="checkbox" v-model="form.cacheable_config" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                                    <span class="text-gray-300">Config</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Cache Warming</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-white font-medium">Enable Cache Warming</p>
                                <p class="text-gray-400 text-sm">Pre-populate cache with frequently accessed data</p>
                            </div>
                            <button
                                type="button"
                                @click="form.enable_warming = !form.enable_warming"
                                :class="form.enable_warming ? 'bg-indigo-600' : 'bg-gray-600'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                            >
                                <span
                                    :class="form.enable_warming ? 'translate-x-6' : 'translate-x-1'"
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                                />
                            </button>
                        </div>

                        <template v-if="form.enable_warming">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-1">Warmup Schedule</label>
                                    <select
                                        v-model="form.warmup_schedule"
                                        class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                    >
                                        <option value="Hourly">Hourly</option>
                                        <option value="Daily">Daily</option>
                                        <option value="Weekly">Weekly</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-1">Warmup Items</label>
                                    <input
                                        v-model="form.warmup_items"
                                        type="number"
                                        min="1"
                                        class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                    />
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Priority</label>
                                <select
                                    v-model="form.warmup_priority"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                >
                                    <option value="Low">Low</option>
                                    <option value="Medium">Medium</option>
                                    <option value="High">High</option>
                                </select>
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-white font-medium">Prewarm on Start</p>
                                    <p class="text-gray-400 text-sm">Warm cache when server starts</p>
                                </div>
                                <button
                                    type="button"
                                    @click="form.prewarm_on_start = !form.prewarm_on_start"
                                    :class="form.prewarm_on_start ? 'bg-indigo-600' : 'bg-gray-600'"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                                >
                                    <span
                                        :class="form.prewarm_on_start ? 'translate-x-6' : 'translate-x-1'"
                                        class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                                    />
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button
                        type="button"
                        @click="clearCache"
                        class="px-6 py-2 bg-red-600 hover:bg-red-500 text-white rounded-lg transition"
                    >
                        Clear Cache
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
