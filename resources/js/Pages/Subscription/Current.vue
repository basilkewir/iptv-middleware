<template>
  <AppLayout title="Current Subscription">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Current Subscription
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div v-if="subscription" class="space-y-6">
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
            <div
              class="h-2"
              :class="subscription.status === 'active' ? 'bg-green-500' : 'bg-yellow-500'"
            />
            <div class="p-8">
              <div class="flex items-start justify-between">
                <div>
                  <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ subscription.plan_name }}
                  </h3>
                  <p class="mt-1 text-gray-500 dark:text-gray-400">
                    {{ subscription.connections }} connection{{ subscription.connections > 1 ? 's' : '' }}
                  </p>
                </div>
                <span
                  class="px-4 py-2 text-sm font-semibold rounded-full"
                  :class="
                    subscription.status === 'active'
                      ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                      : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'
                  "
                >
                  {{ subscription.status }}
                </span>
              </div>

              <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                  <p class="text-sm text-gray-500 dark:text-gray-400">Price</p>
                  <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    ${{ subscription.price }}
                  </p>
                  <p class="text-xs text-gray-400">/month</p>
                </div>
                <div class="text-center p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                  <p class="text-sm text-gray-500 dark:text-gray-400">Connections</p>
                  <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ subscription.connections }}
                  </p>
                  <p class="text-xs text-gray-400">devices</p>
                </div>
                <div class="text-center p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                  <p class="text-sm text-gray-500 dark:text-gray-400">Quality</p>
                  <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ subscription.quality }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8">
            <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-6">
              Expiry Countdown
            </h4>
            <div class="flex items-center justify-center space-x-8">
              <div class="text-center">
                <div
                  class="w-20 h-20 rounded-full flex items-center justify-center bg-indigo-100 dark:bg-indigo-900/30"
                >
                  <span class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">
                    {{ daysRemaining }}
                  </span>
                </div>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Days</p>
              </div>
              <div class="text-center">
                <div
                  class="w-20 h-20 rounded-full flex items-center justify-center bg-indigo-100 dark:bg-indigo-900/30"
                >
                  <span class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">
                    {{ hoursRemaining }}
                  </span>
                </div>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Hours</p>
              </div>
              <div class="text-center">
                <div
                  class="w-20 h-20 rounded-full flex items-center justify-center bg-indigo-100 dark:bg-indigo-900/30"
                >
                  <span class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">
                    {{ minutesRemaining }}
                  </span>
                </div>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Minutes</p>
              </div>
            </div>
            <p class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
              Expires on {{ formatDate(subscription.end_date) }}
            </p>
          </div>

          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8">
            <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-6">
              Active Connections
            </h4>
            <div class="space-y-4">
              <div
                v-for="connection in activeConnections"
                :key="connection.id"
                class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg"
              >
                <div class="flex items-center space-x-4">
                  <div class="w-10 h-10 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center">
                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                  </div>
                  <div>
                    <p class="font-medium text-gray-900 dark:text-gray-100">
                      {{ connection.device_name }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                      {{ connection.ip_address }} • Last active: {{ connection.last_active }}
                    </p>
                  </div>
                </div>
                <button
                  @click="disconnectDevice(connection)"
                  class="text-red-500 hover:text-red-600 text-sm"
                >
                  Disconnect
                </button>
              </div>
            </div>
          </div>

          <div class="flex gap-4">
            <button
              @click="renewSubscription"
              class="flex-1 py-3 px-6 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-colors"
            >
              Renew Subscription
            </button>
            <button
              @click="upgradeSubscription"
              class="flex-1 py-3 px-6 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700 transition-colors"
            >
              Upgrade Plan
            </button>
          </div>
        </div>

        <div v-else class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-12 text-center">
          <svg
            class="w-20 h-20 mx-auto text-gray-400 dark:text-gray-500"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
          </svg>
          <h3 class="mt-6 text-xl font-semibold text-gray-900 dark:text-gray-100">
            No Active Subscription
          </h3>
          <p class="mt-2 text-gray-500 dark:text-gray-400">
            You don't have an active subscription. Choose a plan to get started.
          </p>
          <button
            @click="$inertia.visit(route('subscription.plans'))"
            class="mt-6 px-8 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-colors"
          >
            Browse Plans
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { route } from '@/Composables/useRoute';

const props = defineProps({
  subscription: Object,
  activeConnections: Array,
});

const daysRemaining = ref(0);
const hoursRemaining = ref(0);
const minutesRemaining = ref(0);
let countdownInterval = null;

const updateCountdown = () => {
  if (!props.subscription?.end_date) return;
  const end = new Date(props.subscription.end_date);
  const now = new Date();
  const diff = end - now;

  if (diff <= 0) {
    daysRemaining.value = 0;
    hoursRemaining.value = 0;
    minutesRemaining.value = 0;
    return;
  }

  daysRemaining.value = Math.floor(diff / (1000 * 60 * 60 * 24));
  hoursRemaining.value = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
  minutesRemaining.value = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
};

onMounted(() => {
  updateCountdown();
  countdownInterval = setInterval(updateCountdown, 60000);
});

onUnmounted(() => {
  if (countdownInterval) clearInterval(countdownInterval);
});

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
};

const disconnectDevice = (connection) => {
  if (confirm('Disconnect this device?')) {
    router.delete(route('subscription.disconnect', connection.id));
  }
};

const renewSubscription = () => {
  router.post(route('subscription.renew', props.subscription.id));
};

const upgradeSubscription = () => {
  router.get(route('subscription.plans'));
};
</script>
