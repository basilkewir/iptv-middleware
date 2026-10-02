/**
 * XSRF cookie lookup shared by every raw fetch() in the app.
 *
 * Laravel hard-codes the cookie name to 'XSRF-TOKEN', and cookies are scoped
 * by hostname rather than by port — so every Laravel app on the same host
 * overwrites the same cookie. On 192.168.20.15 the middleware (:8081) and the
 * HMS (:80) were clobbering each other's token, which is what produced
 * intermittent 419 "CSRF token mismatch" on save.
 *
 * The middleware therefore publishes its own cookie name; we prefer it and
 * fall back to the generic one so an already-loaded page keeps working.
 */
const APP_XSRF_COOKIE = 'iptvmiddleware_xsrf'
const GENERIC_XSRF_COOKIE = 'XSRF-TOKEN'

export function getXsrfToken() {
    const cookies = document.cookie.split('; ')
    const raw =
        cookies.find(c => c.startsWith(APP_XSRF_COOKIE + '=')) ||
        cookies.find(c => c.startsWith(GENERIC_XSRF_COOKIE + '='))

    return raw ? decodeURIComponent(raw.substring(raw.indexOf('=') + 1)) : ''
}
