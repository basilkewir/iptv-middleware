<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto space-y-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-white">User Management</h1>
        <p class="text-gray-400 mt-1">Configure registration, password, and session settings</p>
      </div>
      <form @submit.prevent="form.put(route('admin.settings.users.update'))" class="space-y-6">
        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Registration Settings</h3>
          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <label class="text-white font-medium">Enable Registration</label>
                <p class="text-gray-400 text-sm">Allow new users to register</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="form.enable_registration" class="sr-only peer" />
                <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
              </label>
            </div>
            <div>
              <label class="block text-white font-medium mb-2">Registration Type</label>
              <div class="space-y-2">
                <label v-for="type in ['Open', 'Invitation Only', 'Admin Approval', 'Closed']" :key="type" class="flex items-center space-x-3 cursor-pointer">
                  <input type="radio" v-model="form.registration_type" :value="type" class="w-4 h-4 text-indigo-500 bg-gray-700 border-gray-600 focus:ring-indigo-500" />
                  <span class="text-gray-300">{{ type }}</span>
                </label>
              </div>
            </div>
            <div>
              <label class="block text-white font-medium mb-2">Default Role</label>
              <select v-model="form.default_role" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500">
                <option value="Subscriber">Subscriber</option>
                <option value="Reseller">Reseller</option>
                <option value="Admin">Admin</option>
              </select>
            </div>
            <div>
              <label class="block text-white font-medium mb-2">Default Status</label>
              <select v-model="form.default_status" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500">
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
                <option value="Pending">Pending</option>
              </select>
            </div>
            <div class="flex items-center justify-between">
              <div>
                <label class="text-white font-medium">Require Email Verification</label>
                <p class="text-gray-400 text-sm">Users must verify their email</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="form.require_email_verification" class="sr-only peer" />
                <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
              </label>
            </div>
            <div class="flex items-center justify-between">
              <div>
                <label class="text-white font-medium">Require Phone Verification</label>
                <p class="text-gray-400 text-sm">Users must verify their phone</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="form.require_phone_verification" class="sr-only peer" />
                <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
              </label>
            </div>
            <div>
              <label class="block text-white font-medium mb-2">Registration Agreement</label>
              <textarea v-model="form.registration_agreement" rows="4" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" placeholder="Terms and conditions..."></textarea>
            </div>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Password Policy</h3>
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-white font-medium mb-2">Minimum Password Length</label>
                <input type="number" v-model="form.min_password_length" min="6" max="128" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-white font-medium mb-2">Maximum Password Length</label>
                <input type="number" v-model="form.max_password_length" min="32" max="256" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" />
              </div>
            </div>
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <label class="text-white">Require Uppercase Letters</label>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="form.require_uppercase" class="sr-only peer" />
                  <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
              </div>
              <div class="flex items-center justify-between">
                <label class="text-white">Require Lowercase Letters</label>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="form.require_lowercase" class="sr-only peer" />
                  <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
              </div>
              <div class="flex items-center justify-between">
                <label class="text-white">Require Numbers</label>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="form.require_numbers" class="sr-only peer" />
                  <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
              </div>
              <div class="flex items-center justify-between">
                <label class="text-white">Require Special Characters</label>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="form.require_special_chars" class="sr-only peer" />
                  <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
              </div>
              <div class="flex items-center justify-between">
                <label class="text-white">Disallow Common Passwords</label>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="form.disallow_common_passwords" class="sr-only peer" />
                  <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-white font-medium mb-2">Password Expiry Days</label>
                <input type="number" v-model="form.password_expiry_days" min="0" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" />
                <p class="text-gray-400 text-sm mt-1">0 = Never expires</p>
              </div>
              <div>
                <label class="block text-white font-medium mb-2">Password History Count</label>
                <input type="number" v-model="form.password_history_count" min="0" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" />
                <p class="text-gray-400 text-sm mt-1">0 = No history tracking</p>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
          <h3 class="text-lg font-semibold text-white mb-4">Session Management</h3>
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-white font-medium mb-2">Session Lifetime (minutes)</label>
                <input type="number" v-model="form.session_lifetime" min="5" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" />
              </div>
              <div>
                <label class="block text-white font-medium mb-2">Idle Timeout (minutes)</label>
                <input type="number" v-model="form.session_idle_timeout" min="1" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" />
              </div>
            </div>
            <div>
              <label class="block text-white font-medium mb-2">Max Concurrent Sessions</label>
              <input type="number" v-model="form.max_concurrent_sessions" min="1" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" />
            </div>
            <div class="flex items-center justify-between">
              <div>
                <label class="text-white font-medium">Force Logout on Password Change</label>
                <p class="text-gray-400 text-sm">Invalidate all sessions when password changes</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="form.force_logout_on_password_change" class="sr-only peer" />
                <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
              </label>
            </div>
            <div>
              <label class="block text-white font-medium mb-2">Remember Me Duration (minutes)</label>
              <input type="number" v-model="form.remember_me_duration" min="0" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-indigo-500" />
            </div>
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <label class="text-white">Session Cookie Secure</label>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="form.session_cookie_secure" class="sr-only peer" />
                  <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
              </div>
              <div class="flex items-center justify-between">
                <label class="text-white">Session Cookie HttpOnly</label>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="form.session_cookie_httponly" class="sr-only peer" />
                  <div class="w-11 h-6 bg-gray-600 peer-focus:ring-2 peer-focus:ring-indigo-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
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
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'

const props = defineProps({ settings: Object })

const form = useForm({
  enable_registration: props.settings.enable_registration ?? true,
  registration_type: props.settings.registration_type ?? 'Open',
  default_role: props.settings.default_role ?? 'Subscriber',
  default_status: props.settings.default_status ?? 'Active',
  require_email_verification: props.settings.require_email_verification ?? true,
  require_phone_verification: props.settings.require_phone_verification ?? false,
  registration_agreement: props.settings.registration_agreement ?? '',
  min_password_length: props.settings.min_password_length ?? 8,
  max_password_length: props.settings.max_password_length ?? 50,
  require_uppercase: props.settings.require_uppercase ?? true,
  require_lowercase: props.settings.require_lowercase ?? true,
  require_numbers: props.settings.require_numbers ?? true,
  require_special_chars: props.settings.require_special_chars ?? true,
  disallow_common_passwords: props.settings.disallow_common_passwords ?? true,
  password_expiry_days: props.settings.password_expiry_days ?? 90,
  password_history_count: props.settings.password_history_count ?? 5,
  session_lifetime: props.settings.session_lifetime ?? 1440,
  session_idle_timeout: props.settings.session_idle_timeout ?? 30,
  max_concurrent_sessions: props.settings.max_concurrent_sessions ?? 5,
  force_logout_on_password_change: props.settings.force_logout_on_password_change ?? true,
  remember_me_duration: props.settings.remember_me_duration ?? 43200,
  session_cookie_secure: props.settings.session_cookie_secure ?? true,
  session_cookie_httponly: props.settings.session_cookie_httponly ?? true,
})
</script>
