import { ref, computed, onUnmounted } from 'vue'
import { usePlayerStore } from '@/Stores/player'

export function usePlayer() {
    const store = usePlayerStore()
    const playerElement = ref(null)
    const hlsInstance = ref(null)

    const currentContent = computed(() => store.currentContent)
    const isPlaying = computed(() => store.isPlaying)
    const currentTime = computed(() => store.currentTime)
    const duration = computed(() => store.duration)
    const volume = computed(() => store.volume)
    const isMuted = computed(() => store.isMuted)
    const isFullscreen = computed(() => store.isFullscreen)
    const quality = computed(() => store.quality)
    const progress = computed(() => store.progress)
    const isLive = computed(() => store.isLive)
    const error = computed(() => store.error)
    const buffering = computed(() => store.buffering)

    function initPlayer(element) {
        playerElement.value = element
        if (!element) return

        element.addEventListener('timeupdate', () => {
            store.setCurrentTime(element.currentTime)
        })

        element.addEventListener('loadedmetadata', () => {
            store.setDuration(element.duration)
        })

        element.addEventListener('play', () => store.setPlaying(true))
        element.addEventListener('pause', () => store.setPlaying(false))
        element.addEventListener('waiting', () => store.setBuffering(true))
        element.addEventListener('canplay', () => store.setBuffering(false))
        element.addEventListener('error', (e) => {
            store.setError(e.target.error?.message || 'Playback error')
        })
    }

    function play() {
        const el = playerElement.value
        if (!el) return
        el.play().catch(e => store.setError(e.message))
    }

    function pause() {
        const el = playerElement.value
        if (!el) return
        el.pause()
    }

    function togglePlay() {
        if (isPlaying.value) {
            pause()
        } else {
            play()
        }
    }

    function seek(time) {
        const el = playerElement.value
        if (!el) return
        el.currentTime = time
        store.seekTo(time)
    }

    function seekPercent(percent) {
        const el = playerElement.value
        if (!el) return
        el.currentTime = (percent / 100) * el.duration
        store.seekPercent(percent)
    }

    function setVolume(vol) {
        const el = playerElement.value
        store.setVolume(vol)
        if (el) el.volume = vol
    }

    function toggleMute() {
        const el = playerElement.value
        store.toggleMute()
        if (el) el.muted = store.isMuted
    }

    function requestFullscreen() {
        const el = playerElement.value
        if (!el) return
        if (el.requestFullscreen) {
            el.requestFullscreen()
        } else if (el.webkitRequestFullscreen) {
            el.webkitRequestFullscreen()
        }
        store.setFullscreen(true)
    }

    function exitFullscreen() {
        if (document.exitFullscreen) {
            document.exitFullscreen()
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen()
        }
        store.setFullscreen(false)
    }

    function toggleFullscreen() {
        if (isFullscreen.value) {
            exitFullscreen()
        } else {
            requestFullscreen()
        }
    }

    function setQuality(q) {
        store.setQuality(q)
    }

    function loadContent(content) {
        store.setCurrentContent(content)
    }

    function stop() {
        const el = playerElement.value
        if (el) {
            el.pause()
            el.removeAttribute('src')
            el.load()
        }
        store.reset()
    }

    function destroy() {
        stop()
        if (hlsInstance.value) {
            hlsInstance.value.destroy()
            hlsInstance.value = null
        }
        playerElement.value = null
    }

    onUnmounted(() => {
        destroy()
    })

    return {
        playerElement,
        currentContent,
        isPlaying,
        currentTime,
        duration,
        volume,
        isMuted,
        isFullscreen,
        quality,
        progress,
        isLive,
        error,
        buffering,
        initPlayer,
        play,
        pause,
        togglePlay,
        seek,
        seekPercent,
        setVolume,
        toggleMute,
        requestFullscreen,
        exitFullscreen,
        toggleFullscreen,
        setQuality,
        loadContent,
        stop,
        destroy,
    }
}
