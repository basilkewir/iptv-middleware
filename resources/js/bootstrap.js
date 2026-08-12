import axios from 'axios'
window.axios = axios

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
axios.defaults.headers.common['Accept'] = 'application/json'

const token = document.head.querySelector('meta[name="csrf-token"]')
if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content
}

import('pusher-js').then(({ default: Pusher }) => {
    window.Pusher = Pusher
    return import('laravel-echo')
}).then(({ default: Echo }) => {
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_PUSHER_APP_KEY || undefined,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || undefined,
        forceTLS: true,
    })
}).catch(() => {
    // Pusher/Echo not configured - skip real-time features
})
