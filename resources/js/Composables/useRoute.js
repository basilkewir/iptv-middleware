export function route(name, params = {}, absolute = false) {
    const config = window.Ziggy
    if (!config) {
        throw new Error('Ziggy error: window.Ziggy is not defined.')
    }
    if (!config.routes || !config.routes[name]) {
        throw new Error(`Ziggy error: route '${name}' is not in the route list.`)
    }

    const routeDef = config.routes[name]
    let routeParams = params

    if (routeParams !== null && routeParams !== undefined && typeof routeParams !== 'object') {
        if (routeDef.parameters && routeDef.parameters.length === 1) {
            routeParams = { [routeDef.parameters[0]]: routeParams }
        } else {
            routeParams = [routeParams]
        }
    }

    if (Array.isArray(routeParams) && routeDef.parameters) {
        routeParams = routeParams.reduce((result, value, index) => {
            if (routeDef.parameters[index]) {
                return { ...result, [routeDef.parameters[index]]: value }
            }
            return { ...result, [value]: '' }
        }, {})
    }

    if (typeof routeParams === 'object' && routeParams !== null && !Array.isArray(routeParams)) {
        const resolved = {}
        for (const paramName of (routeDef.parameters || [])) {
            if (paramName in routeParams) {
                resolved[paramName] = routeParams[paramName]
            } else if ('id' in routeParams) {
                resolved[paramName] = routeParams.id
            }
        }
        routeParams = resolved
    }

    let template = routeDef.uri

    const segments = template.match(/\{[^}]+\}/g) || []
    for (const segment of segments) {
        const isOptional = segment.endsWith('?}')
        const key = segment.replace(/[{}?]/g, '')
        const value = routeParams[key]

        if (value !== null && value !== undefined && value !== '') {
            template = template.replace(segment, encodeURIComponent(value))
        } else if (isOptional) {
            template = template.replace(segment, '')
        } else {
            throw new Error(`Ziggy error: '${key}' parameter is required for route '${name}'.`)
        }
    }

    template = template.replace(/\/+/g, '/').replace(/\/$/, '') || '/'

    if (absolute) {
        return `${config.url}/${template}`.replace(/\/+/g, '/')
    }

    return `/${template}`.replace(/\/+/g, '/')
}
