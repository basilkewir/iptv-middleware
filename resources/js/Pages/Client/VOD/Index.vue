<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Common/Pagination.vue'

const props = defineProps({
    vod: Object,
    filters: Object,
    categories: Array,
    genres: Array,
    years: Array,
    qualityLevels: Array,
})

const filters = ref({
    type: props.filters?.type || '',
    category: props.filters?.category || '',
    genre: props.filters?.genre || '',
    year: props.filters?.year || '',
    quality: props.filters?.quality || '',
    rating: props.filters?.rating || '',
    sort: props.filters?.sort || 'featured',
    search: props.filters?.search || '',
})

const featuredItems = computed(() => {
    return props.vod.data.filter((item) => item.is_featured).slice(0, 5)
})

function applyFilters() {
    const params = new URLSearchParams()
    Object.keys(filters.value).forEach((key) => {
        if (filters.value[key]) {
            params.append(key, filters.value[key])
        }
    })
    router.visit(`${window.location.pathname}?${params.toString()}`)
}

function resetFilters() {
    filters.value = {
        type: '',
        category: '',
        genre: '',
        year: '',
        quality: '',
        rating: '',
        sort: 'featured',
        search: '',
    }
    applyFilters()
}

function changePage(url) {
    router.visit(url)
}

function viewContent(item) {
    if (item.type === 'series' || item.type === 'tv_show') {
        router.visit(`/vod/series/${item.id}`)
    } else {
        router.visit(`/vod/movie/${item.id}`)
    }
}

function getGenres(genre) {
    if (Array.isArray(genre)) return genre
    try {
        return JSON.parse(genre)
    } catch {
        return genre ? genre.split(',').map((g) => g.trim()) : []
    }
}

function getQualityIcon(quality) {
    const icons = {
        '4k': '🟣',
        fhd: '🔵',
        hd: '🟢',
        sd: '🟡',
        low: '⚪',
        mobile: '📱',
    }
    return icons[quality] || '🟡'
}

function truncateText(text, maxLength) {
    if (!text) return ''
    if (text.length <= maxLength) return text
    return text.substring(0, maxLength) + '...'
}

function handleImageError(e) {
    e.target.src = '/images/default-poster.jpg'
}
</script>

