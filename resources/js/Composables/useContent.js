import { ref, computed } from 'vue'
import { useContentStore } from '@/Stores/content'

export function useContent() {
    const store = useContentStore()
    const searchQuery = ref('')
    const activeFilters = ref({})
    const sortBy = ref('name')
    const sortOrder = ref('asc')

    const channels = computed(() => store.channels)
    const vodItems = computed(() => store.vodItems)
    const categories = computed(() => store.categories)
    const searchResults = computed(() => store.searchResults)
    const currentContent = computed(() => store.currentContent)
    const loading = computed(() => store.loading)
    const errors = computed(() => store.errors)
    const pagination = computed(() => store.pagination)
    const activeChannels = computed(() => store.activeChannels)
    const favoriteChannels = computed(() => store.favoriteChannels)
    const vodByCategory = computed(() => store.vodByCategory)

    async function fetchChannels(params = {}) {
        return store.fetchChannels({ ...activeFilters.value, ...params })
    }

    async function fetchVodItems(params = {}) {
        return store.fetchVodItems({ ...activeFilters.value, ...params })
    }

    async function fetchCategories() {
        return store.fetchCategories()
    }

    async function fetchContent(id, type) {
        return store.fetchContent(id, type)
    }

    async function search(query) {
        searchQuery.value = query
        if (!query.trim()) {
            store.searchResults = []
            return []
        }
        return store.search(query)
    }

    function applyFilter(key, value) {
        if (value === null || value === '' || value === undefined) {
            delete activeFilters.value[key]
        } else {
            activeFilters.value[key] = value
        }
        return fetchChannels(activeFilters.value)
    }

    function clearFilters() {
        activeFilters.value = {}
        return fetchChannels()
    }

    function sort(field) {
        if (sortBy.value === field) {
            sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
        } else {
            sortBy.value = field
            sortOrder.value = 'asc'
        }
    }

    async function paginate(page) {
        return store.paginate(page)
    }

    async function loadMore() {
        const nextPage = pagination.value.page + 1
        if (nextPage > pagination.value.lastPage) return
        return store.paginate(nextPage)
    }

    async function toggleFavorite(contentId, type = 'channel') {
        return store.toggleFavorite(contentId, type)
    }

    function filterByCategory(categoryId) {
        return applyFilter('category_id', categoryId)
    }

    function filterByType(type) {
        return applyFilter('type', type)
    }

    function filterByStatus(status) {
        return applyFilter('status', status)
    }

    function refresh() {
        store.clearCache()
        return fetchChannels()
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
        searchQuery,
        activeFilters,
        sortBy,
        sortOrder,
        fetchChannels,
        fetchVodItems,
        fetchCategories,
        fetchContent,
        search,
        applyFilter,
        clearFilters,
        sort,
        paginate,
        loadMore,
        toggleFavorite,
        filterByCategory,
        filterByType,
        filterByStatus,
        refresh,
    }
}
