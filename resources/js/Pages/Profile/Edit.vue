<template>
  <AppLayout title="Edit Profile">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Edit Profile
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8">
          <form @submit.prevent="submit">
            <div class="mb-8">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                Profile Picture
              </h3>
              <div class="flex items-center space-x-6">
                <img
                  :src="avatarPreview || form.avatar || defaultAvatar"
                  alt="Avatar"
                  class="w-24 h-24 rounded-full object-cover"
                />
                <div>
                  <label
                    for="avatar"
                    class="cursor-pointer px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors inline-block"
                  >
                    Change Avatar
                  </label>
                  <input
                    id="avatar"
                    type="file"
                    accept="image/*"
                    class="hidden"
                    @change="handleAvatarChange"
                  />
                  <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    JPG, PNG or GIF. Max 2MB.
                  </p>
                </div>
              </div>
              <div v-if="form.errors.avatar" class="mt-2 text-sm text-red-600 dark:text-red-400">
                {{ form.errors.avatar }}
              </div>
            </div>

            <div class="mb-8">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                Personal Information
              </h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Full Name
                  </label>
                  <input
                    v-model="form.name"
                    type="text"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500"
                  />
                  <div v-if="form.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.name }}
                  </div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Email
                  </label>
                  <input
                    v-model="form.email"
                    type="email"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500"
                  />
                  <div v-if="form.errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.email }}
                  </div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Phone Number
                  </label>
                  <input
                    v-model="form.phone"
                    type="tel"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Country
                  </label>
                  <select
                    v-model="form.country"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500"
                  >
                    <option value="">Select country</option>
                    <option value="US">United States</option>
                    <option value="UK">United Kingdom</option>
                    <option value="CA">Canada</option>
                    <option value="AU">Australia</option>
                  </select>
                </div>
              </div>
            </div>

            <div class="mb-8">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                Change Password
              </h3>
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Current Password
                  </label>
                  <input
                    v-model="form.current_password"
                    type="password"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500"
                  />
                  <div v-if="form.errors.current_password" class="mt-1 text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.current_password }}
                  </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                      New Password
                    </label>
                    <input
                      v-model="form.password"
                      type="password"
                      class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <div v-if="form.errors.password" class="mt-1 text-sm text-red-600 dark:text-red-400">
                      {{ form.errors.password }}
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                      Confirm Password
                    </label>
                    <input
                      v-model="form.password_confirmation"
                      type="password"
                      class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500"
                    />
                  </div>
                </div>
              </div>
            </div>

            <div class="mb-8">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                Notification Preferences
              </h3>
              <div class="space-y-4">
                <label class="flex items-center justify-between">
                  <div>
                    <p class="font-medium text-gray-900 dark:text-gray-100">Email Notifications</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                      Receive updates about your subscription
                    </p>
                  </div>
                  <button
                    type="button"
                    @click="form.email_notifications = !form.email_notifications"
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                    :class="form.email_notifications ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700'"
                  >
                    <span
                      class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                      :class="form.email_notifications ? 'translate-x-6' : 'translate-x-1'"
                    />
                  </button>
                </label>
                <label class="flex items-center justify-between">
                  <div>
                    <p class="font-medium text-gray-900 dark:text-gray-100">SMS Notifications</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                      Get text messages for important alerts
                    </p>
                  </div>
                  <button
                    type="button"
                    @click="form.sms_notifications = !form.sms_notifications"
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                    :class="form.sms_notifications ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700'"
                  >
                    <span
                      class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                      :class="form.sms_notifications ? 'translate-x-6' : 'translate-x-1'"
                    />
                  </button>
                </label>
                <label class="flex items-center justify-between">
                  <div>
                    <p class="font-medium text-gray-900 dark:text-gray-100">Marketing Emails</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                      Receive promotional offers and news
                    </p>
                  </div>
                  <button
                    type="button"
                    @click="form.marketing_emails = !form.marketing_emails"
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                    :class="form.marketing_emails ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700'"
                  >
                    <span
                      class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                      :class="form.marketing_emails ? 'translate-x-6' : 'translate-x-1'"
                    />
                  </button>
                </label>
              </div>
            </div>

            <div class="flex justify-end space-x-4">
              <button
                type="button"
                @click="$inertia.visit(route('profile.show'))"
                class="px-6 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg font-semibold hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="form.processing"
                class="px-6 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                <span v-if="form.processing">Saving...</span>
                <span v-else>Save Changes</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { route } from '@/Composables/useRoute';

const props = defineProps({
  user: Object,
});

const defaultAvatar = 'https://ui-avatars.com/api/?name=User&background=6366f1&color=fff';
const avatarPreview = ref(null);

const form = useForm({
  name: props.user.name,
  email: props.user.email,
  phone: props.user.phone || '',
  country: props.user.country || '',
  avatar: null,
  current_password: '',
  password: '',
  password_confirmation: '',
  email_notifications: props.user.email_notifications ?? true,
  sms_notifications: props.user.sms_notifications ?? false,
  marketing_emails: props.user.marketing_emails ?? false,
});

const handleAvatarChange = (event) => {
  const file = event.target.files[0];
  if (file) {
    form.avatar = file;
    const reader = new FileReader();
    reader.onload = (e) => {
      avatarPreview.value = e.target.result;
    };
    reader.readAsDataURL(file);
  }
};

const submit = () => {
  form.post(route('profile.update'), {
    forceFormData: true,
  });
};
</script>