<template>
    <AppLayout>
        <div class="vod-library">
            <!-- Header -->
            <div class="library-header">
                <h1 class="page-title">VOD Library</h1>
                <div class="header-actions">
                    <div class="search-wrapper">
                        <i class="fas fa-search"></i>
                        <input
                            type="text"
                            v-model="filters.search"
                            placeholder="Search movies, series..."
                            @input="applyFilters"
                            class="search-input"
                        />
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-bar">
                <div class="filters-row">
                    <select v-model="filters.type" @change="applyFilters" class="filter-select">
                        <option value="">All Types</option>
                        <option value="movie">Movies</option>
                        <option value="series">TV Series</option>
                        <option value="documentary">Documentaries</option>
                        <option value="anime">Anime</option>
                        <option value="kids">Kids</option>
                    </select>

                    <select v-model="filters.category" @change="applyFilters" class="filter-select">
                        <option value="">All Categories</option>
                        <option v-for="category in categories" :key="category.id" :value="category.id">
                            {{ category.name }}
                        </option>
                    </select>

                    <select v-model="filters.genre" @change="applyFilters" class="filter-select">
                        <option value="">All Genres</option>
                        <option v-for="genre in genres" :key="genre" :value="genre">
                            {{ genre }}
                        </option>
                    </select>

                    <select v-model="filters.year" @change="applyFilters" class="filter-select">
                        <option value="">All Years</option>
                        <option v-for="year in years" :key="year" :value="year">
                            {{ year }}
                        </option>
                    </select>

                    <select v-model="filters.quality" @change="applyFilters" class="filter-select">
                        <option value="">All Qualities</option>
                        <option value="4k">🟣 4K</option>
                        <option value="fhd">🔵 FHD</option>
                        <option value="hd">🟢 HD</option>
                        <option value="sd">🟡 SD</option>
                    </select>

                    <select v-model="filters.rating" @change="applyFilters" class="filter-select">
                        <option value="">All Ratings</option>
                        <option value="8">8+ ★★★★★</option>
                        <option value="7">7+ ★★★★</option>
                        <option value="6">6+ ★★★</option>
                        <option value="5">5+ ★★</option>
                    </select>
                </div>

                <div class="filters-row">
                    <select v-model="filters.sort" @change="applyFilters" class="filter-select">
                        <option value="featured">Featured</option>
                        <option value="popular">Most Popular</option>
                        <option value="latest">Latest</option>
                        <option value="rating">Top Rated</option>
                        <option value="title">A-Z</option>
                    </select>
                    <button @click="resetFilters" class="btn btn-secondary btn-sm">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </div>

            <!-- Featured Banner -->
            <div v-if="vod.data.length > 0 && !filters.search" class="featured-banner">
                <div class="banner-slider">
                    <div v-for="(item, index) in featuredItems" :key="index" class="banner-item">
                        <div class="banner-backdrop" :style="{ backgroundImage: `url(${item.backdrop_url})` }">
                            <div class="banner-overlay">
                                <div class="banner-content">
                                    <span class="banner-badge">{{ item.type.toUpperCase() }}</span>
                                    <h2>{{ item.title }}</h2>
                                    <p>{{ truncateText(item.description, 200) }}</p>
                                    <div class="banner-meta">
                                        <span>{{ item.year }}</span>
                                        <span>•</span>
                                        <span>★ {{ item.rating ? Number(item.rating).toFixed(1) : 'N/A' }}</span>
                                        <span>•</span>
                                        <span>{{ item.views }} views</span>
                                    </div>
                                    <button @click="viewContent(item)" class="btn btn-primary btn-lg">
                                        <i class="fas fa-play"></i> Watch Now
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <div
                    v-for="item in vod.data"
                    :key="item.id"
                    class="content-card"
                    @click="viewContent(item)"
                >
                    <div class="card-poster">
                        <img
                            :src="item.poster_url || '/images/default-poster.jpg'"
                            :alt="item.title"
                            loading="lazy"
                            @error="handleImageError"
                        />
                        <div class="card-badges">
                            <span v-if="item.quality_level" class="badge-quality" :class="item.quality_level">
                                {{ getQualityIcon(item.quality_level) }} {{ item.quality_level.toUpperCase() }}
                            </span>
                            <span v-if="item.is_featured" class="badge-featured">⭐ Featured</span>
                            <span v-if="item.is_adult" class="badge-adult">🔞 18+</span>
                        </div>
                        <div class="card-hover">
                            <button class="btn-play">
                                <i class="fas fa-play"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-info">
                        <h4>{{ item.title }}</h4>
                        <div class="card-meta">
                            <span>{{ item.year }}</span>
                            <span>•</span>
                            <span>★ {{ item.rating ? Number(item.rating).toFixed(1) : 'N/A' }}</span>
                            <span v-if="item.type === 'series'" class="episode-count">
                                {{ item.episode_count }} episodes
                            </span>
                        </div>
                        <div class="card-genres" v-if="item.genre">
                            <span v-for="genre in getGenres(item.genre)" :key="genre" class="genre-tag">
                                {{ genre }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="vod.data.length === 0" class="empty-state">
                <div class="empty-content">
                    <i class="fas fa-film empty-icon"></i>
                    <h3>No content found</h3>
                    <p>Try adjusting your filters or search terms</p>
                    <button @click="resetFilters" class="btn btn-primary">
                        <i class="fas fa-undo"></i> Reset Filters
                    </button>
                </div>
            </div>

            <!-- Pagination -->
            <div class="pagination-container" v-if="vod.data.length > 0">
                <Pagination :links="vod.links" @page-change="changePage" />
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.vod-library {
    padding: 24px;
    max-width: 1400px;
    margin: 0 auto;
}

.library-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.page-title {
    font-size: 28px;
    font-weight: 700;
    color: #2c3e50;
    margin: 0;
}

.search-wrapper {
    position: relative;
    width: 300px;
}

.search-wrapper i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #95a5a6;
}

.search-input {
    width: 100%;
    padding: 10px 12px 10px 36px;
    border: 1px solid #d5dbdb;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s;
}

