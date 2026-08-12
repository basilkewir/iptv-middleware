<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useToast } from '@/Composables/useToast'

const props = defineProps({
    series: Object,
    similar: Array,
})

const toast = useToast()
const selectedSeason = ref(props.series.seasons?.[0]?.id || null)

function getGenres(genre) {
    if (Array.isArray(genre)) return genre
    try {
        return JSON.parse(genre)
    } catch {
        return genre ? genre.split(',').map((g) => g.trim()) : []
    }
}

function playEpisode(episode) {
    router.visit(`/vod/episode/${episode.id}`)
}

async function toggleFavorite() {
    try {
        const response = await axios.post('/vod/toggle-favorite', {
            vod_id: props.series.id,
        })
        props.series.is_favorite = response.data.is_favorite
        toast.success(response.data.message)
    } catch (error) {
        toast.error('Failed to update favorite')
    }
}

async function toggleWatchlist() {
    try {
        const response = await axios.post('/vod/toggle-watchlist', {
            vod_id: props.series.id,
        })
        props.series.is_watchlisted = response.data.is_watchlisted
        toast.success(response.data.message)
    } catch (error) {
        toast.error('Failed to update watchlist')
    }
}

function viewContent(item) {
    if (item.type === 'series' || item.type === 'tv_show') {
        router.visit(`/vod/series/${item.id}`)
    } else {
        router.visit(`/vod/movie/${item.id}`)
    }
}

function handleImageError(e) {
    e.target.src = '/images/default-poster.jpg'
}
</script>

