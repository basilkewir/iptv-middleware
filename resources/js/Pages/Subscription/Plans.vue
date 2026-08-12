<template>
  <AppLayout title="Subscription Plans">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Subscription Plans
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="mb-8 text-center">
          <p class="text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
            Choose the perfect plan for your streaming needs. All plans include access to live TV, movies, and series.
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <div
            v-for="plan in plans"
            :key="plan.id"
            class="relative rounded-2xl border-2 p-8 transition-all duration-300 hover:scale-105"
            :class="
              plan.popular
                ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 shadow-xl shadow-indigo-500/20'
                : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800'
            "
          >
            <div
              v-if="plan.popular"
              class="absolute -top-4 left-1/2 -translate-x-1/2 bg-indigo-600 text-white text-xs font-bold px-4 py-1 rounded-full"
            >
              Most Popular
            </div>

            <div class="text-center mb-6">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                {{ plan.name }}
              </h3>
              <div class="mt-4">
                <span class="text-4xl font-bold text-gray-900 dark:text-gray-100">
                  ${{ plan.price }}
                </span>
                <span class="text-gray-500 dark:text-gray-400">
                  /{{ plan.period }}
                </span>
              </div>
            </div>

            <ul class="space-y-3 mb-8">
              <li
                v-for="feature in plan.features"
                :key="feature"
                class="flex items-center text-sm text-gray-600 dark:text-gray-400"
              >
                <svg
                  class="w-5 h-5 text-green-500 mr-3 flex-shrink-0"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5 13l4 4L19 7"
                  />
                </svg>
                {{ feature }}
              </li>
            </ul>

            <button
              @click="selectPlan(plan)"
              class="w-full py-3 px-6 rounded-lg font-semibold transition-colors duration-200"
              :class="
                plan.popular
                  ? 'bg-indigo-600 text-white hover:bg-indigo-700'
                  : 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100 hover:bg-gray-200 dark:hover:bg-gray-600'
              "
            >
              Select Plan
            </button>
          </div>
        </div>

        <div class="mt-16">
          <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-6 text-center">
            Feature Comparison
          </h3>
          <div class="overflow-x-auto">
            <table class="w-full bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
              <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                  <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">
                    Feature
                  </th>
                  <th
                    v-for="plan in plans"
                    :key="plan.id"
                    class="px-6 py-4 text-center text-sm font-semibold text-gray-900 dark:text-gray-100"
                  >
                    {{ plan.name }}
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <tr
                  v-for="feature in comparisonFeatures"
                  :key="feature.name"
                  class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                >
                  <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                    {{ feature.name }}
                  </td>
                  <td
                    v-for="plan in plans"
                    :key="plan.id"
                    class="px-6 py-4 text-center"
                  >
                    <span
                      v-if="feature.included[plan.id]"
                      class="text-green-500 text-lg"
                    >
                      &#10003;
                    </span>
                    <span v-else class="text-gray-400 text-lg">
                      &#10007;
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { route } from '@/Composables/useRoute';

const plans = [
  {
    id: 'basic',
    name: 'Basic',
    price: 9.99,
    period: 'month',
    popular: false,
    features: [
      '1 Connection',
      '1,000+ Live Channels',
      'On-Demand Library',
      'SD & HD Quality',
      'Email Support',
    ],
  },
  {
    id: 'standard',
    name: 'Standard',
    price: 19.99,
    period: 'month',
    popular: true,
    features: [
      '2 Connections',
      '2,000+ Live Channels',
      'Full On-Demand Library',
      'HD & FHD Quality',
      'Priority Support',
      'EPG Guide',
      'Catch-Up TV',
    ],
  },
  {
    id: 'premium',
    name: 'Premium',
    price: 29.99,
    period: 'month',
    popular: false,
    features: [
      '4 Connections',
      '3,000+ Live Channels',
      'Full On-Demand Library',
      '4K Quality',
      '24/7 VIP Support',
      'EPG Guide',
      'Catch-Up TV',
      'Multi-Device Access',
    ],
  },
];

const comparisonFeatures = [
  { name: 'Live Channels', included: { basic: true, standard: true, premium: true } },
  { name: 'On-Demand Content', included: { basic: true, standard: true, premium: true } },
  { name: 'EPG Guide', included: { basic: false, standard: true, premium: true } },
  { name: 'Catch-Up TV', included: { basic: false, standard: true, premium: true } },
  { name: '4K Streaming', included: { basic: false, standard: false, premium: true } },
  { name: 'Multi-Device', included: { basic: false, standard: false, premium: true } },
  { name: 'Priority Support', included: { basic: false, standard: true, premium: true } },
];

const selectPlan = (plan) => {
  router.post(route('subscription.checkout'), { plan_id: plan.id });
};
</script>
