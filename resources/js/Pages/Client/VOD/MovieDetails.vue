<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Common/Modal.vue'
import { useToast } from '@/Composables/useToast'

const props = defineProps({
    movie: Object,
    similar: Array,
})

const toast = useToast()
const showReviewModal = ref(false)
const reviewData = ref({
    rating: 0,
    title: '',
    comment: '',
})

function getGenres(genre) {
    if (Array.isArray(genre)) return genre
    try {
        return JSON.parse(genre)
    } catch {
        return genre ? genre.split(',').map((g) => g.trim()) : []
    }
}

function formatDate(date) {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}

function playMovie() {
    router.visit(`/vod/play/${props.movie.id}`)
}

async function toggleFavorite() {
    try {
        const response = await axios.post('/vod/toggle-favorite', {
            vod_id: props.movie.id,
        })
        props.movie.is_favorite = response.data.is_favorite
        toast.success(response.data.message)
    } catch (error) {
        toast.error('Failed to update favorite')
    }
}

async function toggleWatchlist() {
    try {
        const response = await axios.post('/vod/toggle-watchlist', {
            vod_id: props.movie.id,
        })
        props.movie.is_watchlisted = response.data.is_watchlisted
        toast.success(response.data.message)
    } catch (error) {
        toast.error('Failed to update watchlist')
    }
}

function shareMovie() {
    if (navigator.share) {
        navigator.share({
            title: props.movie.title,
            text: `Check out ${props.movie.title} on our IPTV platform!`,
            url: window.location.href,
        })
    } else {
        navigator.clipboard.writeText(window.location.href)
        toast.success('Link copied to clipboard!')
    }
}

function openReviewModal() {
    reviewData.value = { rating: 0, title: '', comment: '' }
    showReviewModal.value = true
}

async function submitReview() {
    if (reviewData.value.rating === 0) {
        toast.error('Please select a rating')
        return
    }

    try {
        await axios.post('/vod/review', {
            vod_id: props.movie.id,
            rating: reviewData.value.rating,
            title: reviewData.value.title,
            comment: reviewData.value.comment,
        })
        toast.success('Review submitted successfully!')
        showReviewModal.value = false
        router.reload()
    } catch (error) {
        toast.error('Failed to submit review')
    }
}

function viewContent(item) {
    if (item.type === 'series' || item.type === 'tv_show') {
        router.visit(`/vod/series/${item.id}`)
    } else {
        router.visit(`/vod/movie/${item.id}`)
    }
}

function handleAvatarError(e) {
    e.target.src = '/images/default-avatar.jpg'
}
</script>

