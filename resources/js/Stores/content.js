import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export const useContentStore = defineStore('content', () => {
    const channels = ref([])
    const vodItems = ref([])
    const categories = ref([])
    const searchResults = ref([])
    const currentContent = ref(null)
    const loading = ref(false)
    const errors = ref({})
    const pagination = ref({ page: 1, perPage: 20, total: 0, lastPage: 1 })
    const cache = ref(new Map())

    const activeChannels = computed(() => channels.value.filter(c => c.status === 'active'))
    const favoriteChannels = computed(() => channels.value.filter(c => c.is_favorite))
    const vodByCategory = computed(() => {
        const grouped = {}
        vodItems.value.forEach(item => {
            const cat = item.category?.name || 'Uncategorized'
            if (!grouped[cat]) grouped[cat] = []
            grouped[cat].push(item)
        })
        return grouped
    })

    function getCacheKey(url, params) {
        return `${url}:${JSON.stringify(params || {})}`
    }

    function setCache(key, data) {
        cache.value.set(key, data)
        setTimeout(() => cache.value.delete(key), 5 * 60 * 1000)
    }

    async function fetchChannels(params = {}) {
        const cacheKey = getCacheKey('channels', params)
        if (cache.value.has(cacheKey)) {
            channels.value = cache.value.get(cacheKey)
            return channels.value
        }
        loading.value = true
        errors.value = {}
        try {
            const { data } = await axios.get('/api/channels', { params })
            channels.value = data.data || data
            pagination.value = data.meta || pagination.value
            setCache(cacheKey, channels.value)
            return channels.value
        } catch (e) {
            errors.value = e.response?.data?.errors || { fetch: 'Failed to load channels' }
            throw e
        } finally {
            loading.value = false
        }
    }

    async function fetchVodItems(params = {}) {
        const cacheKey = getCacheKey('vod', params)
        if (cache.value.has(cacheKey)) {
            vodItems.value = cache.value.get(cacheKey)
            return vodItems.value
        }
        loading.value = true
        errors.value = {}
        try {
            const { data } = await axios.get('/api/vod', { params })
            vodItems.value = data.data || data
            pagination.value = data.meta || pagination.value
            setCache(cacheKey, vodItems.value)
            return vodItems.value
        } catch (e) {
            errors.value = e.response?.data?.errors || { fetch: 'Failed to load VOD' }
            throw e
        } finally {
            loading.value = false
        }
    }

    async function fetchCategories() {
        const cacheKey = getCacheKey('categories', {})
        if (cache.value.has(cacheKey)) {
            categories.value = cache.value.get(cacheKey)
            return categories.value
        }
        try {
            const { data } = await axios.get('/api/categories')
            categories.value = data.data || data
            setCache(cacheKey, categories.value)
            return categories.value
        } catch (e) {
            errors.value = e.response?.data?.errors || { fetch: 'Failed to load categories' }
            throw e
        }
    }

    async function fetchContent(id, type = 'channel') {
        loading.value = true
        try {
            const url = type === 'vod' ? `/api/vod/${id}` : `/api/channels/${id}`
            const { data } = await axios.get(url)
            currentContent.value = data.data || data
            return currentContent.value
        } catch (e) {
            errors.value = e.response?.data?.errors || { fetch: 'Content not found' }
            throw e
        } finally {
            loading.value = false
        }
    }

    async function search(query, type = null) {
        loading.value = true
        errors.value = {}
        try {
            const params = { q: query }
            if (type) params.type = type
            const { data } = await axios.get('/api/search', { params })
            searchResults.value = data.data || data
            return searchResults.value
        } catch (e) {
            errors.value = e.response?.data?.errors || { search: 'Search failed' }
            throw e
        } finally {
            loading.value = false
        }
    }

    async function filterContent(filters) {
        loading.value = true
        errors.value = {}
        try {
            const { data } = await axios.get('/api/channels', { params: filters })
            channels.value = data.data || data
            pagination.value = data.meta || pagination.value
            return channels.value
        } catch (e) {
            errors.value = e.response?.data?.errors || { filter: 'Filter failed' }
            throw e
        } finally {
            loading.value = false
        }
    }

    async function paginate(page) {
        pagination.value.page = page
        return fetchChannels({ page })
    }

    async function toggleFavorite(contentId, type = 'channel') {
        try {
            const { data } = await axios.post(`/api/${type}/${contentId}/favorite`)
            const list = type === 'vod' ? vodItems.value : channels.value
            const item = list.find(i => i.id === contentId)
            if (item) item.is_favorite = !item.is_favorite
            return data
        } catch (e) {
            throw e
        }
    }

    function clearCache() {
        cache.value.clear()
    }

    return {
        channels,
        vodItems,
        categories,
        searchResults,
        currentContent,
        loading,
        errors,
        pagination,
        activeChannels,
        favoriteChannels,
        vodByCategory,
        fetchChannels,
        fetchVodItems,
        fetchCategories,
        fetchContent,
        search,
        filterContent,
        paginate,
        toggleFavorite,
        clearCache,
    }
})
