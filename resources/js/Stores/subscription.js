import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export const useSubscriptionStore = defineStore('subscription', () => {
    const currentSubscription = ref(null)
    const packages = ref([])
    const invoices = ref([])
    const loading = ref(false)
    const errors = ref({})
    const paymentProcessing = ref(false)

    const isActive = computed(() => currentSubscription.value?.status === 'active')
    const isExpired = computed(() => {
        if (!currentSubscription.value?.expires_at) return true
        return new Date(currentSubscription.value.expires_at) < new Date()
    })
    const daysRemaining = computed(() => {
        if (!currentSubscription.value?.expires_at) return 0
        const diff = new Date(currentSubscription.value.expires_at) - new Date()
        return Math.max(0, Math.ceil(diff / (1000 * 60 * 60 * 24)))
    })
    const currentPlan = computed(() => currentSubscription.value?.package || null)
    const expiryDate = computed(() => currentSubscription.value?.expires_at || null)
    const autoRenew = computed(() => currentSubscription.value?.auto_renew ?? false)

    async function fetchCurrentSubscription() {
        loading.value = true
        errors.value = {}
        try {
            const { data } = await axios.get('/api/subscription')
            currentSubscription.value = data.data || data
            return currentSubscription.value
        } catch (e) {
            if (e.response?.status !== 404) {
                errors.value = e.response?.data?.errors || { fetch: 'Failed to load subscription' }
            }
            currentSubscription.value = null
            return null
        } finally {
            loading.value = false
        }
    }

    async function fetchPackages() {
        try {
            const { data } = await axios.get('/api/packages')
            packages.value = data.data || data
            return packages.value
        } catch (e) {
            errors.value = e.response?.data?.errors || { fetch: 'Failed to load packages' }
            throw e
        }
    }

    async function subscribe(packageId, paymentMethod = null) {
        paymentProcessing.value = true
        errors.value = {}
        try {
            const payload = { package_id: packageId }
            if (paymentMethod) payload.payment_method = paymentMethod
            const { data } = await axios.post('/api/subscribe', payload)
            currentSubscription.value = data.data || data
            return data
        } catch (e) {
            errors.value = e.response?.data?.errors || { payment: 'Subscription failed' }
            throw e
        } finally {
            paymentProcessing.value = false
        }
    }

    async function renew(paymentMethod = null) {
        paymentProcessing.value = true
        errors.value = {}
        try {
            const payload = {}
            if (paymentMethod) payload.payment_method = paymentMethod
            const { data } = await axios.post('/api/subscription/renew', payload)
            currentSubscription.value = data.data || data
            return data
        } catch (e) {
            errors.value = e.response?.data?.errors || { renew: 'Renewal failed' }
            throw e
        } finally {
            paymentProcessing.value = false
        }
    }

    async function cancelSubscription() {
        loading.value = true
        errors.value = {}
        try {
            const { data } = await axios.post('/api/subscription/cancel')
            if (currentSubscription.value) {
                currentSubscription.value.status = 'cancelled'
            }
            return data
        } catch (e) {
            errors.value = e.response?.data?.errors || { cancel: 'Cancellation failed' }
            throw e
        } finally {
            loading.value = false
        }
    }

    async function fetchInvoices() {
        try {
            const { data } = await axios.get('/api/invoices')
            invoices.value = data.data || data
            return invoices.value
        } catch (e) {
            errors.value = e.response?.data?.errors || { fetch: 'Failed to load invoices' }
            throw e
        }
    }

    async function toggleAutoRenew() {
        try {
            const { data } = await axios.post('/api/subscription/auto-renew')
            if (currentSubscription.value) {
                currentSubscription.value.auto_renew = !currentSubscription.value.auto_renew
            }
            return data
        } catch (e) {
            errors.value = e.response?.data?.errors || { update: 'Failed to update auto-renew' }
            throw e
        }
    }

    function reset() {
        currentSubscription.value = null
        packages.value = []
        invoices.value = []
        errors.value = {}
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
        fetchCurrentSubscription,
        fetchPackages,
        subscribe,
        renew,
        cancelSubscription,
        fetchInvoices,
        toggleAutoRenew,
        reset,
    }
})