<template>
    <AppLayout>
        <div class="movie-details">
            <!-- Hero Section -->
            <div class="hero-section" :style="{ backgroundImage: `url(${movie.backdrop_url})` }">
                <div class="hero-overlay">
                    <div class="hero-content">
                        <div class="hero-poster">
                            <img :src="movie.poster_url || '/images/default-poster.jpg'" :alt="movie.title" />
                        </div>
                        <div class="hero-info">
                            <span class="content-type">{{ movie.type.toUpperCase() }}</span>
                            <h1>{{ movie.title }}</h1>
                            <div class="meta-info">
                                <span>{{ movie.year }}</span>
                                <span>•</span>
                                <span>{{ movie.duration }} min</span>
                                <span>•</span>
                                <span>★ {{ movie.rating ? Number(movie.rating).toFixed(1) : 'N/A' }}</span>
                                <span>•</span>
                                <span>{{ movie.views }} views</span>
                            </div>
                            <p class="description">{{ movie.description }}</p>
                            <div class="genres" v-if="movie.genre">
                                <span v-for="genre in getGenres(movie.genre)" :key="genre" class="genre-tag">
                                    {{ genre }}
                                </span>
                            </div>
                            <div class="action-buttons">
                                <button @click="playMovie" class="btn btn-primary btn-lg">
                                    <i class="fas fa-play"></i> Watch Now
                                </button>
                                <button @click="toggleFavorite" class="btn btn-secondary btn-lg">
                                    <i :class="movie.is_favorite ? 'fas fa-heart' : 'far fa-heart'"></i>
                                </button>
                                <button @click="toggleWatchlist" class="btn btn-secondary btn-lg">
                                    <i :class="movie.is_watchlisted ? 'fas fa-bookmark' : 'far fa-bookmark'"></i>
                                </button>
                                <button @click="shareMovie" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-share-alt"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cast & Crew -->
            <div class="cast-section" v-if="movie.cast && movie.cast.length > 0">
                <h2>Cast & Crew</h2>
                <div class="cast-grid">
                    <div v-for="person in movie.cast" :key="person.id" class="cast-card">
                        <img
                            :src="person.person?.profile_url || '/images/default-avatar.jpg'"
                            :alt="person.person?.name"
                            @error="handleAvatarError"
                        />
                        <div class="cast-info">
                            <span class="cast-name">{{ person.person?.name }}</span>
                            <span class="cast-character">{{ person.character_name }}</span>
                        </div>
                    </div>
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
                            <div class="similar-hover">
                                <i class="fas fa-play"></i>
                            </div>
                        </div>
                        <div class="similar-info">
                            <span class="similar-title">{{ item.title }}</span>
                            <span class="similar-year">{{ item.year }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reviews -->
            <div class="reviews-section">
                <h2>Reviews</h2>
                <div class="review-summary">
                    <div class="average-rating">
                        <span class="rating-number">{{ movie.rating ? Number(movie.rating).toFixed(1) : 'N/A' }}</span>
                        <span class="rating-stars">
                            <i
                                v-for="n in 5"
                                :key="n"
                                class="fas fa-star"
                                :class="{ active: n <= Math.round(movie.rating || 0) }"
                            ></i>
                        </span>
                        <span class="rating-count">{{ movie.rating_count || 0 }} reviews</span>
                    </div>
                    <button @click="openReviewModal" class="btn btn-primary">
                        <i class="fas fa-pen"></i> Write Review
                    </button>
                </div>

                <div class="reviews-list" v-if="movie.reviews && movie.reviews.length > 0">
                    <div v-for="review in movie.reviews" :key="review.id" class="review-item">
                        <div class="review-header">
                            <div class="review-user">
                                <span class="user-name">{{ review.user?.name || 'Anonymous' }}</span>
                                <span class="review-date">{{ formatDate(review.created_at) }}</span>
                            </div>
                            <div class="review-rating">
                                <i
                                    v-for="n in 5"
                                    :key="n"
                                    class="fas fa-star"
                                    :class="{ active: n <= review.rating }"
                                ></i>
                            </div>
                        </div>
                        <div class="review-content">
                            <h4 v-if="review.title">{{ review.title }}</h4>
                            <p>{{ review.comment }}</p>
                        </div>
                    </div>
                </div>
                <div v-else class="no-reviews">
                    <p>No reviews yet. Be the first to review!</p>
                </div>
            </div>

            <!-- Review Modal -->
            <Modal :show="showReviewModal" @close="showReviewModal = false" title="Write a Review">
                <form @submit.prevent="submitReview">
                    <div class="form-group">
                        <label>Your Rating</label>
                        <div class="star-rating">
                            <button
                                v-for="n in 5"
                                :key="n"
                                type="button"
                                @click="reviewData.rating = n"
                                class="star-btn"
                            >
                                <i class="fas fa-star" :class="{ active: n <= reviewData.rating }"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" v-model="reviewData.title" class="form-input" />
                    </div>
                    <div class="form-group">
                        <label>Comment</label>
                        <textarea v-model="reviewData.comment" class="form-textarea" rows="4"></textarea>
                    </div>
                    <div class="modal-actions">
                        <button type="button" @click="showReviewModal = false" class="btn btn-secondary">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">Submit Review</button>
                    </div>
                </form>
            </Modal>
        </div>
    </AppLayout>
</template>

<style scoped>
.movie-details {
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

.btn-primary {
    background: #3498db;
    color: white;
}

.btn-primary:hover {
    background: #2980b9;
    transform: translateY(-2px);
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

.cast-section {
    padding: 0 20px;
    margin-bottom: 40px;
}

.cast-section h2 {
    font-size: 24px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 20px;
}

.cast-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 16px;
}

.cast-card {
    text-align: center;
}

.cast-card img {
    width: 100%;
    aspect-ratio: 1;
    object-fit: cover;
    border-radius: 50%;
    margin-bottom: 8px;
}

.cast-info {
    display: flex;
    flex-direction: column;
}

.cast-name {
    font-weight: 600;
    font-size: 13px;
}

.cast-character {
    font-size: 12px;
    color: #7f8c8d;
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
    position: relative;
    aspect-ratio: 2 / 3;
    overflow: hidden;
}

.similar-poster img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.similar-hover {
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

.similar-card:hover .similar-hover {
    opacity: 1;
}

.similar-hover i {
    font-size: 32px;
    color: white;
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

.reviews-section {
    padding: 0 20px;
    margin-bottom: 40px;
}

.reviews-section h2 {
    font-size: 24px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 20px;
}

.review-summary {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 20px;
}

.average-rating {
    display: flex;
    align-items: center;
    gap: 12px;
}

.rating-number {
    font-size: 32px;
    font-weight: 700;
    color: #2c3e50;
}

.rating-stars {
    color: #d5dbdb;
}

.rating-stars .active {
    color: #f1c40f;
}

.rating-count {
    color: #7f8c8d;
    font-size: 14px;
}

.reviews-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.review-item {
    padding: 16px;
    border: 1px solid #ecf0f1;
    border-radius: 8px;
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.review-user {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-name {
    font-weight: 600;
}

.review-date {
    font-size: 12px;
    color: #7f8c8d;
}

.review-rating {
    color: #d5dbdb;
}

.review-rating .active {
    color: #f1c40f;
}

.review-content h4 {
    margin: 0 0 4px 0;
    font-size: 16px;
}

.review-content p {
    margin: 0;
    color: #2c3e50;
}

.no-reviews {
    text-align: center;
    color: #7f8c8d;
    padding: 40px;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: #2c3e50;
}

.form-input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d5dbdb;
    border-radius: 6px;
    font-size: 14px;
}

.form-textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d5dbdb;
    border-radius: 6px;
    font-size: 14px;
    resize: vertical;
}

.star-rating {
    display: flex;
    gap: 8px;
}

.star-btn {
    background: none;
    border: none;
    font-size: 32px;
    cursor: pointer;
    color: #d5dbdb;
    transition: all 0.2s;
}

.star-btn:hover {
    transform: scale(1.1);
}

.star-btn .active {
    color: #f1c40f;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid #ecf0f1;
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

    .action-buttons {
        flex-wrap: wrap;
        justify-content: center;
    }

    .cast-grid {
        grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    }

    .review-summary {
        flex-direction: column;
        gap: 16px;
    }
}
</style>