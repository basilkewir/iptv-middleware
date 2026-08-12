<template>
  <AppLayout title="Checkout">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Checkout
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <div class="lg:col-span-2 space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-6">
                Payment Method
              </h3>
              <div class="space-y-4">
                <label
                  v-for="method in paymentMethods"
                  :key="method.id"
                  class="flex items-center p-4 border-2 rounded-lg cursor-pointer transition-colors"
                  :class="
                    selectedPaymentMethod === method.id
                      ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20'
                      : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
                  "
                >
                  <input
                    v-model="selectedPaymentMethod"
                    type="radio"
                    :value="method.id"
                    class="text-indigo-600 focus:ring-indigo-500"
                  />
                  <div class="ml-4 flex items-center">
                    <component :is="method.icon" class="w-8 h-8 mr-3" />
                    <div>
                      <p class="font-medium text-gray-900 dark:text-gray-100">
                        {{ method.name }}
                      </p>
                      <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ method.description }}
                      </p>
                    </div>
                  </div>
                </label>
              </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-6">
                Invoice Details
              </h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Full Name
                  </label>
                  <input
                    v-model="form.name"
                    type="text"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500"
                  />
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
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Postal Code
                  </label>
                  <input
                    v-model="form.postalCode"
                    type="text"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500"
                  />
                </div>
              </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                Promo Code
              </h3>
              <div class="flex gap-3">
                <input
                  v-model="promoCode"
                  type="text"
                  placeholder="Enter promo code"
                  class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500"
                />
                <button
                  @click="applyPromoCode"
                  class="px-6 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                >
                  Apply
                </button>
              </div>
              <p
                v-if="promoApplied"
                class="mt-2 text-sm text-green-600 dark:text-green-400"
              >
                Promo code applied! You save ${{ promoDiscount }}
              </p>
            </div>
          </div>

          <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 sticky top-6">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-6">
                Order Summary
              </h3>
              <div class="space-y-4">
                <div class="flex justify-between">
                  <span class="text-gray-600 dark:text-gray-400">Plan</span>
                  <span class="font-medium text-gray-900 dark:text-gray-100">
                    {{ selectedPlan?.name }}
                  </span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600 dark:text-gray-400">Period</span>
                  <span class="font-medium text-gray-900 dark:text-gray-100">
                    Monthly
                  </span>
                </div>
                <div
                  v-if="promoApplied"
                  class="flex justify-between text-green-600 dark:text-green-400"
                >
                  <span>Promo Discount</span>
                  <span>-${{ promoDiscount }}</span>
                </div>
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                  <div class="flex justify-between">
                    <span class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                      Total
                    </span>
                    <span class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                      ${{ finalPrice }}
                    </span>
                  </div>
                </div>
              </div>
              <button
                @click="confirmPayment"
                :disabled="processing"
                class="w-full mt-6 py-3 px-6 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                <span v-if="processing">Processing...</span>
                <span v-else>Confirm Payment</span>
              </button>
              <p class="mt-4 text-xs text-center text-gray-500 dark:text-gray-400">
                By confirming, you agree to our Terms of Service and Privacy Policy.
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
import { ref, computed, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { route } from '@/Composables/useRoute';

const props = defineProps({
  plan: Object,
});

const form = ref({
  name: '',
  email: '',
  country: '',
  postalCode: '',
});

const selectedPaymentMethod = ref('card');
const promoCode = ref('');
const promoApplied = ref(false);
const promoDiscount = ref(0);
const processing = ref(false);

const paymentMethods = [
  { id: 'card', name: 'Credit/Debit Card', description: 'Visa, Mastercard, AMEX' },
  { id: 'paypal', name: 'PayPal', description: 'Pay with your PayPal account' },
  { id: 'crypto', name: 'Cryptocurrency', description: 'Bitcoin, Ethereum, USDT' },
];

const selectedPlan = computed(() => props.plan);
const finalPrice = computed(() => {
  if (!selectedPlan.value) return 0;
  return (selectedPlan.value.price - promoDiscount.value).toFixed(2);
});

onMounted(() => {
  const user = usePage().props.auth.user;
  if (user) {
    form.value.name = user.name;
    form.value.email = user.email;
  }
});

const applyPromoCode = () => {
  if (promoCode.value === 'SAVE10') {
    promoApplied.value = true;
    promoDiscount.value = 2;
  }
};

const confirmPayment = () => {
  processing.value = true;
  router.post(route('subscription.process'), {
    plan_id: selectedPlan.value.id,
    payment_method: selectedPaymentMethod.value,
    promo_code: promoCode.value,
    ...form.value,
  }, {
    onFinish: () => {
      processing.value = false;
    },
  });
};
</script>
