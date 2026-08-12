import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null)
    const token = ref(localStorage.getItem('auth_token') || null)
    const loading = ref(false)
    const errors = ref({})

    const isAuthenticated = computed(() => !!user.value && !!token.value)
    const isAdmin = computed(() => user.value?.role === 'admin')
    const isSubscribed = computed(() => user.value?.subscription?.status === 'active')

    function setAuthHeader() {
        if (token.value) {
            axios.defaults.headers.common['Authorization'] = `Bearer ${token.value}`
        } else {
            delete axios.defaults.headers.common['Authorization']
        }
    }

    async function fetchUser() {
        if (!token.value) return null
        loading.value = true
        try {
            setAuthHeader()
            const { data } = await axios.get('/api/user')
            user.value = data.data || data
            return user.value
        } catch (e) {
            user.value = null
            token.value = null
            localStorage.removeItem('auth_token')
            return null
        } finally {
            loading.value = false
        }
    }

    async function login(credentials) {
        loading.value = true
        errors.value = {}
        try {
            const { data } = await axios.post('/api/login', credentials)
            token.value = data.token
            user.value = data.user
            localStorage.setItem('auth_token', data.token)
            setAuthHeader()
            return data
        } catch (e) {
            errors.value = e.response?.data?.errors || { email: ['Invalid credentials'] }
            throw e
        } finally {
            loading.value = false
        }
    }

    async function register(payload) {
        loading.value = true
        errors.value = {}
        try {
            const { data } = await axios.post('/api/register', payload)
            token.value = data.token
            user.value = data.user
            localStorage.setItem('auth_token', data.token)
            setAuthHeader()
            return data
        } catch (e) {
            errors.value = e.response?.data?.errors || {}
            throw e
        } finally {
            loading.value = false
        }
    }

    async function logout() {
        loading.value = true
        try {
            await axios.post('/api/logout')
        } catch (e) {
            // ignore
        } finally {
            user.value = null
            token.value = null
            localStorage.removeItem('auth_token')
            delete axios.defaults.headers.common['Authorization']
            loading.value = false
        }
    }

    async function updateProfile(payload) {
        loading.value = true
        errors.value = {}
        try {
            const { data } = await axios.put('/api/user/profile', payload)
            user.value = data.data || data
            return data
        } catch (e) {
            errors.value = e.response?.data?.errors || {}
            throw e
        } finally {
            loading.value = false
        }
    }

    function initialize() {
        if (token.value) {
            setAuthHeader()
            fetchUser()
        }
    }

    return {
        user,
        token,
        loading,
        errors,
        isAuthenticated,
        isAdmin,
        isSubscribed,
        fetchUser,
        login,
        register,
        logout,
        updateProfile,
        initialize,
    }
})
