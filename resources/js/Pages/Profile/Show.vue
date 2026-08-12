<template>
  <AppLayout title="Profile">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Profile
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
          <div class="h-32 bg-gradient-to-r from-indigo-500 to-purple-600" />
          <div class="px-8 pb-8">
            <div class="flex items-end -mt-16">
              <div class="relative">
                <img
                  :src="user?.profile_photo_url || defaultAvatar"
                  :alt="user?.username || 'User'"
                  class="w-32 h-32 rounded-full border-4 border-white dark:border-gray-800 object-cover"
                />
                <button
                  @click="$inertia.visit(route('profile.edit'))"
                  class="absolute bottom-0 right-0 p-2 bg-indigo-600 text-white rounded-full hover:bg-indigo-700 transition-colors"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                  </svg>
                </button>
              </div>
              <div class="ml-6 mb-2">
                <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                  {{ [user?.first_name, user?.last_name].filter(Boolean).join(' ') || user?.username }}
                </h3>
                <p class="text-gray-500 dark:text-gray-400">
                  {{ user?.email }}
                </p>
              </div>
            </div>

            <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-6">
                <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                  Subscription Status
                </h4>
                <div v-if="subscription" class="space-y-3">
                  <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Plan</span>
                    <span class="font-medium text-gray-900 dark:text-gray-100">
                      {{ subscription.plan_name }}
                    </span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Status</span>
                    <span
                      class="px-2 py-1 text-xs font-semibold rounded-full"
                      :class="
                        subscription.status === 'active'
                          ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                          : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'
                      "
                    >
                      {{ subscription.status }}
                    </span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Expires</span>
                    <span class="font-medium text-gray-900 dark:text-gray-100">
                      {{ formatDate(subscription.end_date) }}
                    </span>
                  </div>
                </div>
                <div v-else>
                  <p class="text-gray-500 dark:text-gray-400">No active subscription</p>
                  <button
                    @click="$inertia.visit(route('subscription.plans'))"
                    class="mt-2 text-indigo-600 hover:text-indigo-700 text-sm font-medium"
                  >
                    Browse Plans
                  </button>
                </div>
              </div>

              <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-6">
                <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                  Account Details
                </h4>
                <div class="space-y-3">
                  <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Member Since</span>
                    <span class="font-medium text-gray-900 dark:text-gray-100">
                      {{ formatDate(user?.created_at) }}
                    </span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Last Login</span>
                    <span class="font-medium text-gray-900 dark:text-gray-100">
                      {{ user?.last_login_at ? formatDate(user.last_login_at) : 'N/A' }}
                    </span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Devices</span>
                    <span class="font-medium text-gray-900 dark:text-gray-100">
                      {{ deviceCount }} registered
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <div class="mt-8">
              <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                Recent Activity
              </h4>
              <div class="space-y-4">
                <div
                  v-for="activity in recentActivity"
                  :key="activity.id"
                  class="flex items-center space-x-4 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg"
                >
                  <div
                    class="w-10 h-10 rounded-full flex items-center justify-center"
                    :class="getActivityBg(activity.type)"
                  >
                    <svg
                      class="w-5 h-5"
                      :class="getActivityIcon(activity.type)"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        :d="getActivityPath(activity.type)"
                      />
                    </svg>
                  </div>
                  <div class="flex-1">
                    <p class="text-sm text-gray-900 dark:text-gray-100">
                      {{ activity.description }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                      {{ formatDate(activity.created_at) }}
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <div class="mt-8 flex gap-4">
              <button
                @click="$inertia.visit(route('profile.edit'))"
                class="flex-1 py-3 px-6 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-colors"
              >
                Edit Profile
              </button>
              <button
                @click="$inertia.visit(route('profile.settings'))"
                class="flex-1 py-3 px-6 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg font-semibold hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
              >
                Settings
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  user: Object,
  subscription: Object,
  recentActivity: Array,
  deviceCount: Number,
});

const defaultAvatar = 'https://ui-avatars.com/api/?name=User&background=6366f1&color=fff';

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const getActivityBg = (type) => {
  const bgs = {
    login: 'bg-blue-100 dark:bg-blue-900/30',
    subscription: 'bg-green-100 dark:bg-green-900/30',
    payment: 'bg-yellow-100 dark:bg-yellow-900/30',
    device: 'bg-purple-100 dark:bg-purple-900/30',
  };
  return bgs[type] || bgs.login;
};

const getActivityIcon = (type) => {
  const icons = {
    login: 'text-blue-600 dark:text-blue-400',
    subscription: 'text-green-600 dark:text-green-400',
    payment: 'text-yellow-600 dark:text-yellow-400',
    device: 'text-purple-600 dark:text-purple-400',
  };
  return icons[type] || icons.login;
};

const getActivityPath = (type) => {
  const paths = {
    login: 'M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1',
    subscription: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    payment: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    device: 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
  };
  return paths[type] || paths.login;
};
</script>
