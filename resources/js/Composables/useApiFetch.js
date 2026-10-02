import { getXsrfToken } from '@/Composables/useCsrf'

export function useApiFetch() {
    const apiFetch = (url, options = {}) => {
        return fetch(url, {
            ...options,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
                ...(options.headers || {}),
            },
        })
    }

    return { apiFetch }
}
