<script setup>
import { useForm } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Database, FileCode, Gauge } from 'lucide-vue-next'

const props = defineProps({ settings: Object })

const form = useForm({
    enable_query_cache: props.settings.enable_query_cache ?? false,
    query_cache_ttl: props.settings.query_cache_ttl ?? 300,
    enable_slow_query_log: props.settings.enable_slow_query_log ?? false,
    slow_query_threshold: props.settings.slow_query_threshold ?? 2,
    enable_query_logging: props.settings.enable_query_logging ?? false,
    query_log_path: props.settings.query_log_path ?? '',

    enable_gzip: props.settings.enable_gzip ?? true,
    compression_level: props.settings.compression_level ?? 6,
    compressable_html: props.settings.compressable_html ?? true,
    compressable_css: props.settings.compressable_css ?? true,
    compressable_js: props.settings.compressable_js ?? true,
    compressable_json: props.settings.compressable_json ?? true,
    compressable_xml: props.settings.compressable_xml ?? true,
    compressable_m3u: props.settings.compressable_m3u ?? true,
    compressable_api: props.settings.compressable_api ?? true,
    enable_image_optimization: props.settings.enable_image_optimization ?? false,
    image_quality: props.settings.image_quality ?? 85,
    enable_minification: props.settings.enable_minification ?? false,

    max_execution_time: props.settings.max_execution_time ?? 120,
    memory_limit: props.settings.memory_limit ?? 256,
    upload_max_size: props.settings.upload_max_size ?? 2048,
    post_max_size: props.settings.post_max_size ?? 2048,
    max_concurrent_requests: props.settings.max_concurrent_requests ?? 100,
    max_connections: props.settings.max_connections ?? 1000,
})

const compressionLabels = {
    1: '1 - Fastest',
    2: '2',
    3: '3',
    4: '4',
    5: '5 - Balanced',
    6: '6',
    7: '7',
    8: '8',
    9: '9 - Best',
}
</script>

