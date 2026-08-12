import { ref, computed } from 'vue'
import axios from 'axios'
import { usePlayerStore } from '@/Stores/player'

export function useStreaming() {
    const playerStore = usePlayerStore()
    const streamUrl = ref(null)
    const streamType = ref(null)
    const loading = ref(false)
    const error = ref(null)
    const qualities = ref([])
    const selectedQuality = ref('auto')
    const streamMetadata = ref(null)

    const isLive = computed(() => streamType.value === 'live')
    const isVod = computed(() => streamType.value === 'vod')
    const currentUrl = computed(() => streamUrl.value)

    async function startStream(content, quality = 'auto') {
        loading.value = true
        error.value = null
        try {
            const endpoint = content.type === 'live'
                ? `/api/stream/live/${content.id}`
                : `/api/stream/vod/${content.id}`

            const params = { quality }
            const { data } = await axios.get(endpoint, { params })

            streamUrl.value = data.url || data.manifest || data
            streamType.value = content.type
            selectedQuality.value = quality
            streamMetadata.value = data.metadata || null

            if (data.qualities) {
                qualities.value = data.qualities
            }

            playerStore.setCurrentContent(content)
            return streamUrl.value
        } catch (e) {
            error.value = e.response?.data?.message || 'Failed to start stream'
            throw e
        } finally {
            loading.value = false
        }
    }

    async function stopStream() {
        if (streamUrl.value && playerStore.currentContent?.id) {
            try {
                await axios.post('/api/stream/stop', {
                    content_id: playerStore.currentContent.id,
                })
            } catch (e) {
                // ignore cleanup errors
            }
        }
        streamUrl.value = null
        streamType.value = null
        streamMetadata.value = null
        qualities.value = []
    }

    function getStreamUrl(content, quality = 'auto') {
        const base = content.type === 'live' ? '/api/stream/live' : '/api/stream/vod'
        return `${base}/${content.id}?quality=${quality}`
    }

    async function changeQuality(quality) {
        if (!playerStore.currentContent) return
        selectedQuality.value = quality
        await startStream(playerStore.currentContent, quality)
    }

    function getAvailableQualities() {
        return qualities.value
    }

    async function checkStreamStatus(contentId) {
        try {
            const { data } = await axios.get(`/api/stream/status/${contentId}`)
            return data
        } catch (e) {
            return { active: false }
        }
    }

    return {
        streamUrl,
        streamType,
        loading,
        error,
        qualities,
        selectedQuality,
        streamMetadata,
        isLive,
        isVod,
        currentUrl,
        startStream,
        stopStream,
        getStreamUrl,
        changeQuality,
        getAvailableQualities,
        checkStreamStatus,
    }
}
