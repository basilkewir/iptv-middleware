import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import { useSubscriptionStore } from '@/Stores/subscription'

export function useSubscription() {
    const store = useSubscriptionStore()

    const currentSubscription = computed(() => store.currentSubscription)
    const packages = computed(() => store.packages)
    const invoices = computed(() => store.invoices)
    const loading = computed(() => store.loading)
    const errors = computed(() => store.errors)
    const paymentProcessing = computed(() => store.paymentProcessing)
    const isActive = computed(() => store.isActive)
    const isExpired = computed(() => store.isExpired)
    const daysRemaining = computed(() => store.daysRemaining)
    const currentPlan = computed(() => store.currentPlan)
    const expiryDate = computed(() => store.expiryDate)
    const autoRenew = computed(() => store.autoRenew)

    async function fetchSubscription() {
        return store.fetchCurrentSubscription()
    }

    async function fetchPackages() {
        return store.fetchPackages()
    }

    async function subscribe(packageId, paymentMethod = null) {
        try {
            const data = await store.subscribe(packageId, paymentMethod)
            return data
        } catch (e) {
            throw e
        }
    }

    async function renew(paymentMethod = null) {
        try {
            const data = await store.renew(paymentMethod)
            return data
        } catch (e) {
            throw e
        }
    }

    async function cancel() {
        try {
            await store.cancelSubscription()
        } catch (e) {
            throw e
        }
    }

    async function fetchInvoices() {
        return store.fetchInvoices()
    }

    async function toggleAutoRenew() {
        return store.toggleAutoRenew()
    }

    function redirectToSubscribe() {
        router.visit(route('subscription.plans'))
    }

    function redirectToPricing() {
        router.visit(route('subscription.plans'))
    }

    function getPlanById(id) {
        return packages.value.find(p => p.id === id) || null
    }

    function isExpiringSoon(days = 7) {
        return isActive.value && daysRemaining.value <= days
    }

    function canAccess(feature) {
        if (!isActive.value) return false
        return currentPlan.value?.features?.includes(feature) ?? false
    }

    function getMaxConnections() {
        return currentPlan.value?.max_connections ?? 0
    }

    return {
        currentSubscription,
        packages,
        invoices,
        loading,
        errors,
        paymentProcessing,
        isActive,
        isExpired,
        daysRemaining,
        currentPlan,
        expiryDate,
        autoRenew,
        fetchSubscription,
        fetchPackages,
        subscribe,
        renew,
        cancel,
        fetchInvoices,
        toggleAutoRenew,
        redirectToSubscribe,
        redirectToPricing,
        getPlanById,
        isExpiringSoon,
        canAccess,
        getMaxConnections,
    }
}