<template>
    <AdminLayout>
        <div class="p-6 max-w-4xl mx-auto space-y-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-white">Performance Settings</h1>
                <p class="text-gray-400 mt-1">Optimize database queries, compression, and resource limits</p>
            </div>

            <form @submit.prevent="form.put(route('admin.settings.performance.update'))" class="space-y-6">
                <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Database</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-white font-medium">Enable Query Cache</p>
                                <p class="text-gray-400 text-sm">Cache database query results</p>
                            </div>
                            <button
                                type="button"
                                @click="form.enable_query_cache = !form.enable_query_cache"
                                :class="form.enable_query_cache ? 'bg-indigo-600' : 'bg-gray-600'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                            >
                                <span
                                    :class="form.enable_query_cache ? 'translate-x-6' : 'translate-x-1'"
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                                />
                            </button>
                        </div>

                        <div v-if="form.enable_query_cache">
                            <label class="block text-sm font-medium text-gray-300 mb-1">Query Cache TTL (seconds)</label>
                            <input
                                v-model="form.query_cache_ttl"
                                type="number"
                                min="0"
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                            />
                        </div>

                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-white font-medium">Enable Slow Query Log</p>
                                <p class="text-gray-400 text-sm">Log queries that exceed the threshold</p>
                            </div>
                            <button
                                type="button"
                                @click="form.enable_slow_query_log = !form.enable_slow_query_log"
                                :class="form.enable_slow_query_log ? 'bg-indigo-600' : 'bg-gray-600'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                            >
                                <span
                                    :class="form.enable_slow_query_log ? 'translate-x-6' : 'translate-x-1'"
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                                />
                            </button>
                        </div>

                        <div v-if="form.enable_slow_query_log">
                            <label class="block text-sm font-medium text-gray-300 mb-1">Slow Query Threshold (seconds)</label>
                            <input
                                v-model="form.slow_query_threshold"
                                type="number"
                                min="0"
                                step="0.1"
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                            />
                        </div>

                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-white font-medium">Enable Query Logging</p>
                                <p class="text-gray-400 text-sm">Log all database queries</p>
                            </div>
                            <button
                                type="button"
                                @click="form.enable_query_logging = !form.enable_query_logging"
                                :class="form.enable_query_logging ? 'bg-indigo-600' : 'bg-gray-600'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                            >
                                <span
                                    :class="form.enable_query_logging ? 'translate-x-6' : 'translate-x-1'"
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                                />
                            </button>
                        </div>

                        <div v-if="form.enable_query_logging">
                            <label class="block text-sm font-medium text-gray-300 mb-1">Query Log Path</label>
                            <input
                                v-model="form.query_log_path"
                                type="text"
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                placeholder="/var/log/queries.log"
                            />
                        </div>
                    </div>
                </div>

                <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Compression</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-white font-medium">Enable Gzip Compression</p>
                                <p class="text-gray-400 text-sm">Compress responses with Gzip</p>
                            </div>
                            <button
                                type="button"
                                @click="form.enable_gzip = !form.enable_gzip"
                                :class="form.enable_gzip ? 'bg-indigo-600' : 'bg-gray-600'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                            >
                                <span
                                    :class="form.enable_gzip ? 'translate-x-6' : 'translate-x-1'"
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                                />
                            </button>
                        </div>

                        <div v-if="form.enable_gzip">
                            <label class="block text-sm font-medium text-gray-300 mb-1">Compression Level</label>
                            <select
                                v-model="form.compression_level"
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                            >
                                <option v-for="(label, level) in compressionLabels" :key="level" :value="Number(level)">{{ label }}</option>
                            </select>
                        </div>

                        <div v-if="form.enable_gzip">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Compressable Content</label>
                            <div class="space-y-2">
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input type="checkbox" v-model="form.compressable_html" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                                    <span class="text-gray-300">HTML</span>
                                </label>
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input type="checkbox" v-model="form.compressable_css" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                                    <span class="text-gray-300">CSS</span>
                                </label>
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input type="checkbox" v-model="form.compressable_js" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                                    <span class="text-gray-300">JavaScript</span>
                                </label>
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input type="checkbox" v-model="form.compressable_json" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                                    <span class="text-gray-300">JSON</span>
                                </label>
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input type="checkbox" v-model="form.compressable_xml" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                                    <span class="text-gray-300">XML</span>
                                </label>
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input type="checkbox" v-model="form.compressable_m3u" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                                    <span class="text-gray-300">M3U</span>
                                </label>
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input type="checkbox" v-model="form.compressable_api" class="w-4 h-4 text-indigo-600 bg-gray-700 border-gray-600 rounded focus:ring-indigo-500" />
                                    <span class="text-gray-300">API Responses</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-white font-medium">Enable Image Optimization</p>
                                <p class="text-gray-400 text-sm">Automatically optimize uploaded images</p>
                            </div>
                            <button
                                type="button"
                                @click="form.enable_image_optimization = !form.enable_image_optimization"
                                :class="form.enable_image_optimization ? 'bg-indigo-600' : 'bg-gray-600'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                            >
                                <span
                                    :class="form.enable_image_optimization ? 'translate-x-6' : 'translate-x-1'"
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                                />
                            </button>
                        </div>

                        <div v-if="form.enable_image_optimization">
                            <label class="block text-sm font-medium text-gray-300 mb-1">Image Quality (%)</label>
                            <input
                                v-model="form.image_quality"
                                type="number"
                                min="1"
                                max="100"
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                            />
                        </div>

                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-white font-medium">Enable Minification</p>
                                <p class="text-gray-400 text-sm">Minify HTML, CSS, and JavaScript output</p>
                            </div>
                            <button
                                type="button"
                                @click="form.enable_minification = !form.enable_minification"
                                :class="form.enable_minification ? 'bg-indigo-600' : 'bg-gray-600'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                            >
                                <span
                                    :class="form.enable_minification ? 'translate-x-6' : 'translate-x-1'"
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                                />
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                    <h3 class="text-lg font-semibold text-white mb-4">Resource Limits</h3>
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Max Execution Time (seconds)</label>
                                <input
                                    v-model="form.max_execution_time"
                                    type="number"
                                    min="0"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Memory Limit (MB)</label>
                                <input
                                    v-model="form.memory_limit"
                                    type="number"
                                    min="32"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Upload Max Size (MB)</label>
                                <input
                                    v-model="form.upload_max_size"
                                    type="number"
                                    min="1"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">POST Max Size (MB)</label>
                                <input
                                    v-model="form.post_max_size"
                                    type="number"
                                    min="1"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Max Concurrent Requests</label>
                                <input
                                    v-model="form.max_concurrent_requests"
                                    type="number"
                                    min="1"
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500 outline-none"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">Max Connections</label>
                                <input
                                    v-model="form.max_connections"
                                    type="number"
                                    min="1"
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
