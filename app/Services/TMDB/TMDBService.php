<?php

namespace App\Services\TMDB;

use App\Models\SystemSetting;
use App\Models\TMDBMetadata;
use App\Models\TMDBMapping;
use App\Models\TMDBSyncLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TMDBService
{
    protected string $apiKey;
    protected string $baseUrl;
    protected string $imageBaseUrl;
    protected string $language;
    protected string $region;
    protected int $cacheTtl;

    public function __construct()
    {
        $this->apiKey = config('tmdb.api_key') ?: '';
        $this->baseUrl = config('tmdb.api_base_url');
        $this->imageBaseUrl = config('tmdb.image_base_url');
        $this->language = config('tmdb.language', 'en-US');
        $this->region = config('tmdb.region', 'US');
        $this->cacheTtl = (int) config('tmdb.cache_ttl', 86400);
    }

    public function isConfigured(): bool
    {
        return !empty($this->getApiKey());
    }

    protected function getApiKey(): string
    {
        if (!empty($this->apiKey)) {
            return $this->apiKey;
        }
        try {
            $dbKey = SystemSetting::get('metadata_api_key', '');
            if (!empty($dbKey)) {
                $this->apiKey = $dbKey;
                return $this->apiKey;
            }
        } catch (\Exception $e) {
            // DB may not be available during route listing or artisan commands
        }
        return '';
    }

    public function search(string $query, string $type = 'movie', int $page = 1): array
    {
        $endpoint = $type === 'tv' ? '/search/tv' : '/search/movie';

        $params = [
            'query' => $query,
            'page' => $page,
            'language' => $this->language,
        ];

        $response = $this->makeRequest($endpoint, $params);

        if ($response === null) {
            return [];
        }

        return $this->formatSearchResults($response, $type);
    }

    public function getMovie(int|string $tmdbId, array $appendToResponse = ['credits', 'videos', 'images', 'external_ids']): ?array
    {
        $cacheKey = "tmdb_movie_{$tmdbId}_" . md5(implode(',', $appendToResponse));

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($tmdbId, $appendToResponse) {
            $params = [
                'append_to_response' => implode(',', $appendToResponse),
                'language' => $this->language,
            ];

            $data = $this->makeRequest("/movie/{$tmdbId}", $params);

            if ($data === null) {
                return null;
            }

            $formatted = $this->formatMovieData($data);
            $this->storeMetadata($tmdbId, 'movie', $data);

            return $formatted;
        });
    }

    public function getTVShow(int|string $tmdbId, array $appendToResponse = ['credits', 'videos', 'images', 'external_ids']): ?array
    {
        $cacheKey = "tmdb_tv_{$tmdbId}_" . md5(implode(',', $appendToResponse));

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($tmdbId, $appendToResponse) {
            $params = [
                'append_to_response' => implode(',', $appendToResponse),
                'language' => $this->language,
            ];

            $data = $this->makeRequest("/tv/{$tmdbId}", $params);

            if ($data === null) {
                return null;
            }

            $formatted = $this->formatTVData($data);
            $this->storeMetadata($tmdbId, 'tv', $data);

            return $formatted;
        });
    }

    public function getTVEpisodes(int|string $tmdbId, int $seasonNumber): array
    {
        $cacheKey = "tmdb_tv_{$tmdbId}_season_{$seasonNumber}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($tmdbId, $seasonNumber) {
            $params = [
                'language' => $this->language,
            ];

            $data = $this->makeRequest("/tv/{$tmdbId}/season/{$seasonNumber}", $params);

            if ($data === null) {
                return [];
            }

            $episodes = [];
            if (isset($data['episodes']) && is_array($data['episodes'])) {
                foreach ($data['episodes'] as $episode) {
                    $episodes[] = $this->formatEpisodeData($episode);
                }
            }

            return $episodes;
        });
    }

    public function getImageUrl(?string $path, string $size = 'original'): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return "{$this->imageBaseUrl}/{$size}{$path}";
    }

    public function autoPopulateVOD($vodContent): array
    {
        $title = $vodContent->title;
        $year = $vodContent->year;
        $type = $vodContent->type ?? 'movie';

        Log::info('TMDB auto-populate starting', [
            'vod_id' => $vodContent->id,
            'title' => $title,
            'year' => $year,
            'type' => $type,
        ]);

        $match = $this->mapVODToTMDB($title, $year, $type);

        if ($match === null) {
            Log::warning('TMDB auto-populate failed: no match found', ['title' => $title]);

            return [
                'success' => false,
                'data' => null,
                'error' => 'No match found on TMDB',
            ];
        }

        $tmdbId = $match['tmdb_id'];
        $matchScore = $match['score'] ?? 0;

        $matchThreshold = config('tmdb.auto_populate.match_threshold', 70);

        if ($matchScore < $matchThreshold) {
            Log::warning('TMDB auto-populate: match score below threshold', [
                'title' => $title,
                'score' => $matchScore,
                'threshold' => $matchThreshold,
            ]);

            return [
                'success' => false,
                'data' => $match,
                'error' => "Match score {$matchScore} below threshold {$matchThreshold}",
            ];
        }

        try {
            $details = $type === 'tv'
                ? $this->getTVShow($tmdbId)
                : $this->getMovie($tmdbId);

            if ($details === null) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => "Failed to fetch TMDB details for ID {$tmdbId}",
                ];
            }

            $updateData = [
                'description' => $details['overview'] ?? $vodContent->description,
                'rating' => $details['vote_average'] ?? $vodContent->rating,
                'poster_url' => $this->getImageUrl($details['poster_path']) ?? $vodContent->poster_url,
                'backdrop_url' => $this->getImageUrl($details['backdrop_path']) ?? $vodContent->backdrop_url,
                'trailer_url' => $details['trailer_url'] ?? $vodContent->trailer_url,
                'genre' => $details['genres'] ?? $vodContent->genre,
                'cast' => $details['cast'] ?? $vodContent->cast,
                'director' => $details['director'] ?? $vodContent->director,
                'year' => $details['release_year'] ?? $vodContent->year,
            ];

            $vodContent->update($updateData);

            if ($type === 'tv' && isset($details['seasons'])) {
                foreach ($details['seasons'] as $season) {
                    $seasonNum = $season['season_number'] ?? 0;
                    if ($seasonNum === 0) continue;
                    $episodes = $this->getTVEpisodes($tmdbId, $seasonNum);
                    foreach ($episodes as $ep) {
                        \App\Models\VODMedia::updateOrCreate(
                            [
                                'vod_content_id' => $vodContent->id,
                                'season_number' => $ep['season_number'] ?? $seasonNum,
                                'episode_number' => $ep['episode_number'] ?? null,
                            ],
                            [
                                'episode_title' => $ep['title'] ?? null,
                                'overview' => $ep['overview'] ?? null,
                                'still_url' => $ep['still_url'] ?? null,
                                'duration' => $ep['runtime'] ?? null,
                                'air_date' => $ep['air_date'] ?? null,
                                'stream_url' => '',
                                'quality' => '1080p',
                                'resolution' => '1080p',
                                'codec' => 'h264',
                                'stream_type' => 'hls',
                                'is_available' => true,
                            ]
                        );
                    }
                }
            }

            TMDBMapping::updateOrCreate(
                [
                    'content_type' => 'vod',
                    'content_id' => $vodContent->id,
                    'tmdb_id' => $tmdbId,
                ],
                [
                    'media_type' => $type,
                    'is_primary' => true,
                    'confidence_score' => $matchScore,
                    'mapped_at' => now(),
                ]
            );

            TMDBSyncLog::create([
                'operation' => 'auto_populate',
                'tmdb_id' => $tmdbId,
                'content_type' => $type,
                'status' => 'success',
                'data' => ['vod_id' => $vodContent->id, 'match_score' => $matchScore],
                'started_at' => now(),
                'completed_at' => now(),
            ]);

            Log::info('TMDB auto-populate completed', [
                'vod_id' => $vodContent->id,
                'tmdb_id' => $tmdbId,
                'score' => $matchScore,
            ]);

            return [
                'success' => true,
                'data' => $details,
                'error' => null,
            ];
        } catch (\Exception $e) {
            Log::error('TMDB auto-populate error', [
                'title' => $title,
                'error' => $e->getMessage(),
            ]);

            TMDBSyncLog::create([
                'operation' => 'auto_populate',
                'tmdb_id' => $tmdbId,
                'content_type' => $type,
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'started_at' => now(),
                'completed_at' => now(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function mapVODToTMDB(string $title, ?int $year, string $type = 'movie'): ?array
    {
        $results = $this->search($title, $type);

        if (empty($results)) {
            return null;
        }

        $bestMatch = null;
        $bestScore = 0;

        foreach ($results as $result) {
            $score = $this->calculateMatchScore($result, $title, $year);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = array_merge($result, ['score' => $score]);
            }
        }

        return $bestMatch;
    }

    public function getPopular(string $type = 'movie', int $page = 1): array
    {
        $endpoint = $type === 'tv' ? '/tv/popular' : '/movie/popular';

        $params = [
            'page' => $page,
            'language' => $this->language,
            'region' => $this->region,
        ];

        $response = $this->makeRequest($endpoint, $params);

        if ($response === null) {
            return [];
        }

        return $this->formatSearchResults($response, $type);
    }

    public function getTrending(string $timeWindow = 'week', int $page = 1): array
    {
        $params = [
            'page' => $page,
        ];

        $response = $this->makeRequest("/trending/all/{$timeWindow}", $params);

        if ($response === null) {
            return [];
        }

        $results = [];
        if (isset($response['results']) && is_array($response['results'])) {
            foreach ($response['results'] as $item) {
                $type = $item['media_type'] ?? 'movie';
                $results[] = $this->formatSearchResults(['results' => [$item]], $type)[0] ?? [];
            }
        }

        return $results;
    }

    public function makeRequest(string $endpoint, array $params = []): ?array
    {
        try {
            $response = Http::timeout(config('tmdb.fallback.timeout', 30))
                ->retry(config('tmdb.fallback.max_retries', 3), config('tmdb.fallback.retry_delay', 100))
                ->get("{$this->baseUrl}{$endpoint}", array_merge(['api_key' => $this->getApiKey()], $params));

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('TMDB API request failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('TMDB API request error', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function formatSearchResults(array $results, string $type): array
    {
        $formatted = [];

        if (!isset($results['results']) || !is_array($results['results'])) {
            return $formatted;
        }

        foreach ($results['results'] as $item) {
            if ($type === 'tv') {
                $formatted[] = [
                    'tmdb_id' => $item['id'],
                    'title' => $item['name'] ?? null,
                    'original_title' => $item['original_name'] ?? null,
                    'overview' => $item['overview'] ?? null,
                    'poster_path' => $item['poster_path'] ?? null,
                    'poster_url' => $this->getImageUrl($item['poster_path'] ?? null),
                    'backdrop_path' => $item['backdrop_path'] ?? null,
                    'backdrop_url' => $this->getImageUrl($item['backdrop_path'] ?? null),
                    'vote_average' => $item['vote_average'] ?? null,
                    'vote_count' => $item['vote_count'] ?? null,
                    'popularity' => $item['popularity'] ?? null,
                    'release_date' => $item['first_air_date'] ?? null,
                    'release_year' => isset($item['first_air_date'])
                        ? (int) substr($item['first_air_date'], 0, 4)
                        : null,
                    'genre_ids' => $item['genre_ids'] ?? [],
                    'episode_count' => $item['episode_count'] ?? null,
                    'type' => 'tv',
                ];
            } else {
                $formatted[] = [
                    'tmdb_id' => $item['id'],
                    'title' => $item['title'] ?? null,
                    'original_title' => $item['original_title'] ?? null,
                    'overview' => $item['overview'] ?? null,
                    'poster_path' => $item['poster_path'] ?? null,
                    'poster_url' => $this->getImageUrl($item['poster_path'] ?? null),
                    'backdrop_path' => $item['backdrop_path'] ?? null,
                    'backdrop_url' => $this->getImageUrl($item['backdrop_path'] ?? null),
                    'vote_average' => $item['vote_average'] ?? null,
                    'vote_count' => $item['vote_count'] ?? null,
                    'popularity' => $item['popularity'] ?? null,
                    'release_date' => $item['release_date'] ?? null,
                    'release_year' => isset($item['release_date'])
                        ? (int) substr($item['release_date'], 0, 4)
                        : null,
                    'genre_ids' => $item['genre_ids'] ?? [],
                    'type' => 'movie',
                ];
            }
        }

        return $formatted;
    }

    private function formatMovieData(array $data): array
    {
        $director = null;
        $cast = [];
        $trailerUrl = null;

        if (isset($data['credits']['crew'])) {
            foreach ($data['credits']['crew'] as $crew) {
                if ($crew['job'] === 'Director') {
                    $director = $crew['name'];
                    break;
                }
            }
        }

        if (isset($data['credits']['cast'])) {
            foreach (array_slice($data['credits']['cast'], 0, 10) as $actor) {
                $cast[] = $actor['name'];
            }
        }

        if (isset($data['videos']['results'])) {
            foreach ($data['videos']['results'] as $video) {
                if ($video['type'] === 'Trailer' && $video['site'] === 'YouTube') {
                    $trailerUrl = "https://www.youtube.com/watch?v={$video['key']}";
                    break;
                }
            }
            if ($trailerUrl === null) {
                foreach ($data['videos']['results'] as $video) {
                    if ($video['site'] === 'YouTube') {
                        $trailerUrl = "https://www.youtube.com/watch?v={$video['key']}";
                        break;
                    }
                }
            }
        }

        $genres = [];
        if (isset($data['genres']) && is_array($data['genres'])) {
            foreach ($data['genres'] as $genre) {
                $genres[] = $genre['name'];
            }
        }

        return [
            'tmdb_id' => $data['id'],
            'title' => $data['title'] ?? null,
            'original_title' => $data['original_title'] ?? null,
            'overview' => $data['overview'] ?? null,
            'poster_path' => $data['poster_path'] ?? null,
            'poster_url' => $this->getImageUrl($data['poster_path'] ?? null),
            'backdrop_path' => $data['backdrop_path'] ?? null,
            'backdrop_url' => $this->getImageUrl($data['backdrop_path'] ?? null),
            'vote_average' => $data['vote_average'] ?? null,
            'vote_count' => $data['vote_count'] ?? null,
            'popularity' => $data['popularity'] ?? null,
            'release_date' => $data['release_date'] ?? null,
            'release_year' => isset($data['release_date'])
                ? (int) substr($data['release_date'], 0, 4)
                : null,
            'runtime' => $data['runtime'] ?? null,
            'genres' => $genres,
            'director' => $director,
            'cast' => $cast,
            'trailer_url' => $trailerUrl,
            'imdb_id' => $data['external_ids']['imdb_id'] ?? null,
            'type' => 'movie',
        ];
    }

    private function formatTVData(array $data): array
    {
        $createdBy = null;
        $cast = [];
        $trailerUrl = null;

        if (isset($data['created_by']) && !empty($data['created_by'])) {
            $createdBy = $data['created_by'][0]['name'] ?? null;
        }

        if (isset($data['credits']['cast'])) {
            foreach (array_slice($data['credits']['cast'], 0, 10) as $actor) {
                $cast[] = $actor['name'];
            }
        }

        if (isset($data['videos']['results'])) {
            foreach ($data['videos']['results'] as $video) {
                if ($video['type'] === 'Trailer' && $video['site'] === 'YouTube') {
                    $trailerUrl = "https://www.youtube.com/watch?v={$video['key']}";
                    break;
                }
            }
            if ($trailerUrl === null) {
                foreach ($data['videos']['results'] as $video) {
                    if ($video['site'] === 'YouTube') {
                        $trailerUrl = "https://www.youtube.com/watch?v={$video['key']}";
                        break;
                    }
                }
            }
        }

        $genres = [];
        if (isset($data['genres']) && is_array($data['genres'])) {
            foreach ($data['genres'] as $genre) {
                $genres[] = $genre['name'];
            }
        }

        return [
            'tmdb_id' => $data['id'],
            'title' => $data['name'] ?? null,
            'original_title' => $data['original_name'] ?? null,
            'overview' => $data['overview'] ?? null,
            'poster_path' => $data['poster_path'] ?? null,
            'poster_url' => $this->getImageUrl($data['poster_path'] ?? null),
            'backdrop_path' => $data['backdrop_path'] ?? null,
            'backdrop_url' => $this->getImageUrl($data['backdrop_path'] ?? null),
            'vote_average' => $data['vote_average'] ?? null,
            'vote_count' => $data['vote_count'] ?? null,
            'popularity' => $data['popularity'] ?? null,
            'release_date' => $data['first_air_date'] ?? null,
            'release_year' => isset($data['first_air_date'])
                ? (int) substr($data['first_air_date'], 0, 4)
                : null,
            'number_of_seasons' => $data['number_of_seasons'] ?? null,
            'number_of_episodes' => $data['number_of_episodes'] ?? null,
            'status' => $data['status'] ?? null,
            'seasons' => $this->formatSeasonsData($data['seasons'] ?? []),
            'genres' => $genres,
            'director' => $createdBy,
            'cast' => $cast,
            'trailer_url' => $trailerUrl,
            'imdb_id' => $data['external_ids']['imdb_id'] ?? null,
            'type' => 'tv',
        ];
    }

    private function formatEpisodeData(array $data): array
    {
        return [
            'id' => $data['id'] ?? null,
            'episode_number' => $data['episode_number'] ?? null,
            'season_number' => $data['season_number'] ?? null,
            'name' => $data['name'] ?? null,
            'title' => $data['name'] ?? null,
            'overview' => $data['overview'] ?? null,
            'air_date' => $data['air_date'] ?? null,
            'vote_average' => $data['vote_average'] ?? null,
            'vote_count' => $data['vote_count'] ?? null,
            'still_path' => $data['still_path'] ?? null,
            'still_url' => $this->getImageUrl($data['still_path'] ?? null),
            'runtime' => $data['runtime'] ?? null,
        ];
    }

    private function formatSeasonsData(array $seasons): array
    {
        $formatted = [];
        foreach ($seasons as $season) {
            $formatted[] = [
                'season_number' => $season['season_number'] ?? null,
                'name' => $season['name'] ?? null,
                'overview' => $season['overview'] ?? null,
                'poster_path' => $season['poster_path'] ?? null,
                'poster_url' => $this->getImageUrl($season['poster_path'] ?? null, 'w300'),
                'air_date' => $season['air_date'] ?? null,
                'episode_count' => $season['episode_count'] ?? null,
            ];
        }
        return $formatted;
    }

    private function calculateMatchScore(array $result, string $title, ?int $year): float
    {
        $resultTitle = strtolower($result['title'] ?? '');
        $searchTitle = strtolower($title);

        similar_text($resultTitle, $searchTitle, $titleSimilarity);

        $normalizedTitle = preg_replace('/[^a-z0-9]/', '', $searchTitle);
        $normalizedResult = preg_replace('/[^a-z0-9]/', '', $resultTitle);

        $exactMatch = $normalizedTitle === $normalizedResult ? 1.0 : 0.0;

        $levenshtein = levenshtein($searchTitle, $resultTitle);
        $maxLen = max(strlen($searchTitle), strlen($resultTitle));
        $levenshteinScore = $maxLen > 0 ? 1 - ($levenshtein / $maxLen) : 0;

        $titleScore = ($titleSimilarity * 0.3) + ($exactMatch * 0.5) + ($levenshteinScore * 0.2);

        $yearScore = 0;
        if ($year !== null && isset($result['release_year']) && $result['release_year'] !== null) {
            $yearDiff = abs($year - $result['release_year']);
            $yearScore = $yearDiff <= 1 ? 1.0 : ($yearDiff <= 2 ? 0.7 : max(0, 1 - ($yearDiff * 0.2)));
        } elseif ($year !== null && !isset($result['release_year'])) {
            $yearScore = 0.5;
        }

        return round(($titleScore * 0.7 + $yearScore * 0.3) * 100, 2);
    }

    private function storeMetadata(int|string $tmdbId, string $type, array $data): void
    {
        try {
            TMDBMetadata::updateOrCreate(
                ['tmdb_id' => $tmdbId, 'media_type' => $type],
                [
                    'data' => $data,
                    'poster_path' => $data['poster_path'] ?? null,
                    'backdrop_path' => $data['backdrop_path'] ?? null,
                    'popularity' => $data['popularity'] ?? null,
                    'vote_average' => $data['vote_average'] ?? null,
                    'vote_count' => $data['vote_count'] ?? null,
                    'release_date' => isset($data['release_date']) ? substr($data['release_date'], 0, 10) : null,
                    'last_updated' => now(),
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to store TMDB metadata', [
                'tmdb_id' => $tmdbId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
