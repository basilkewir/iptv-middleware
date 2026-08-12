<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useToast } from '@/Composables/useToast'

const props = defineProps({
    vod: Object,
    media: Object,
    episode: Object,
    watchProgress: {
        type: Number,
        default: 0,
    },
    nextEpisode: Object,
})

const toast = useToast()
const videoPlayer = ref(null)
const isPlaying = ref(false)
const currentTime = ref(0)
const duration = ref(0)
const volume = ref(1)
const isFullscreen = ref(false)
const showControls = ref(true)
const controlsTimeout = ref(null)
const progressInterval = ref(null)

const streamUrl = computed(() => {
    if (props.episode) {
        return props.episode.stream_url
    }
    return props.media?.stream_url || props.vod?.stream_url
})

const title = computed(() => {
    if (props.episode) {
        return `${props.vod.title} - ${props.episode.title}`
    }
    return props.vod.title
})

onMounted(() => {
    const video = videoPlayer.value
    if (video) {
        video.volume = volume.value

        if (props.watchProgress > 0) {
            video.currentTime = (props.watchProgress / 100) * (duration.value || 0)
        }

        video.addEventListener('loadedmetadata', () => {
            duration.value = video.duration
            if (props.watchProgress > 0) {
                video.currentTime = (props.watchProgress / 100) * video.duration
            }
        })

        video.addEventListener('timeupdate', () => {
            currentTime.value = video.currentTime
        })

        video.addEventListener('play', () => {
            isPlaying.value = true
            startProgressTracking()
        })

        video.addEventListener('pause', () => {
            isPlaying.value = false
        })

        video.addEventListener('ended', () => {
            isPlaying.value = false
            recordProgress(100)
            if (props.nextEpisode) {
                toast.info('Next episode starting soon...')
                setTimeout(() => {
                    router.visit(`/vod/episode/${props.nextEpisode.id}`)
                }, 3000)
            }
        })
    }
})

onUnmounted(() => {
    if (progressInterval.value) {
        clearInterval(progressInterval.value)
    }
    if (controlsTimeout.value) {
        clearTimeout(controlsTimeout.value)
    }
})

function togglePlay() {
    const video = videoPlayer.value
    if (video) {
        if (video.paused) {
            video.play()
        } else {
            video.pause()
        }
    }
}

function toggleFullscreen() {
    const video = videoPlayer.value
    if (video) {
        if (!document.fullscreenElement) {
            video.requestFullscreen()
            isFullscreen.value = true
        } else {
            document.exitFullscreen()
            isFullscreen.value = false
        }
    }
}

function setVolume(v) {
    volume.value = v
    if (videoPlayer.value) {
        videoPlayer.value.volume = v
    }
}

function seek(e) {
    const video = videoPlayer.value
    if (video) {
        const rect = e.target.getBoundingClientRect()
        const percent = (e.clientX - rect.left) / rect.width
        video.currentTime = percent * video.duration
    }
}

function formatTime(seconds) {
    if (!seconds || isNaN(seconds)) return '0:00'
    const h = Math.floor(seconds / 3600)
    const m = Math.floor((seconds % 3600) / 60)
    const s = Math.floor(seconds % 60)
    if (h > 0) {
        return `${h}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`
    }
    return `${m}:${s.toString().padStart(2, '0')}`
}

function startProgressTracking() {
    if (progressInterval.value) {
        clearInterval(progressInterval.value)
    }
    progressInterval.value = setInterval(() => {
        const video = videoPlayer.value
        if (video && video.duration > 0) {
            const progress = (video.currentTime / video.duration) * 100
            recordProgress(Math.round(progress))
        }
    }, 30000) // Record every 30 seconds
}

async function recordProgress(progress) {
    try {
        await axios.post('/vod/progress', {
            vod_id: props.vod.id,
            progress: progress,
            duration: Math.round(currentTime.value),
            episode_id: props.episode?.id || null,
        })
    } catch (error) {
        // Silent fail for progress tracking
    }
}

function showControlsTemporarily() {
    showControls.value = true
    if (controlsTimeout.value) {
        clearTimeout(controlsTimeout.value)
    }
    controlsTimeout.value = setTimeout(() => {
        if (isPlaying.value) {
            showControls.value = false
        }
    }, 3000)
}

function playNext() {
    if (props.nextEpisode) {
        router.visit(`/vod/episode/${props.nextEpisode.id}`)
    }
}

function goBack() {
    if (props.episode) {
        router.visit(`/vod/series/${props.vod.id}`)
    } else {
        router.visit(`/vod/movie/${props.vod.id}`)
    }
}
</script>

