<?php

namespace App\Services\VOD;

use App\Models\VODContent;
use App\Models\VODSeason;
use App\Models\VODEpisode;
use App\Models\VODMedia;
use App\Models\VODWatchHistory;
use App\Models\VODWatchlist;
use App\Models\VODReview;
use App\Models\VODFavorite;
use App\Models\VODPerson;
use App\Models\VODCast;
use App\Models\VODCrew;
use App\Services\TMDB\TMDBService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class VODService
{
    protected TMDBService $tmdbService;
    protected $qualityService;
    protected int $cacheTTL = 3600;

    public function __construct(TMDBService $tmdbService, $qualityService = null)
    {
        $this->tmdbService = $tmdbService;
        $this->qualityService = $qualityService;
    }

    /**
     * Get VOD content with all relationships
     */
    public function getVOD($id, $includeRelations = ['seasons', 'categories', 'cast', 'crew'])
    {
        $query = VODContent::query();

        foreach ($includeRelations as $relation) {
            if ($relation === 'seasons') {
                $query->with(['seasons' => function ($q) {
                    $q->orderBy('season_number');
                    $q->with(['episodes' => function ($q2) {
                        $q2->orderBy('episode_number');
                    }]);
                }]);
            } else {
                $query->with($relation);
            }
        }

        return $query->findOrFail($id);
    }

    /**
     * Get movie details
     */
    public function getMovie($id, $userId = null)
    {
        $movie = $this->getVOD($id, ['categories', 'cast.person', 'crew.person', 'vodMedia']);

        if ($userId) {
            $movie->watch_progress = $this->getWatchProgress($userId, $id);
            $movie->is_favorite = $this->isFavorite($userId, $id);
            $movie->is_watchlisted = $this->isWatchlisted($userId, $id);
            $movie->user_rating = $this->getUserRating($userId, $id);
        }

        $movie->statistics = $this->getStatistics($id);
        $movie->similar = $this->getSimilarContent($id);

        return $movie;
    }

    /**
     * Get series details with seasons and episodes
     */
    public function getSeries($id, $userId = null)
    {
        $series = $this->getVOD($id, [
            'seasons' => function ($q) {
                $q->orderBy('season_number');
                $q->with(['episodes' => function ($q2) {
                    $q2->orderBy('episode_number');
                }]);
            },
            'categories',
            'cast.person',
            'crew.person',
        ]);

        if ($userId) {
            $series->is_favorite = $this->isFavorite($userId, $id);
            $series->is_watchlisted = $this->isWatchlisted($userId, $id);

            foreach ($series->seasons as $season) {
                foreach ($season->episodes as $episode) {
                    $episode->watch_progress = $this->getEpisodeWatchProgress($userId, $episode->id);
                }
            }

            $series->series_progress = $this->getSeriesProgress($userId, $id);
        }

        $series->statistics = $this->getStatistics($id);
        $series->similar = $this->getSimilarContent($id);

        return $series;
    }

    /**
     * Create new VOD content
     */
    public function createVOD(array $data, $files = null)
    {
        try {
            DB::beginTransaction();

            $vod = VODContent::create([
                'title' => $data['title'],
                'original_title' => $data['original_title'] ?? null,
                'slug' => Str::slug($data['title']),
                'description' => $data['description'] ?? null,
                'year' => $data['release_year'] ?? $data['year'] ?? null,
                'type' => $data['content_type'] ?? $data['type'] ?? 'movie',
                'duration' => $data['duration'] ?? null,
                'director' => $data['director'] ?? null,
                'cast' => $data['cast'] ?? null,
                'genre' => $data['genre'] ?? null,
                'country' => $data['country'] ?? null,
                'language' => $data['language'] ?? null,
                'rating' => $data['rating'] ?? null,
                'age_rating' => $data['age_rating'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'is_featured' => $data['is_featured'] ?? false,
                'is_adult' => $data['is_adult'] ?? false,
                'released_at' => $data['released_at'] ?? null,
            ]);

            if (isset($files['poster'])) {
                $vod->poster_url = $this->uploadImage($files['poster'], 'posters', $vod->id);
                $vod->save();
            }

            if (isset($files['backdrop'])) {
                $vod->backdrop_url = $this->uploadImage($files['backdrop'], 'backdrops', $vod->id);
                $vod->save();
            }

            if (isset($files['banner'])) {
                $vod->banner_url = $this->uploadImage($files['banner'], 'banners', $vod->id);
                $vod->save();
            }

            if (isset($data['categories'])) {
                $vod->categories()->sync($data['categories']);
            }

            if (isset($files['media'])) {
                foreach ($files['media'] as $file) {
                    $media = $this->uploadMedia($file, $vod->id, 'movie');
                    $this->detectQuality($media);
                }
            }

            if (($data['content_type'] ?? $data['type'] ?? '') === 'series' && isset($data['seasons'])) {
                foreach ($data['seasons'] as $seasonData) {
                    $season = $this->createSeason($vod->id, $seasonData);

                    if (isset($seasonData['episodes'])) {
                        foreach ($seasonData['episodes'] as $episodeData) {
                            $this->createEpisode($season->id, $episodeData);
                        }
                    }
                }
            }

            if (($data['auto_fetch_tmdb'] ?? false) && isset($data['tmdb_id'])) {
                $this->importTMDBData($vod, $data['tmdb_id']);
            }

            DB::commit();

            return $vod;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Create season for series
     */
    public function createSeason($vodId, array $data)
    {
        return VODSeason::create([
            'vod_content_id' => $vodId,
            'season_number' => $data['season_number'],
            'title' => $data['title'] ?? "Season {$data['season_number']}",
            'description' => $data['description'] ?? null,
            'poster_url' => isset($data['poster']) ? $this->uploadImage($data['poster'], 'season-posters', $vodId) : null,
            'season_year' => $data['season_year'] ?? null,
            'air_date' => $data['air_date'] ?? null,
        ]);
    }

    /**
     * Create episode for season
     */
    public function createEpisode($seasonId, array $data)
    {
        $episode = VODEpisode::create([
            'season_id' => $seasonId,
            'episode_number' => $data['episode_number'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'duration' => $data['duration'] ?? null,
            'thumbnail_url' => isset($data['thumbnail']) ? $this->uploadImage($data['thumbnail'], 'episode-thumbnails', $seasonId) : null,
            'stream_url' => $data['stream_url'] ?? null,
            'air_date' => $data['air_date'] ?? null,
            'director' => $data['director'] ?? null,
            'writer' => $data['writer'] ?? null,
            'guest_stars' => $data['guest_stars'] ?? null,
            'is_available' => $data['is_available'] ?? true,
        ]);

        if (isset($data['media_file'])) {
            $media = $this->uploadMedia($data['media_file'], $episode->id, 'episode');
            $this->detectQuality($media);
            $episode->stream_url = $media->stream_url;
            $episode->file_size = $media->file_size;
            $episode->save();
        }

        return $episode;
    }

    /**
     * Update VOD content
     */
    public function updateVOD($id, array $data, $files = null)
    {
        try {
            DB::beginTransaction();

            $vod = VODContent::findOrFail($id);

            $vod->update([
                'title' => $data['title'] ?? $vod->title,
                'original_title' => $data['original_title'] ?? $vod->original_title,
                'slug' => isset($data['title']) ? Str::slug($data['title']) : $vod->slug,
                'description' => $data['description'] ?? $vod->description,
                'year' => $data['release_year'] ?? $data['year'] ?? $vod->year,
                'type' => $data['content_type'] ?? $data['type'] ?? $vod->type,
                'duration' => $data['duration'] ?? $vod->duration,
                'director' => $data['director'] ?? $vod->director,
                'cast' => $data['cast'] ?? $vod->cast,
                'genre' => $data['genre'] ?? $vod->genre,
                'country' => $data['country'] ?? $vod->country,
                'language' => $data['language'] ?? $vod->language,
                'rating' => $data['rating'] ?? $vod->rating,
                'age_rating' => $data['age_rating'] ?? $vod->age_rating,
                'is_active' => $data['is_active'] ?? $vod->is_active,
                'is_featured' => $data['is_featured'] ?? $vod->is_featured,
                'is_adult' => $data['is_adult'] ?? $vod->is_adult,
            ]);

            if (isset($files['poster'])) {
                $this->deleteImage($vod->poster_url);
                $vod->poster_url = $this->uploadImage($files['poster'], 'posters', $vod->id);
                $vod->save();
            }

            if (isset($files['backdrop'])) {
                $this->deleteImage($vod->backdrop_url);
                $vod->backdrop_url = $this->uploadImage($files['backdrop'], 'backdrops', $vod->id);
                $vod->save();
            }

            if (isset($files['banner'])) {
                $this->deleteImage($vod->banner_url);
                $vod->banner_url = $this->uploadImage($files['banner'], 'banners', $vod->id);
                $vod->save();
            }

            if (isset($data['categories'])) {
                $vod->categories()->sync($data['categories']);
            }

            $type = $data['content_type'] ?? $data['type'] ?? $vod->type;
            if ($type === 'series' && isset($data['seasons'])) {
                foreach ($data['seasons'] as $seasonData) {
                    if (isset($seasonData['id'])) {
                        $season = VODSeason::find($seasonData['id']);
                        if ($season) {
                            $this->updateSeason($season->id, $seasonData);
                        }
                    } else {
                        $this->createSeason($vod->id, $seasonData);
                    }
                }
            }

            DB::commit();

            return $vod;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update season
     */
    public function updateSeason($id, array $data)
    {
        $season = VODSeason::findOrFail($id);

        $season->update([
            'title' => $data['title'] ?? $season->title,
            'description' => $data['description'] ?? $season->description,
            'season_year' => $data['season_year'] ?? $season->season_year,
            'air_date' => $data['air_date'] ?? $season->air_date,
        ]);

        if (isset($data['poster'])) {
            $this->deleteImage($season->poster_url);
            $season->poster_url = $this->uploadImage($data['poster'], 'season-posters', $season->vod_content_id);
            $season->save();
        }

        if (isset($data['episodes'])) {
            foreach ($data['episodes'] as $episodeData) {
                if (isset($episodeData['id'])) {
                    $this->updateEpisode($episodeData['id'], $episodeData);
                } else {
                    $this->createEpisode($season->id, $episodeData);
                }
            }
        }

        return $season;
    }

    /**
     * Update episode
     */
    public function updateEpisode($id, array $data)
    {
        $episode = VODEpisode::findOrFail($id);

        $episode->update([
            'title' => $data['title'] ?? $episode->title,
            'description' => $data['description'] ?? $episode->description,
            'duration' => $data['duration'] ?? $episode->duration,
            'air_date' => $data['air_date'] ?? $episode->air_date,
            'director' => $data['director'] ?? $episode->director,
            'writer' => $data['writer'] ?? $episode->writer,
            'guest_stars' => $data['guest_stars'] ?? $episode->guest_stars,
            'is_available' => $data['is_available'] ?? $episode->is_available,
        ]);

        if (isset($data['thumbnail'])) {
            $this->deleteImage($episode->thumbnail_url);
            $episode->thumbnail_url = $this->uploadImage($data['thumbnail'], 'episode-thumbnails', $episode->season_id);
            $episode->save();
        }

        return $episode;
    }

    /**
     * Import TMDB data
     */
    public function importTMDBData(VODContent $vod, $tmdbId)
    {
        $data = $vod->type === 'series'
            ? $this->tmdbService->getTVShow($tmdbId)
            : $this->tmdbService->getMovie($tmdbId);

        if (!$data) {
            return false;
        }

        $updateData = [
            'tmdb_id' => $tmdbId,
            'original_title' => $data['original_title'] ?? $data['name'] ?? null,
            'description' => $data['overview'] ?? null,
            'year' => isset($data['release_date']) ? substr($data['release_date'], 0, 4) : ($data['first_air_date_year'] ?? null),
            'rating' => $data['vote_average'] ?? null,
            'poster_url' => $data['poster_url'] ?? null,
            'backdrop_url' => $data['backdrop_url'] ?? null,
            'duration' => $data['runtime'] ?? null,
            'genre' => $data['genres'] ?? null,
            'tmdb_data' => $data,
        ];

        if (isset($data['credits'])) {
            foreach ($data['credits']['cast'] ?? [] as $castData) {
                $person = $this->importPerson($castData);
                if ($person) {
                    $this->addCast($vod->id, $person->id, [
                        'character_name' => $castData['character'] ?? null,
                        'order_index' => $castData['order'] ?? 0,
                    ]);
                }
            }

            foreach ($data['credits']['crew'] ?? [] as $crewData) {
                $person = $this->importPerson($crewData);
                if ($person) {
                    $this->addCrew($vod->id, $person->id, [
                        'job' => $crewData['job'] ?? null,
                        'department' => $crewData['department'] ?? null,
                    ]);
                }
            }
        }

        if (isset($data['videos'])) {
            $trailer = collect($data['videos']['results'] ?? [])
                ->filter(function ($video) {
                    return $video['site'] === 'YouTube' &&
                        in_array($video['type'], ['Trailer', 'Teaser']);
                })
                ->first();

            if ($trailer) {
                $updateData['trailer_url'] = "https://www.youtube.com/watch?v={$trailer['key']}";
            }
        }

        $vod->update($updateData);

        if ($vod->type === 'series' && isset($data['seasons'])) {
            foreach ($data['seasons'] as $seasonData) {
                if (($seasonData['season_number'] ?? 0) > 0) {
                    $season = $this->createSeason($vod->id, [
                        'season_number' => $seasonData['season_number'],
                        'title' => $seasonData['name'] ?? "Season {$seasonData['season_number']}",
                        'description' => $seasonData['overview'] ?? null,
                        'air_date' => $seasonData['air_date'] ?? null,
                    ]);

                    $episodes = $this->tmdbService->getTVEpisodes($tmdbId, $seasonData['season_number']);
                    foreach ($episodes as $episodeData) {
                        $this->createEpisode($season->id, [
                            'episode_number' => $episodeData['episode_number'],
                            'title' => $episodeData['name'],
                            'description' => $episodeData['overview'] ?? null,
                            'duration' => $episodeData['runtime'] ?? null,
                            'air_date' => $episodeData['air_date'] ?? null,
                        ]);
                    }
                }
            }
        }

        return true;
    }

    /**
     * Import person from TMDB
     */
    public function importPerson($data)
    {
        if (!isset($data['id'])) {
            return null;
        }

        return VODPerson::updateOrCreate(
            ['tmdb_id' => $data['id']],
            [
                'name' => $data['name'],
                'profile_url' => $data['profile_path'] ?? null,
                'known_for_department' => $data['known_for_department'] ?? null,
                'popularity' => $data['popularity'] ?? 0,
            ]
        );
    }

    /**
     * Add cast to VOD content
     */
    public function addCast($vodContentId, $personId, $data)
    {
        return VODCast::create([
            'vod_content_id' => $vodContentId,
            'person_id' => $personId,
            'character_name' => $data['character_name'] ?? null,
            'role' => $data['role'] ?? 'supporting',
            'order_index' => $data['order_index'] ?? 0,
        ]);
    }

    /**
     * Add crew to VOD content
     */
    public function addCrew($vodContentId, $personId, $data)
    {
        return VODCrew::create([
            'vod_content_id' => $vodContentId,
            'person_id' => $personId,
            'job' => $data['job'] ?? null,
            'department' => $data['department'] ?? null,
        ]);
    }

    /**
     * Get watch progress for user
     */
    public function getWatchProgress($userId, $vodContentId)
    {
        $history = VODWatchHistory::where('user_id', $userId)
            ->where('vod_content_id', $vodContentId)
            ->orderBy('last_watched', 'desc')
            ->first();

        return $history ? $history->progress : 0;
    }

    /**
     * Get episode watch progress
     */
    public function getEpisodeWatchProgress($userId, $episodeId)
    {
        $history = VODWatchHistory::where('user_id', $userId)
            ->where('episode_id', $episodeId)
            ->orderBy('last_watched', 'desc')
            ->first();

        return $history ? $history->progress : 0;
    }

    /**
     * Get series progress
     */
    public function getSeriesProgress($userId, $seriesId)
    {
        $episodes = VODEpisode::whereHas('season', function ($q) use ($seriesId) {
            $q->where('vod_content_id', $seriesId);
        })->pluck('id')->toArray();

        $watched = VODWatchHistory::where('user_id', $userId)
            ->whereIn('episode_id', $episodes)
            ->where('progress', '>=', 90)
            ->count();

        $total = count($episodes);

        return $total > 0 ? round(($watched / $total) * 100, 1) : 0;
    }

    /**
     * Check if content is favorite
     */
    public function isFavorite($userId, $vodContentId)
    {
        return VODFavorite::where('user_id', $userId)
            ->where('vod_content_id', $vodContentId)
            ->exists();
    }

    /**
     * Check if content is in watchlist
     */
    public function isWatchlisted($userId, $vodContentId)
    {
        return VODWatchlist::where('user_id', $userId)
            ->where('vod_content_id', $vodContentId)
            ->exists();
    }

    /**
     * Get user rating
     */
    public function getUserRating($userId, $vodContentId)
    {
        $review = VODReview::where('user_id', $userId)
            ->where('vod_content_id', $vodContentId)
            ->first();

        return $review ? $review->rating : null;
    }

    /**
     * Get content statistics
     */
    public function getStatistics($vodContentId)
    {
        return Cache::remember("vod_stats:{$vodContentId}", 3600, function () use ($vodContentId) {
            return [
                'total_views' => VODWatchHistory::where('vod_content_id', $vodContentId)->count(),
                'total_watch_time' => VODWatchHistory::where('vod_content_id', $vodContentId)->sum('watch_duration'),
                'average_rating' => VODReview::where('vod_content_id', $vodContentId)->avg('rating'),
                'total_ratings' => VODReview::where('vod_content_id', $vodContentId)->count(),
                'favorite_count' => VODFavorite::where('vod_content_id', $vodContentId)->count(),
                'watchlist_count' => VODWatchlist::where('vod_content_id', $vodContentId)->count(),
                'completion_rate' => $this->calculateCompletionRate($vodContentId),
            ];
        });
    }

    /**
     * Calculate completion rate
     */
    protected function calculateCompletionRate($vodContentId)
    {
        $histories = VODWatchHistory::where('vod_content_id', $vodContentId)->get();

        if ($histories->isEmpty()) {
            return 0;
        }

        $completed = $histories->filter(function ($history) {
            return $history->progress >= 90 || $history->completed;
        })->count();

        return round(($completed / $histories->count()) * 100, 1);
    }

    /**
     * Get similar content
     */
    public function getSimilarContent($vodContentId, $limit = 10)
    {
        return Cache::remember("vod_similar:{$vodContentId}", 3600, function () use ($vodContentId, $limit) {
            $vod = VODContent::with(['categories'])->find($vodContentId);

            if (!$vod) {
                return [];
            }

            $query = VODContent::where('id', '!=', $vodContentId)
                ->where('is_active', true);

            if ($vod->genre) {
                $genres = is_array($vod->genre) ? $vod->genre : (json_decode($vod->genre, true) ?: []);
                if (!empty($genres)) {
                    $query->where(function ($q) use ($genres) {
                        foreach ($genres as $genre) {
                            $q->orWhere('genre', 'LIKE', "%{$genre}%");
                        }
                    });
                }
            }

            if ($vod->categories->isNotEmpty()) {
                $categoryIds = $vod->categories->pluck('id')->toArray();
                $query->whereHas('categories', function ($q) use ($categoryIds) {
                    $q->whereIn('categories.id', $categoryIds);
                });
            }

            $query->where('type', $vod->type);

            $query->orderBy('year', 'desc')
                ->orderBy('views', 'desc')
                ->limit($limit);

            return $query->get();
        });
    }

    /**
     * Upload image
     */
    protected function uploadImage($file, $folder, $id)
    {
        $extension = $file->getClientOriginalExtension();
        $filename = "{$id}_" . uniqid() . ".{$extension}";
        $path = "vod/{$folder}/{$filename}";

        Storage::disk('public')->put($path, file_get_contents($file));

        return Storage::disk('public')->url($path);
    }

    /**
     * Delete image
     */
    protected function deleteImage($url)
    {
        if ($url) {
            $path = str_replace(Storage::disk('public')->url(''), '', $url);
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    /**
     * Upload media file
     */
    protected function uploadMedia($file, $id, $type)
    {
        $extension = $file->getClientOriginalExtension();
        $filename = "{$id}_" . uniqid() . ".{$extension}";
        $path = "vod/media/{$type}/{$filename}";

        Storage::disk('public')->put($path, file_get_contents($file));

        return VODMedia::create([
            'vod_content_id' => $id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'stream_url' => Storage::disk('public')->url($path),
            'is_available' => true,
        ]);
    }

    /**
     * Detect media quality
     */
    protected function detectQuality(VODMedia $media)
    {
        if (!$this->qualityService) {
            return ['success' => false];
        }

        $result = $this->qualityService->detectVODQuality($media);

        if ($result['success'] ?? false) {
            $media->update([
                'quality' => $result['quality'] ?? 'hd',
                'resolution' => $result['data']['resolution'] ?? null,
                'codec' => $result['data']['codec'] ?? null,
                'duration' => $result['data']['duration'] ?? null,
            ]);
        }

        return $result;
    }

    /**
     * Get VOD for client display
     */
    public function getVODForClient($userId, $filters = [])
    {
        $query = VODContent::where('is_active', true)
            ->where('is_available', true);

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['category'])) {
            $query->whereHas('categories', function ($q) use ($filters) {
                $q->where('categories.id', $filters['category']);
            });
        }

        if (isset($filters['genre'])) {
            $query->where('genre', 'LIKE', "%{$filters['genre']}%");
        }

        if (isset($filters['year'])) {
            $query->where('year', $filters['year']);
        }

        if (isset($filters['quality'])) {
            $query->where('quality_level', $filters['quality']);
        }

        if (isset($filters['rating'])) {
            $query->where('rating', '>=', $filters['rating']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'LIKE', "%{$filters['search']}%")
                    ->orWhere('description', 'LIKE', "%{$filters['search']}%")
                    ->orWhere('director', 'LIKE', "%{$filters['search']}%");
            });
        }

        $sort = $filters['sort'] ?? 'popular';

        switch ($sort) {
            case 'popular':
                $query->orderBy('views', 'desc');
                break;
            case 'rating':
                $query->orderBy('rating', 'desc');
                break;
            case 'latest':
                $query->orderBy('year', 'desc');
                break;
            case 'oldest':
                $query->orderBy('year', 'asc');
                break;
            case 'title':
                $query->orderBy('title', 'asc');
                break;
            case 'featured':
                $query->orderBy('featured_order', 'asc')
                    ->orderBy('created_at', 'desc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
        }

        $perPage = $filters['per_page'] ?? 20;

        return $query->paginate($perPage);
    }

    /**
     * Get featured content
     */
    public function getFeatured($limit = 10)
    {
        return VODContent::where('is_active', true)
            ->where('is_featured', true)
            ->where('is_available', true)
            ->orderBy('featured_order', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recently added content
     */
    public function getRecentlyAdded($limit = 20)
    {
        return VODContent::where('is_active', true)
            ->where('is_available', true)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get trending content
     */
    public function getTrending($limit = 20)
    {
        $daysAgo = Carbon::now()->subDays(7);

        return VODContent::where('is_active', true)
            ->where('is_available', true)
            ->where('created_at', '>=', $daysAgo)
            ->orderBy('views', 'desc')
            ->orderBy('watch_time', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get next episode for user
     */
    public function getNextEpisode($userId, $seriesId)
    {
        $episodes = VODEpisode::whereHas('season', function ($q) use ($seriesId) {
            $q->where('vod_content_id', $seriesId);
        })->orderBy('season_number')->orderBy('episode_number')->get();

        $watchedEpisodeIds = VODWatchHistory::where('user_id', $userId)
            ->whereIn('episode_id', $episodes->pluck('id'))
            ->where('progress', '>=', 90)
            ->pluck('episode_id')
            ->toArray();

        foreach ($episodes as $episode) {
            if (!in_array($episode->id, $watchedEpisodeIds)) {
                return $episode;
            }
        }

        return null;
    }

    /**
     * Record watch activity
     */
    public function recordWatch($userId, $vodContentId, $progress, $duration = null, $episodeId = null)
    {
        $history = VODWatchHistory::updateOrCreate(
            [
                'user_id' => $userId,
                'vod_content_id' => $vodContentId,
                'episode_id' => $episodeId,
            ],
            [
                'progress' => min($progress, 100),
                'watch_duration' => $duration ?? 0,
                'last_watched' => now(),
                'watch_count' => DB::raw('watch_count + 1'),
                'completed' => $progress >= 90,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]
        );

        if ($progress >= 90) {
            $vod = VODContent::find($vodContentId);
            if ($vod) {
                $vod->increment('views');
                $vod->watch_time = $vod->watch_time + ($duration ?? 0);
                $vod->save();
            }
        }

        return $history;
    }

    /**
     * Add to favorites
     */
    public function addToFavorites($userId, $vodContentId)
    {
        return VODFavorite::firstOrCreate([
            'user_id' => $userId,
            'vod_content_id' => $vodContentId,
        ]);
    }

    /**
     * Remove from favorites
     */
    public function removeFromFavorites($userId, $vodContentId)
    {
        return VODFavorite::where('user_id', $userId)
            ->where('vod_content_id', $vodContentId)
            ->delete();
    }

    /**
     * Add to watchlist
     */
    public function addToWatchlist($userId, $vodContentId)
    {
        return VODWatchlist::firstOrCreate([
            'user_id' => $userId,
            'vod_content_id' => $vodContentId,
        ]);
    }

    /**
     * Remove from watchlist
     */
    public function removeFromWatchlist($userId, $vodContentId)
    {
        return VODWatchlist::where('user_id', $userId)
            ->where('vod_content_id', $vodContentId)
            ->delete();
    }

    /**
     * Add review
     */
    public function addReview($userId, $vodContentId, $rating, $comment = null, $title = null)
    {
        return VODReview::updateOrCreate(
            [
                'user_id' => $userId,
                'vod_content_id' => $vodContentId,
            ],
            [
                'rating' => $rating,
                'title' => $title,
                'comment' => $comment,
                'is_approved' => false,
            ]
        );
    }

    /**
     * Delete VOD content
     */
    public function deleteVOD($id)
    {
        try {
            DB::beginTransaction();

            $vod = VODContent::findOrFail($id);

            $vod->seasons()->each(function ($season) {
                $season->episodes()->delete();
                $season->delete();
            });

            $vod->vodMedia()->delete();
            $vod->cast()->delete();
            $vod->crew()->delete();
            $vod->reviews()->delete();
            $vod->favorites()->delete();
            $vod->watchlist()->delete();
            $vod->watchHistory()->delete();

            $this->deleteImage($vod->poster_url);
            $this->deleteImage($vod->backdrop_url);
            $this->deleteImage($vod->banner_url);

            $vod->delete();

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}