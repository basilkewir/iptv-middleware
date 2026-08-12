export function useApiFetch() {
    const getXsrf = () => {
        const token = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))
        return token ? decodeURIComponent(token.split('=')[1]) : ''
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
