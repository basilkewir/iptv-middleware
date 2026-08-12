import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const usePlayerStore = defineStore('player', () => {
    const currentContent = ref(null)
    const isPlaying = ref(false)
    const currentTime = ref(0)
    const duration = ref(0)
    const volume = ref(1)
    const isMuted = ref(false)
    const isFullscreen = ref(false)
    const quality = ref('auto')
    const playQueue = ref([])
    const history = ref([])
    const historyIndex = ref(-1)
    const error = ref(null)
    const loading = ref(false)
    const buffering = ref(false)

    const progress = computed(() => {
        if (duration.value === 0) return 0
        return (currentTime.value / duration.value) * 100
    })

    const canGoNext = computed(() => historyIndex.value < playQueue.value.length - 1)
    const canGoPrev = computed(() => historyIndex.value > 0)
    const currentQueueItem = computed(() => playQueue.value[historyIndex.value] || null)
    const hasContent = computed(() => !!currentContent.value)
    const isLive = computed(() => currentContent.value?.type === 'live' || currentContent.value?.is_live)

    function setCurrentContent(content) {
        currentContent.value = content
        addToHistory(content)
    }

    function addToHistory(content) {
        const exists = history.value.findIndex(h => h.id === content.id)
        if (exists === -1) {
            history.value.unshift({ ...content, watchedAt: Date.now() })
            if (history.value.length > 50) history.value.pop()
        }
    }

    function addToQueue(content) {
        playQueue.value.push({ ...content })
    }

    function removeFromQueue(index) {
        playQueue.value.splice(index, 1)
    }

    function clearQueue() {
        playQueue.value = []
    }

    function playNext() {
        if (!canGoNext.value) return null
        historyIndex.value++
        const item = playQueue.value[historyIndex.value]
        setCurrentContent(item)
        return item
    }

    function playPrev() {
        if (!canGoPrev.value) return null
        historyIndex.value--
        const item = playQueue.value[historyIndex.value]
        setCurrentContent(item)
        return item
    }

    function setPlaying(state) {
        isPlaying.value = state
    }

    function setCurrentTime(time) {
        currentTime.value = time
    }

    function setDuration(dur) {
        duration.value = dur
    }

    function setVolume(vol) {
        volume.value = Math.min(1, Math.max(0, vol))
        isMuted.value = vol === 0
    }

    function toggleMute() {
        isMuted.value = !isMuted.value
    }

    function setFullscreen(state) {
        isFullscreen.value = state
    }

    function setQuality(q) {
        quality.value = q
    }

    function setBuffering(state) {
        buffering.value = state
    }

    function setError(err) {
        error.value = err
    }

    function reset() {
        currentContent.value = null
        isPlaying.value = false
        currentTime.value = 0
        duration.value = 0
        volume.value = 1
        isMuted.value = false
        isFullscreen.value = false
        quality.value = 'auto'
        error.value = null
        buffering.value = false
    }

    function clearHistory() {
        history.value = []
        historyIndex.value = -1
    }

    function seekTo(time) {
        currentTime.value = Math.min(duration.value, Math.max(0, time))
    }

    function seekPercent(percent) {
        seekTo((percent / 100) * duration.value)
    }

    return {
        currentContent,
        isPlaying,
        currentTime,
        duration,
        volume,
        isMuted,
        isFullscreen,
        quality,
        playQueue,
        history,
        historyIndex,
        error,
        loading,
        buffering,
        progress,
        canGoNext,
        canGoPrev,
        currentQueueItem,
        hasContent,
        isLive,
        setCurrentContent,
        addToHistory,
        addToQueue,
        removeFromQueue,
        clearQueue,
        playNext,
        playPrev,
        setPlaying,
        setCurrentTime,
        setDuration,
        setVolume,
        toggleMute,
        setFullscreen,
        setQuality,
        setBuffering,
        setError,
        reset,
        clearHistory,
        seekTo,
        seekPercent,
    }
})
