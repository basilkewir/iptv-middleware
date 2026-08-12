import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from '@/Composables/useRoute'
import { useAuthStore } from '@/Stores/auth'

export function useAuth() {
    const store = useAuthStore()

    const user = computed(() => store.user)
    const isAuthenticated = computed(() => store.isAuthenticated)
    const isAdmin = computed(() => store.isAdmin)
    const isSubscribed = computed(() => store.isSubscribed)
    const loading = computed(() => store.loading)
    const errors = computed(() => store.errors)

    async function login(credentials) {
        const data = await store.login(credentials)
        router.visit('/')
        return data
    }

    async function register(payload) {
        const data = await store.register(payload)
        router.visit('/')
        return data
    }

    async function logout() {
        await store.logout()
        router.visit('/login')
    }

    async function updateProfile(payload) {
        return store.updateProfile(payload)
    }

    function hasRole(role) {
        return user.value?.role === role
    }

    function hasPermission(permission) {
        return user.value?.permissions?.includes(permission) ?? false
    }

    function hasAnyPermission(...perms) {
        return perms.some(p => hasPermission(p))
    }

    return {
        user,
        isAuthenticated,
        isAdmin,
        isSubscribed,
        loading,
        errors,
        login,
        register,
        logout,
        updateProfile,
        hasRole,
        hasPermission,
        hasAnyPermission,
    }
}