<template>
    <AppLayout>
        <div class="player-page" @mousemove="showControlsTemporarily">
            <!-- Back Button -->
            <button @click="goBack" class="back-btn" :class="{ hidden: !showControls }">
                <i class="fas fa-arrow-left"></i> Back
            </button>

            <!-- Video Player -->
            <div class="player-container">
                <video
                    ref="videoPlayer"
                    :src="streamUrl"
                    :poster="vod.backdrop_url"
                    @click="togglePlay"
                    autoplay
                    class="video-element"
                ></video>

                <!-- Custom Controls -->
                <div class="video-controls" :class="{ hidden: !showControls }">
                    <!-- Progress Bar -->
                    <div class="progress-bar" @click="seek">
                        <div
                            class="progress-buffer"
                            :style="{ width: `${(currentTime / duration) * 100}%` }"
                        ></div>
                    </div>

                    <!-- Control Buttons -->
                    <div class="controls-row">
                        <div class="controls-left">
                            <button @click="togglePlay" class="control-btn">
                                <i :class="isPlaying ? 'fas fa-pause' : 'fas fa-play'"></i>
                            </button>
                            <button @click="goBack" class="control-btn">
                                <i class="fas fa-step-backward"></i>
                            </button>
                            <button
                                v-if="nextEpisode"
                                @click="playNext"
                                class="control-btn"
                            >
                                <i class="fas fa-step-forward"></i>
                            </button>
                            <div class="volume-control">
                                <button class="control-btn">
                                    <i class="fas fa-volume-up"></i>
                                </button>
                                <input
                                    type="range"
                                    min="0"
                                    max="1"
                                    step="0.1"
                                    v-model="volume"
                                    @input="setVolume(parseFloat($event.target.value))"
                                    class="volume-slider"
                                />
                            </div>
                            <span class="time-display">
                                {{ formatTime(currentTime) }} / {{ formatTime(duration) }}
                            </span>
                        </div>

                        <div class="controls-right">
                            <span class="title-display">{{ title }}</span>
                            <button @click="toggleFullscreen" class="control-btn">
                                <i :class="isFullscreen ? 'fas fa-compress' : 'fas fa-expand'"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Next Episode Banner -->
            <div v-if="nextEpisode && !isPlaying" class="next-episode-banner">
                <div class="next-content">
                    <h3>Up Next</h3>
                    <p>{{ nextEpisode.title }}</p>
                    <button @click="playNext" class="btn btn-primary">
                        <i class="fas fa-play"></i> Play Next
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.player-page {
    position: relative;
    background: #000;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.back-btn {
    position: absolute;
    top: 20px;
    left: 20px;
    z-index: 10;
    padding: 8px 16px;
    background: rgba(0, 0, 0, 0.5);
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: opacity 0.3s;
}

.back-btn.hidden {
    opacity: 0;
    pointer-events: none;
}

.player-container {
    position: relative;
    width: 100%;
    max-width: 1200px;
    aspect-ratio: 16 / 9;
}

.video-element {
    width: 100%;
    height: 100%;
    object-fit: contain;
    background: #000;
}

.video-controls {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.8) 0%, rgba(0, 0, 0, 0) 100%);
    padding: 16px;
    transition: opacity 0.3s;
}

.video-controls.hidden {
    opacity: 0;
    pointer-events: none;
}

.progress-bar {
    height: 6px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
    cursor: pointer;
    margin-bottom: 12px;
    overflow: hidden;
}

.progress-buffer {
    height: 100%;
    background: #3498db;
    transition: width 0.1s;
}

.controls-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.controls-left,
.controls-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.control-btn {
    background: none;
    border: none;
    color: white;
    font-size: 18px;
    cursor: pointer;
    padding: 4px 8px;
    transition: color 0.2s;
}

.control-btn:hover {
    color: #3498db;
}

.volume-control {
    display: flex;
    align-items: center;
    gap: 8px;
}

.volume-slider {
    width: 80px;
    height: 4px;
    -webkit-appearance: none;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 2px;
    outline: none;
}

.volume-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 12px;
    height: 12px;
    background: white;
    border-radius: 50%;
    cursor: pointer;
}

.time-display {
    color: white;
    font-size: 13px;
}

.title-display {
    color: white;
    font-size: 14px;
    font-weight: 500;
}

.next-episode-banner {
    position: absolute;
    bottom: 80px;
    right: 20px;
    background: rgba(0, 0, 0, 0.8);
    border-radius: 8px;
    padding: 16px;
    max-width: 300px;
}

.next-content h3 {
    color: #3498db;
    font-size: 14px;
    margin: 0 0 8px 0;
}

.next-content p {
    color: white;
    font-size: 16px;
    margin: 0 0 12px 0;
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
}

.btn-primary {
    background: #3498db;
    color: white;
}

.btn-primary:hover {
    background: #2980b9;
}

@media (max-width: 768px) {
    .player-container {
        aspect-ratio: auto;
        height: 100vh;
    }

    .volume-slider {
        width: 50px;
    }

    .title-display {
        display: none;
    }
}
</style>