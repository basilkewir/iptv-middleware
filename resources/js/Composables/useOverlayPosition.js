export function useOverlayPosition() {
    const round1 = (v) => Math.round(v * 10) / 10
    const clamp = (v) => Math.min(95, Math.max(0, round1(v)))

    /**
     * Snap a preset corner into precise X/Y percentages for the logo overlay.
     * Logo width is overlay_logo_size / 10 (% of frame width), height derived
     * from the supplied aspect ratio (defaults to square when unknown).
     */
    const snapLogoPosition = (f, pos, logoAspect = 1) => {
        const size = Number(f.overlay_logo_size) || 100
        const wPct = size / 10
        const hPct = wPct / (Number(logoAspect) || 1)
        const m = 2
        f.overlay_logo_position = pos
        switch (pos) {
            case 'top-left':
                f.overlay_logo_x = m
                f.overlay_logo_y = m
                break
            case 'top-right':
                f.overlay_logo_x = clamp(100 - wPct - m)
                f.overlay_logo_y = m
                break
            case 'bottom-left':
                f.overlay_logo_x = m
                f.overlay_logo_y = clamp(100 - hPct - m)
                break
            case 'bottom-right':
                f.overlay_logo_x = clamp(100 - wPct - m)
                f.overlay_logo_y = clamp(100 - hPct - m)
                break
            case 'center':
                f.overlay_logo_x = clamp((100 - wPct) / 2)
                f.overlay_logo_y = clamp((100 - hPct) / 2)
                break
        }
    }

    /**
     * Snap a preset corner into precise X/Y percentages for the clock overlay.
     * size.width/size.height are the preview frame dimensions and size.textW/
     * size.textH the measured clock box, used to keep right/bottom corners
     * accurate. Falls back to sane defaults when metrics are unavailable.
     */
    const snapClockPosition = (f, pos, size = { width: 800, height: 450, textW: 70, textH: 24 }) => {
        const w = size.width || 800
        const h = size.height || 450
        const wPct = ((size.textW || 70) / w) * 100
        const hPct = ((size.textH || 24) / h) * 100
        const m = 1.5
        f.overlay_clock_position = pos
        switch (pos) {
            case 'top-left':
                f.overlay_clock_x = 2
                f.overlay_clock_y = 2
                break
            case 'top-right':
                f.overlay_clock_x = clamp(100 - wPct - m)
                f.overlay_clock_y = 2
                break
            case 'bottom-left':
                f.overlay_clock_x = 2
                f.overlay_clock_y = clamp(100 - hPct - m)
                break
            case 'bottom-right':
                f.overlay_clock_x = clamp(100 - wPct - m)
                f.overlay_clock_y = clamp(100 - hPct - m)
                break
        }
    }

    return { snapLogoPosition, snapClockPosition }
}
