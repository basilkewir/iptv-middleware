<template>
  <AppLayout title="Settings">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Settings
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="space-y-6">
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-6">
              Language
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div
                v-for="language in languages"
                :key="language.code"
                @click="form.language = language.code"
                class="p-4 border-2 rounded-lg cursor-pointer transition-colors"
                :class="
                  form.language === language.code
                    ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20'
                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
                "
              >
                <div class="flex items-center space-x-3">
                  <span class="text-2xl">{{ language.flag }}</span>
                  <span class="font-medium text-gray-900 dark:text-gray-100">
                    {{ language.name }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-6">
              Timezone
            </h3>
            <select
              v-model="form.timezone"
              class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500"
            >
              <option value="">Select timezone</option>
              <option v-for="tz in timezones" :key="tz" :value="tz">
                {{ tz.replace(/_/g, ' ') }}
              </option>
            </select>
          </div>

          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-6">
              Parental Controls
            </h3>
            <div class="space-y-4">
              <label class="flex items-center justify-between">
                <div>
                  <p class="font-medium text-gray-900 dark:text-gray-100">Enable Parental Controls</p>
                  <p class="text-sm text-gray-500 dark:text-gray-400">
                    Restrict access to age-inappropriate content
                  </p>
                </div>
                <button
                  type="button"
                  @click="form.parental_controls = !form.parental_controls"
                  class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                  :class="form.parental_controls ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700'"
                >
                  <span
                    class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                    :class="form.parental_controls ? 'translate-x-6' : 'translate-x-1'"
                  />
                </button>
              </label>
              <div v-if="form.parental_controls">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Content Rating
                </label>
                <select
                  v-model="form.content_rating"
                  class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500"
                >
                  <option value="G">G - General Audiences</option>
                  <option value="PG">PG - Parental Guidance</option>
                  <option value="PG-13">PG-13 - Parents Strongly Cautioned</option>
                  <option value="R">R - Restricted</option>
                </select>
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-6">
              PIN Code
            </h3>
            <div class="space-y-4">
              <label class="flex items-center justify-between">
                <div>
                  <p class="font-medium text-gray-900 dark:text-gray-100">Require PIN for Purchases</p>
                  <p class="text-sm text-gray-500 dark:text-gray-400">
                    Protect your account from unauthorized purchases
                  </p>
                </div>
                <button
                  type="button"
                  @click="form.pin_enabled = !form.pin_enabled"
                  class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                  :class="form.pin_enabled ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700'"
                >
                  <span
                    class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                    :class="form.pin_enabled ? 'translate-x-6' : 'translate-x-1'"
                  />
                </button>
              </label>
              <div v-if="form.pin_enabled" class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Set PIN
                  </label>
                  <input
                    v-model="form.pin"
                    type="password"
                    maxlength="4"
                    pattern="[0-9]*"
                    inputmode="numeric"
                    placeholder="4-digit PIN"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Confirm PIN
                  </label>
                  <input
                    v-model="form.pin_confirmation"
                    type="password"
                    maxlength="4"
                    pattern="[0-9]*"
                    inputmode="numeric"
                    placeholder="Confirm PIN"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500"
                  />
                </div>
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-6">
              Streaming Quality
            </h3>
            <div class="space-y-4">
              <label
                v-for="quality in qualities"
                :key="quality.value"
                class="flex items-center p-4 border-2 rounded-lg cursor-pointer transition-colors"
                :class="
                  form.streaming_quality === quality.value
                    ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20'
                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
                "
              >
                <input
                  v-model="form.streaming_quality"
                  type="radio"
                  :value="quality.value"
                  class="text-indigo-600 focus:ring-indigo-500"
                />
                <div class="ml-3">
                  <p class="font-medium text-gray-900 dark:text-gray-100">
                    {{ quality.label }}
                  </p>
                  <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ quality.description }}
                  </p>
                </div>
              </label>
            </div>
          </div>

          <div class="flex justify-end space-x-4">
            <button
              @click="$inertia.visit(route('profile.show'))"
              class="px-6 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg font-semibold hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
            >
              Cancel
            </button>
            <button
              @click="saveSettings"
              :disabled="saving"
              class="px-6 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              <span v-if="saving">Saving...</span>
              <span v-else>Save Settings</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { route } from '@/Composables/useRoute';

const props = defineProps({
  settings: Object,
});

const saving = ref(false);

const form = reactive({
  language: props.settings?.language || 'en',
  timezone: props.settings?.timezone || 'UTC',
  parental_controls: props.settings?.parental_controls || false,
  content_rating: props.settings?.content_rating || 'PG-13',
  pin_enabled: props.settings?.pin_enabled || false,
  pin: '',
  pin_confirmation: '',
  streaming_quality: props.settings?.streaming_quality || 'auto',
});

const languages = [
  { code: 'en', name: 'English', flag: '🇺🇸' },
  { code: 'es', name: 'Español', flag: '🇪🇸' },
  { code: 'fr', name: 'Français', flag: '🇫🇷' },
  { code: 'de', name: 'Deutsch', flag: '🇩🇪' },
  { code: 'ar', name: 'العربية', flag: '🇸🇦' },
  { code: 'pt', name: 'Português', flag: '🇧🇷' },
];

const timezones = [
  'UTC',
  'America/New_York',
  'America/Chicago',
  'America/Denver',
  'America/Los_Angeles',
  'Europe/London',
  'Europe/Paris',
  'Asia/Dubai',
  'Asia/Tokyo',
];

const qualities = [
  { value: 'auto', label: 'Auto', description: 'Best quality based on connection speed' },
  { value: '4k', label: '4K Ultra HD', description: 'Requires 25+ Mbps' },
  { value: '1080p', label: 'Full HD (1080p)', description: 'Requires 10+ Mbps' },
  { value: '720p', label: 'HD (720p)', description: 'Requires 5+ Mbps' },
  { value: '480p', label: 'SD (480p)', description: 'Best for slow connections' },
];

const saveSettings = () => {
  saving.value = true;
  router.post(route('profile.settings.update'), form, {
    onFinish: () => {
      saving.value = false;
    },
  });
};
</script>