.search-input:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.filters-bar {
    background: white;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 24px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.filters-row {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 8px;
}

.filters-row:last-child {
    margin-bottom: 0;
}

.filter-select {
    padding: 8px 12px;
    border: 1px solid #d5dbdb;
    border-radius: 6px;
    font-size: 13px;
    background: white;
    min-width: 120px;
}

.filter-select:focus {
    outline: none;
    border-color: #3498db;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary {
    background: #3498db;
    color: white;
}

.btn-primary:hover {
    background: #2980b9;
}

.btn-secondary {
    background: #ecf0f1;
    color: #2c3e50;
}

.btn-secondary:hover {
    background: #d5dbdb;
}

.btn-sm {
    padding: 4px 12px;
    font-size: 12px;
}

.btn-lg {
    padding: 12px 24px;
    font-size: 16px;
}

.featured-banner {
    margin-bottom: 32px;
    border-radius: 12px;
    overflow: hidden;
    background: #1a1a2e;
}

.banner-item {
    position: relative;
    height: 400px;
}

.banner-backdrop {
    background-size: cover;
    background-position: center;
    width: 100%;
    height: 100%;
}

.banner-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(to right, rgba(0, 0, 0, 0.8) 0%, rgba(0, 0, 0, 0.4) 50%, rgba(0, 0, 0, 0.2) 100%);
    display: flex;
    align-items: center;
}

.banner-content {
    padding: 40px;
    max-width: 600px;
    color: white;
}

.banner-badge {
    display: inline-block;
    background: rgba(255, 255, 255, 0.2);
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 1px;
    margin-bottom: 12px;
}

.banner-content h2 {
    font-size: 42px;
    font-weight: 700;
    margin: 0 0 12px 0;
}

.banner-content p {
    font-size: 16px;
    opacity: 0.8;
    line-height: 1.6;
    margin-bottom: 16px;
}

.banner-meta {
    display: flex;
    gap: 12px;
    font-size: 14px;
    opacity: 0.7;
    margin-bottom: 20px;
}

.content-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 20px;
}

.content-card {
    cursor: pointer;
    border-radius: 8px;
    overflow: hidden;
    background: white;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    transition: all 0.3s;
}

.content-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.content-card:hover .card-hover {
    opacity: 1;
}

.card-poster {
    position: relative;
    aspect-ratio: 2 / 3;
    overflow: hidden;
    background: #f8f9fa;
}

.card-poster img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.card-badges {
    position: absolute;
    top: 8px;
    right: 8px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.badge-quality {
    font-size: 10px;
    padding: 2px 8px;
    border-radius: 4px;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    font-weight: 600;
}

.badge-featured {
    background: rgba(241, 196, 15, 0.9);
    color: #2c3e50;
    font-size: 10px;
    padding: 2px 8px;
    border-radius: 4px;
    font-weight: 600;
}

.badge-adult {
    background: rgba(231, 76, 60, 0.9);
    color: white;
    font-size: 10px;
    padding: 2px 8px;
    border-radius: 4px;
}

.card-hover {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s;
}

.btn-play {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: rgba(52, 152, 219, 0.9);
    color: white;
    border: none;
    font-size: 24px;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-play:hover {
    background: #3498db;
    transform: scale(1.1);
}

.card-info {
    padding: 12px;
}

.card-info h4 {
    margin: 0 0 4px 0;
    font-size: 14px;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.card-meta {
    font-size: 12px;
    color: #7f8c8d;
    display: flex;
    gap: 6px;
    align-items: center;
}

.episode-count {
    background: #f8f9fa;
    padding: 0 6px;
    border-radius: 3px;
}

.card-genres {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
    margin-top: 6px;
}

.genre-tag {
    font-size: 10px;
    padding: 1px 6px;
    border-radius: 3px;
    background: #f8f9fa;
    color: #7f8c8d;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-icon {
    font-size: 64px;
    color: #d5dbdb;
    margin-bottom: 16px;
}

.empty-state h3 {
    font-size: 20px;
    color: #2c3e50;
    margin-bottom: 8px;
}

.empty-state p {
    color: #7f8c8d;
    margin-bottom: 20px;
}

.pagination-container {
    margin-top: 32px;
    display: flex;
    justify-content: center;
}

@media (max-width: 768px) {
    .library-header {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }

    .search-wrapper {
        width: 100%;
    }

    .filters-row {
        flex-direction: column;
    }

    .filter-select {
        width: 100%;
    }

    .banner-content h2 {
        font-size: 28px;
    }

    .banner-item {
        height: 300px;
    }

    .content-grid {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 12px;
    }

    .banner-content {
        padding: 20px;
    }
}
</style>