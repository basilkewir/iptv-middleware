import { ref } from 'vue'

const toasts = ref([])
let toastId = 0

export function useToast() {
    function show(message, type = 'info', duration = 3000) {
        const id = ++toastId
        toasts.value.push({ id, message, type, duration })

        if (duration > 0) {
            setTimeout(() => {
                dismiss(id)
            }, duration)
        }

        return id
    }

    function success(message, duration = 3000) {
        return show(message, 'success', duration)
    }

    function error(message, duration = 4000) {
        return show(message, 'error', duration)
    }

    function info(message, duration = 3000) {
        return show(message, 'info', duration)
    }

    function warning(message, duration = 3500) {
        return show(message, 'warning', duration)
    }

    function dismiss(id) {
        const index = toasts.value.findIndex((t) => t.id === id)
        if (index > -1) {
            toasts.value.splice(index, 1)
        }
    }

    function clear() {
        toasts.value = []
    }

    return {
        toasts,
        show,
        success,
        error,
        info,
        warning,
        dismiss,
        clear,
    }
}