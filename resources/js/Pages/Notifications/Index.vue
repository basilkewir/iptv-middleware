<template>
  <AppLayout title="Notifications">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Notifications
      </h2>
    </template>

    <div class="py-4 sm:py-8 lg:py-12">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
          <div class="p-4 sm:p-6 border-b border-gray-800">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
              <div class="flex items-center gap-2 sm:gap-4 overflow-x-auto pb-1 scrollbar-hide">
                <button
                  v-for="filter in filters"
                  :key="filter.value"
                  @click="activeFilter = filter.value"
                  class="px-3 sm:px-4 py-2 text-sm rounded-lg transition-colors whitespace-nowrap tv-focusable tv-touch-target flex-shrink-0"
                  :class="
                    activeFilter === filter.value
                      ? 'bg-purple-600 text-white'
                      : 'bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-white'
                  "
                >
                  {{ filter.label }}
                  <span
                    v-if="filter.count > 0"
                    class="ml-1 px-1.5 py-0.5 text-xs rounded-full"
                    :class="
                      activeFilter === filter.value
                        ? 'bg-purple-500 text-white'
                        : 'bg-gray-700 text-gray-400'
                    "
                  >
                    {{ filter.count }}
                  </span>
                </button>
              </div>
              <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                <button
                  v-if="unreadCount > 0"
                  @click="markAllAsRead"
                  class="px-3 sm:px-4 py-2 text-sm text-purple-400 hover:text-purple-300 transition-colors tv-focusable tv-touch-target"
                >
                  Mark all as read
                </button>
                <button
                  v-if="selectedNotifications.length > 0"
                  @click="deleteSelected"
                  class="px-3 sm:px-4 py-2 text-sm text-red-400 hover:text-red-300 transition-colors tv-focusable tv-touch-target"
                >
                  Delete ({{ selectedNotifications.length }})
                </button>
              </div>
            </div>
          </div>

          <div class="divide-y divide-gray-800">
            <div
              v-for="notification in filteredNotifications"
              :key="notification.id"
              class="p-4 sm:p-6 transition-colors"
              :class="{
                'bg-purple-600/5': !notification.read,
              }"
            >
              <div class="flex items-start gap-3 sm:gap-4">
                <input
                  type="checkbox"
                  :value="notification.id"
                  v-model="selectedNotifications"
                  class="mt-1 rounded border-gray-600 bg-gray-800 text-purple-600 focus:ring-purple-500 tv-focusable"
                />
                <div
                  class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                  :class="getTypeBg(notification.type)"
                >
                  <svg
                    class="w-5 h-5"
                    :class="getTypeIcon(notification.type)"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      :d="getTypePath(notification.type)"
                    />
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-1 sm:gap-2">
                    <div class="min-w-0">
                      <p
                        class="font-medium text-white text-sm sm:text-base"
                        :class="{ 'font-bold': !notification.read }"
                      >
                        {{ notification.title }}
                      </p>
                      <p class="mt-1 text-xs sm:text-sm text-gray-400">
                        {{ notification.message }}
                      </p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                      <span
                        v-if="!notification.read"
                        class="w-2 h-2 bg-purple-500 rounded-full"
                      />
                      <span class="text-xs text-gray-500 whitespace-nowrap">
                        {{ formatTime(notification.created_at) }}
                      </span>
                    </div>
                  </div>
                  <div class="mt-2 flex items-center gap-3">
                    <button
                      v-if="!notification.read"
                      @click="markAsRead(notification)"
                      class="text-xs text-purple-400 hover:text-purple-300 tv-focusable tv-touch-target"
                    >
                      Mark as read
                    </button>
                    <button
                      @click="deleteNotification(notification)"
                      class="text-xs text-red-400 hover:text-red-300 tv-focusable tv-touch-target"
                    >
                      Delete
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div
              v-if="filteredNotifications.length === 0"
              class="p-8 sm:p-12 text-center"
            >
              <svg
                class="w-12 h-12 sm:w-16 sm:h-16 mx-auto text-gray-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
              </svg>
              <p class="mt-4 text-gray-500 text-sm sm:text-base">
                No notifications to display.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  notifications: Array,
});

const activeFilter = ref('all');
const selectedNotifications = ref([]);

const filters = computed(() => [
  { label: 'All', value: 'all', count: props.notifications.length },
  { label: 'Unread', value: 'unread', count: unreadCount.value },
  { label: 'Read', value: 'read', count: props.notifications.length - unreadCount.value },
]);

const unreadCount = computed(() => {
  return props.notifications.filter((n) => !n.read).length;
});

const filteredNotifications = computed(() => {
  if (activeFilter.value === 'all') return props.notifications;
  if (activeFilter.value === 'unread') return props.notifications.filter((n) => !n.read);
  return props.notifications.filter((n) => n.read);
});

const formatTime = (date) => {
  const now = new Date();
  const notificationDate = new Date(date);
  const diff = now - notificationDate;
  const minutes = Math.floor(diff / 60000);
  const hours = Math.floor(diff / 3600000);
  const days = Math.floor(diff / 86400000);

  if (minutes < 60) return `${minutes}m ago`;
  if (hours < 24) return `${hours}h ago`;
  if (days < 7) return `${days}d ago`;
  return notificationDate.toLocaleDateString();
};

const getTypeBg = (type) => {
  const bgs = {
    subscription: 'bg-green-100 dark:bg-green-900/30',
    payment: 'bg-yellow-100 dark:bg-yellow-900/30',
    system: 'bg-blue-100 dark:bg-blue-900/30',
    promo: 'bg-purple-100 dark:bg-purple-900/30',
    security: 'bg-red-100 dark:bg-red-900/30',
  };
  return bgs[type] || bgs.system;
};

const getTypeIcon = (type) => {
  const icons = {
    subscription: 'text-green-600 dark:text-green-400',
    payment: 'text-yellow-600 dark:text-yellow-400',
    system: 'text-blue-600 dark:text-blue-400',
    promo: 'text-purple-600 dark:text-purple-400',
    security: 'text-red-600 dark:text-red-400',
  };
  return icons[type] || icons.system;
};

const getTypePath = (type) => {
  const paths = {
    subscription: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    payment: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    system: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    promo: 'M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7',
    security: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
  };
  return paths[type] || paths.system;
};

const markAsRead = (notification) => {
  router.post(route('notifications.read', notification.id));
};

const markAllAsRead = () => {
  router.post(route('notifications.readAll'));
};

const deleteNotification = (notification) => {
  router.delete(route('notifications.destroy', notification.id));
};

const deleteSelected = () => {
  if (confirm(`Delete ${selectedNotifications.value.length} notification(s)?`)) {
    router.post(route('notifications.bulkDelete'), {
      ids: selectedNotifications.value,
    });
    selectedNotifications.value = [];
  }
};
</script>