<template>
    <AppLayout>
        <div class="series-details">
            <!-- Hero Section -->
            <div class="hero-section" :style="{ backgroundImage: `url(${series.backdrop_url})` }">
                <div class="hero-overlay">
                    <div class="hero-content">
                        <div class="hero-poster">
                            <img :src="series.poster_url || '/images/default-poster.jpg'" :alt="series.title" />
                        </div>
                        <div class="hero-info">
                            <span class="content-type">{{ series.type.toUpperCase() }}</span>
                            <h1>{{ series.title }}</h1>
                            <div class="meta-info">
                                <span>{{ series.year }}</span>
                                <span>•</span>
                                <span>{{ series.season_count }} seasons</span>
                                <span>•</span>
                                <span>{{ series.episode_count }} episodes</span>
                                <span>•</span>
                                <span>★ {{ series.rating ? Number(series.rating).toFixed(1) : 'N/A' }}</span>
                            </div>
                            <p class="description">{{ series.description }}</p>
                            <div class="genres" v-if="series.genre">
                                <span v-for="genre in getGenres(series.genre)" :key="genre" class="genre-tag">
                                    {{ genre }}
                                </span>
                            </div>
                            <div class="action-buttons">
                                <button @click="toggleFavorite" class="btn btn-secondary btn-lg">
                                    <i :class="series.is_favorite ? 'fas fa-heart' : 'far fa-heart'"></i>
                                    {{ series.is_favorite ? 'Favorited' : 'Add to Favorites' }}
                                </button>
                                <button @click="toggleWatchlist" class="btn btn-secondary btn-lg">
                                    <i :class="series.is_watchlisted ? 'fas fa-bookmark' : 'far fa-bookmark'"></i>
                                    {{ series.is_watchlisted ? 'In Watchlist' : 'Add to Watchlist' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Series Progress -->
            <div v-if="series.series_progress !== undefined" class="progress-section">
                <div class="progress-bar">
                    <div class="progress-fill" :style="{ width: `${series.series_progress}%` }"></div>
                </div>
                <span class="progress-text">{{ series.series_progress }}% completed</span>
            </div>

            <!-- Seasons & Episodes -->
            <div class="seasons-section">
                <div class="season-tabs">
                    <button
                        v-for="season in series.seasons"
                        :key="season.id"
                        @click="selectedSeason = season.id"
                        :class="['season-tab', { active: selectedSeason === season.id }]"
                    >
                        Season {{ season.season_number }}
                    </button>
                </div>

                <div class="episodes-list">
                    <template v-for="season in series.seasons" :key="season.id">
                        <div v-show="selectedSeason === season.id" class="episodes-grid">
                            <div
                                v-for="episode in season.episodes"
                                :key="episode.id"
                                class="episode-card"
                                @click="playEpisode(episode)"
                            >
                                <div class="episode-thumbnail">
                                    <img
                                        :src="episode.thumbnail_url || '/images/default-episode.jpg'"
                                        :alt="episode.title"
                                        @error="handleImageError"
                                    />
                                    <div class="episode-hover">
                                        <i class="fas fa-play"></i>
                                    </div>
                                    <div
                                        v-if="episode.watch_progress > 0"
                                        class="episode-progress"
                                    >
                                        <div
                                            class="progress-fill"
                                            :style="{ width: `${episode.watch_progress}%` }"
                                        ></div>
                                    </div>
                                </div>
                                <div class="episode-info">
                                    <span class="episode-number">E{{ episode.episode_number }}</span>
                                    <h4>{{ episode.title }}</h4>
                                    <p class="episode-description">{{ episode.description }}</p>
                                    <div class="episode-meta">
                                        <span v-if="episode.duration">{{ episode.duration }} min</span>
                                        <span v-if="episode.air_date">{{ episode.air_date }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Similar Content -->
            <div class="similar-section" v-if="similar && similar.length > 0">
                <h2>You Might Also Like</h2>
                <div class="similar-grid">
                    <div
                        v-for="item in similar"
                        :key="item.id"
                        class="similar-card"
                        @click="viewContent(item)"
                    >
                        <div class="similar-poster">
                            <img :src="item.poster_url || '/images/default-poster.jpg'" :alt="item.title" />
                        </div>
                        <div class="similar-info">
                            <span class="similar-title">{{ item.title }}</span>
                            <span class="similar-year">{{ item.year }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.series-details {
    max-width: 1200px;
    margin: 0 auto;
}

.hero-section {
    background-size: cover;
    background-position: center;
    border-radius: 12px;
    overflow: hidden;
    margin: 0 20px 24px;
}

.hero-overlay {
    background: linear-gradient(to right, rgba(0, 0, 0, 0.9) 0%, rgba(0, 0, 0, 0.5) 100%);
    padding: 40px;
}

.hero-content {
    display: flex;
    gap: 40px;
    max-width: 1000px;
}

.hero-poster {
    flex: 0 0 300px;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
}

.hero-poster img {
    width: 100%;
    display: block;
}

.hero-info {
    flex: 1;
    color: white;
}

.content-type {
    display: inline-block;
    background: rgba(255, 255, 255, 0.1);
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 1px;
    margin-bottom: 12px;
}

.hero-info h1 {
    font-size: 36px;
    font-weight: 700;
    margin: 0 0 8px 0;
}

.meta-info {
    display: flex;
    gap: 12px;
    font-size: 14px;
    opacity: 0.7;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.description {
    font-size: 16px;
    line-height: 1.6;
    opacity: 0.8;
    margin-bottom: 16px;
}

.genres {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.genre-tag {
    padding: 4px 12px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.1);
    font-size: 13px;
}

.action-buttons {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: white;
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}

.btn-lg {
    padding: 12px 24px;
    font-size: 16px;
}

.progress-section {
    padding: 0 20px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.progress-bar {
    flex: 1;
    height: 8px;
    background: #ecf0f1;
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: #3498db;
    transition: width 0.3s;
}

.progress-text {
    font-size: 14px;
    color: #7f8c8d;
    white-space: nowrap;
}

.seasons-section {
    padding: 0 20px;
    margin-bottom: 40px;
}

.season-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.season-tab {
    padding: 8px 16px;
    border: 1px solid #d5dbdb;
    border-radius: 6px;
    background: white;
    color: #2c3e50;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 14px;
}

.season-tab:hover {
    background: #ecf0f1;
}

.season-tab.active {
    background: #3498db;
    color: white;
    border-color: #3498db;
}

.episodes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
}

.episode-card {
    cursor: pointer;
    border-radius: 8px;
    overflow: hidden;
    background: white;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    transition: all 0.3s;
}

.episode-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.episode-thumbnail {
    position: relative;
    aspect-ratio: 16 / 9;
    overflow: hidden;
    background: #f8f9fa;
}

.episode-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.episode-hover {
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

.episode-card:hover .episode-hover {
    opacity: 1;
}

.episode-hover i {
    font-size: 32px;
    color: white;
}

.episode-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: rgba(0, 0, 0, 0.3);
}

.episode-info {
    padding: 12px;
}

.episode-number {
    font-size: 12px;
    color: #3498db;
    font-weight: 600;
}

.episode-info h4 {
    margin: 4px 0;
    font-size: 14px;
    font-weight: 600;
}

.episode-description {
    font-size: 12px;
    color: #7f8c8d;
    margin: 4px 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.episode-meta {
    display: flex;
    gap: 12px;
    font-size: 11px;
    color: #95a5a6;
}

.similar-section {
    padding: 0 20px;
    margin-bottom: 40px;
}

.similar-section h2 {
    font-size: 24px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 20px;
}

.similar-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 16px;
}

.similar-card {
    cursor: pointer;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s;
}

.similar-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.similar-poster {
    aspect-ratio: 2 / 3;
    overflow: hidden;
}

.similar-poster img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.similar-info {
    padding: 8px;
}

.similar-title {
    font-weight: 500;
    font-size: 13px;
    display: block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.similar-year {
    font-size: 12px;
    color: #7f8c8d;
}

@media (max-width: 768px) {
    .hero-content {
        flex-direction: column;
        align-items: center;
    }

    .hero-poster {
        flex: 0 0 auto;
        max-width: 200px;
    }

    .hero-info h1 {
        font-size: 24px;
    }

    .episodes-grid {
        grid-template-columns: 1fr;
    }
}
</style>