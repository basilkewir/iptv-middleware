import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useUiStore = defineStore('ui', () => {
    const sidebarOpen = ref(true)
    const sidebarCollapsed = ref(false)
    const activeModal = ref(null)
    const modalData = ref(null)
    const theme = ref(localStorage.getItem('theme') || 'dark')
    const notifications = ref([])
    const unreadCount = computed(() => notifications.value.filter(n => !n.read).length)
    const loading = ref(false)
    const toast = ref(null)
    const breadcrumbs = ref([])

    function toggleSidebar() {
        sidebarOpen.value = !sidebarOpen.value
    }

    function setSidebarState(open) {
        sidebarOpen.value = open
    }

    function toggleSidebarCollapse() {
        sidebarCollapsed.value = !sidebarCollapsed.value
    }

    function openModal(name, data = null) {
        activeModal.value = name
        modalData.value = data
    }

    function closeModal() {
        activeModal.value = null
        modalData.value = null
    }

    function setTheme(newTheme) {
        theme.value = newTheme
        localStorage.setItem('theme', newTheme)
        document.documentElement.classList.toggle('dark', newTheme === 'dark')
    }

    function toggleTheme() {
        setTheme(theme.value === 'dark' ? 'light' : 'dark')
    }

    function addNotification(notification) {
        notifications.value.unshift({
            id: Date.now(),
            read: false,
            timestamp: new Date().toISOString(),
            ...notification,
        })
    }

    function markAsRead(id) {
        const n = notifications.value.find(n => n.id === id)
        if (n) n.read = true
    }

    function markAllRead() {
        notifications.value.forEach(n => (n.read = true))
    }

    function clearNotifications() {
        notifications.value = []
    }

    function removeNotification(id) {
        notifications.value = notifications.value.filter(n => n.id !== id)
    }

    function showToast(message, type = 'info', duration = 3000) {
        toast.value = { message, type }
        if (duration > 0) {
            setTimeout(() => {
                toast.value = null
            }, duration)
        }
    }

    function hideToast() {
        toast.value = null
    }

    function setBreadcrumbs(items) {
        breadcrumbs.value = items
    }

    function setLoading(state) {
        loading.value = state
    }

    return {
        sidebarOpen,
        sidebarCollapsed,
        activeModal,
        modalData,
        theme,
        notifications,
        unreadCount,
        loading,
        toast,
        breadcrumbs,
        toggleSidebar,
        setSidebarState,
        toggleSidebarCollapse,
        openModal,
        closeModal,
        setTheme,
        toggleTheme,
        addNotification,
        markAsRead,
        markAllRead,
        clearNotifications,
        removeNotification,
        showToast,
        hideToast,
        setBreadcrumbs,
        setLoading,
    }
})
