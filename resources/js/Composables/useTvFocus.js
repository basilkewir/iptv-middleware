import { onMounted, onUnmounted } from 'vue'

export function isTvDevice() {
  if (typeof window === 'undefined') return false

  const ua = navigator.userAgent.toLowerCase()
  const isAndroidTv = ua.includes('android') && (ua.includes('tv') || ua.includes('aftb') || ua.includes('aftm'))
  const isLargeScreen = window.matchMedia('(min-width: 2560px)').matches
  const isCoarsePointer = window.matchMedia('(pointer: coarse) and (hover: none)').matches

  return isAndroidTv || isLargeScreen || (isCoarsePointer && window.innerWidth >= 1280)
}

export function getFocusableElements(container = document) {
  const selector = '.tv-focusable, a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

  return Array.from(container.querySelectorAll(selector)).filter((el) => {
    const style = window.getComputedStyle(el)
    return style.display !== 'none' && style.visibility !== 'hidden' && el.offsetParent !== null
  })
}

function getCenter(el) {
  const rect = el.getBoundingClientRect()
  return { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 }
}

function findNearest(current, direction, elements) {
  const currentCenter = getCenter(current)
  let best = null
  let bestScore = Infinity

  for (const el of elements) {
    if (el === current) continue

    const center = getCenter(el)
    const dx = center.x - currentCenter.x
    const dy = center.y - currentCenter.y

    let valid = false
    switch (direction) {
      case 'up':
        valid = dy < -10 && Math.abs(dx) <= Math.abs(dy) * 2.5
        break
      case 'down':
        valid = dy > 10 && Math.abs(dx) <= Math.abs(dy) * 2.5
        break
      case 'left':
        valid = dx < -10 && Math.abs(dy) <= Math.abs(dx) * 2.5
        break
      case 'right':
        valid = dx > 10 && Math.abs(dy) <= Math.abs(dx) * 2.5
        break
    }

    if (!valid) continue

    const distance = Math.sqrt(dx * dx + dy * dy)
    if (distance < bestScore) {
      bestScore = distance
      best = el
    }
  }

  return best
}

export function setupTvFocusableElements(root = document) {
  root.querySelectorAll('.tv-focusable').forEach((el) => {
    if (!el.hasAttribute('tabindex')) {
      el.setAttribute('tabindex', '0')
    }
    if (el.tagName === 'A' && !el.getAttribute('role')) {
      el.setAttribute('role', 'button')
    }
  })
}

export function useTvFocus(options = {}) {
  const { container = null, autoFocus = false } = options
  let observer = null

  const getRoot = () => container?.value || document

  const handleKeydown = (e) => {
    if (!isTvDevice()) return

    const active = document.activeElement
    const focusable = getFocusableElements(getRoot())

    const keyMap = {
      ArrowUp: 'up',
      ArrowDown: 'down',
      ArrowLeft: 'left',
      ArrowRight: 'right',
    }

    if (keyMap[e.key]) {
      if (['INPUT', 'TEXTAREA', 'SELECT'].includes(active?.tagName)) return

      e.preventDefault()

      const direction = keyMap[e.key]
      const start = focusable.includes(active) ? active : focusable[0]
      if (!start) return

      const next = findNearest(start, direction, focusable)
      if (next) {
        next.focus({ preventScroll: false })
        next.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' })
      }
    }

    if ((e.key === 'Enter' || e.key === ' ') && active?.classList?.contains('tv-focusable')) {
      if (!['INPUT', 'TEXTAREA', 'SELECT', 'BUTTON', 'A'].includes(active.tagName)) {
        e.preventDefault()
        active.click()
      }
    }
  }

  onMounted(() => {
    if (!isTvDevice()) return

    document.documentElement.classList.add('tv-mode')
    setupTvFocusableElements(getRoot())
    document.addEventListener('keydown', handleKeydown)

    observer = new MutationObserver(() => setupTvFocusableElements(getRoot()))
    observer.observe(getRoot() === document ? document.body : getRoot(), {
      childList: true,
      subtree: true,
    })

    if (autoFocus) {
      requestAnimationFrame(() => {
        const focusable = getFocusableElements(getRoot())
        focusable[0]?.focus()
      })
    }
  })

  onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown)
    observer?.disconnect()
    document.documentElement.classList.remove('tv-mode')
  })

  return { isTvDevice, setupTvFocusableElements }
}

export function installTvFocus(app) {
  app.mixin({
    mounted() {
      if (isTvDevice()) {
        setupTvFocusableElements(this.$el instanceof Element ? this.$el : document)
      }
    },
    updated() {
      if (isTvDevice()) {
        setupTvFocusableElements(this.$el instanceof Element ? this.$el : document)
      }
    },
  })

  if (typeof window !== 'undefined' && isTvDevice()) {
    document.documentElement.classList.add('tv-mode')

    document.addEventListener('keydown', (e) => {
      const active = document.activeElement
      const focusable = getFocusableElements()

      const keyMap = {
        ArrowUp: 'up',
        ArrowDown: 'down',
        ArrowLeft: 'left',
        ArrowRight: 'right',
      }

      if (keyMap[e.key]) {
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(active?.tagName)) return

        e.preventDefault()

        const direction = keyMap[e.key]
        const start = focusable.includes(active) ? active : focusable[0]
        if (!start) return

        const next = findNearest(start, direction, focusable)
        if (next) {
          next.focus({ preventScroll: false })
          next.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' })
        }
      }

      if ((e.key === 'Enter' || e.key === ' ') && active?.classList?.contains('tv-focusable')) {
        if (!['INPUT', 'TEXTAREA', 'SELECT', 'BUTTON', 'A'].includes(active.tagName)) {
          e.preventDefault()
          active.click()
        }
      }
    })
  }
}
