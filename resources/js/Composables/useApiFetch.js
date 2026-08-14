export function useApiFetch() {
    const getXsrf = () => {
        const raw = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))
        return raw ? decodeURIComponent(raw.substring('XSRF-TOKEN='.length)) : ''
    }

    const apiFetch = (url, options = {}) => {
        return fetch(url, {
            ...options,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getXsrf(),
                ...(options.headers || {}),
            },
        })
    }

    return { apiFetch }
}
