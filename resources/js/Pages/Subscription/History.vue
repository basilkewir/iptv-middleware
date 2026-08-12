<template>
  <AppLayout title="Subscription History">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Subscription History
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
          <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                All Subscriptions
              </h3>
              <div class="flex gap-2">
                <button
                  v-for="filter in filters"
                  :key="filter.value"
                  @click="activeFilter = filter.value"
                  class="px-4 py-2 text-sm rounded-lg transition-colors"
                  :class="
                    activeFilter === filter.value
                      ? 'bg-indigo-600 text-white'
                      : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600'
                  "
                >
                  {{ filter.label }}
                </button>
              </div>
            </div>
          </div>

          <div class="divide-y divide-gray-200 dark:divide-gray-700">
            <div
              v-for="subscription in filteredSubscriptions"
              :key="subscription.id"
              class="p-6 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
            >
              <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                  <div
                    class="w-12 h-12 rounded-full flex items-center justify-center"
                    :class="getStatusBg(subscription.status)"
                  >
                    <span
                      class="text-lg"
                      :class="getStatusIcon(subscription.status)"
                    >
                      {{ getStatusEmoji(subscription.status) }}
                    </span>
                  </div>
                  <div>
                    <h4 class="font-semibold text-gray-900 dark:text-gray-100">
                      {{ subscription.plan_name }}
                    </h4>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                      {{ formatDate(subscription.start_date) }} - {{ formatDate(subscription.end_date) }}
                    </p>
                  </div>
                </div>

                <div class="flex items-center space-x-4">
                  <span
                    class="px-3 py-1 text-xs font-semibold rounded-full"
                    :class="getStatusClass(subscription.status)"
                  >
                    {{ subscription.status }}
                  </span>

                  <span class="text-gray-900 dark:text-gray-100 font-semibold">
                    ${{ subscription.amount }}
                  </span>

                  <div class="flex items-center space-x-2">
                    <a
                      :href="route('subscription.invoice', subscription.id)"
                      class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                      title="View Invoice"
                    >
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                      </svg>
                    </a>

                    <button
                      v-if="subscription.status === 'active'"
                      @click="cancelSubscription(subscription)"
                      class="p-2 text-red-400 hover:text-red-600 transition-colors"
                      title="Cancel"
                    >
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>

                    <button
                      v-if="subscription.status === 'expired' || subscription.status === 'cancelled'"
                      @click="renewSubscription(subscription)"
                      class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition-colors"
                    >
                      Renew
                    </button>

                    <button
                      v-if="subscription.status === 'active'"
                      @click="upgradeSubscription(subscription)"
                      class="px-4 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition-colors"
                    >
                      Upgrade
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div
              v-if="filteredSubscriptions.length === 0"
              class="p-12 text-center"
            >
              <svg
                class="w-16 h-16 mx-auto text-gray-400 dark:text-gray-500"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
              <p class="mt-4 text-gray-500 dark:text-gray-400">
                No subscriptions found.
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
import { route } from '@/Composables/useRoute';

const props = defineProps({
  subscriptions: Array,
});

const activeFilter = ref('all');

const filters = [
  { label: 'All', value: 'all' },
  { label: 'Active', value: 'active' },
  { label: 'Expired', value: 'expired' },
  { label: 'Cancelled', value: 'cancelled' },
];

const filteredSubscriptions = computed(() => {
  if (activeFilter.value === 'all') return props.subscriptions;
  return props.subscriptions.filter((s) => s.status === activeFilter.value);
});

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const getStatusClass = (status) => {
  const classes = {
    active: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    expired: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
  };
  return classes[status] || classes.active;
};

const getStatusBg = (status) => {
  const bgs = {
    active: 'bg-green-100 dark:bg-green-900/30',
    expired: 'bg-yellow-100 dark:bg-yellow-900/30',
    cancelled: 'bg-red-100 dark:bg-red-900/30',
  };
  return bgs[status] || bgs.active;
};

const getStatusEmoji = (status) => {
  const emojis = { active: '✓', expired: '⏰', cancelled: '✕' };
  return emojis[status] || '✓';
};

const getStatusIcon = (status) => {
  const icons = {
    active: 'text-green-600 dark:text-green-400',
    expired: 'text-yellow-600 dark:text-yellow-400',
    cancelled: 'text-red-600 dark:text-red-400',
  };
  return icons[status] || icons.active;
};

const cancelSubscription = (subscription) => {
  if (confirm('Are you sure you want to cancel this subscription?')) {
    router.delete(route('subscription.cancel', subscription.id));
  }
};

const renewSubscription = (subscription) => {
  router.post(route('subscription.renew', subscription.id));
};

const upgradeSubscription = (subscription) => {
  router.get(route('subscription.plans'));
};
</script>
