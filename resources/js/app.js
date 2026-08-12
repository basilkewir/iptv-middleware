import { createApp, h } from 'vue'
import { createPinia } from 'pinia'
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { ZiggyVue } from 'ziggy/vue'
import { installTvFocus } from '@/Composables/useTvFocus'
import '../css/app.css'
import './bootstrap'

createInertiaApp({
    title: (title) => title ? `${title} - IPTV Middleware` : 'IPTV Middleware',
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue')
        ),
    setup({ el, App, props, plugin }) {
        const page = props.initialPage || {}
        const ziggyData = page.props?.ziggy || {}

        window.Ziggy = ziggyData

        const pinia = createPinia()
        const app = createApp({ render: () => h(App, { ...props, initialPage: page }) })

        app.use(pinia)
        app.use(plugin)
        app.use(ZiggyVue, ziggyData)
        installTvFocus(app)

        app.mount(el)
    },
    progress: {
        color: '#3b82f6',
        showSpinner: false,
    },
})
