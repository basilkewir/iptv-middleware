<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentCategory;
use App\Models\VODContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VODController extends Controller
{
    protected $tmdbService;

    public function __construct(\App\Services\TMDB\TMDBService $tmdbService)
    {
        $this->tmdbService = $tmdbService;
    }

    public function index(Request $request): Response|JsonResponse
    {
        $query = VODContent::with('categories');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $request->input('category_id')) {
            $query->whereHas('categories', fn ($q) => $q->where('categories.id', $categoryId));
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($request->has('status')) {
            $query->where('is_active', $request->boolean('status'));
        }

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        $vods = $query->latest()->paginate($request->input('per_page', 15));

        if ($request->expectsJson()) {
            return response()->json(['data' => $vods]);
        }

        return Inertia::render('Admin/VOD/Index', [
            'vods' => $vods,
            'categories' => ContentCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, VODContent $vod): Response|JsonResponse
    {
        $vod->load(['categories', 'vodMedia', 'bouquets']);

        if ($request->expectsJson()) {
            return response()->json(['data' => $vod]);
        }

        // Attach stream_url from vod_media (season 0 = movie-level media)
        $movieMedia = $vod->vodMedia()->where('season_number', 0)->first();
        $vod->stream_url = $movieMedia?->stream_url ?? '';

        return Inertia::render('Admin/VOD/Edit', [
            'vod' => array_merge($vod->toArray(), [
                'vodMedia' => $vod->vodMedia->map(fn($m) => [
                    'id'             => $m->id,
                    'season_number'  => (int) $m->season_number,
                    'episode_number' => (int) $m->episode_number,
                    'episode_title'  => $m->episode_title,
                    'stream_url'     => $m->stream_url,
                    'air_date'       => $m->air_date ? substr($m->air_date, 0, 10) : null,
                    'duration'       => $m->duration,
                    'is_available'   => $m->is_available,
                ]),
                'bouquets' => $vod->bouquets,
                'categories' => $vod->categories,
            ]),
            'categories' => ContentCategory::where('is_active', true)->get(),
            'bouquets' => \App\Models\Bouquet::where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->filled('episodes_data') && is_string($request->input('episodes_data'))) {
            $decoded = json_decode($request->input('episodes_data'), true);
            if (is_array($decoded)) {
                $request->merge(['episodes_data' => $decoded]);
            }
        }

        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'poster_url'   => 'nullable|string|max:2048',
            'backdrop_url' => 'nullable|string|max:2048',
            'trailer_url'  => 'nullable|string|max:2048',
            'stream_url'   => 'nullable|string|max:2048',
            'type'         => 'required|in:movie,series,documentary,tv_show,anime,kids',
            'year'         => 'nullable|integer|min:1888|max:2100',
            'duration'     => 'nullable|integer|min:0',
            'rating'       => 'nullable|numeric|min:0|max:10',
            'director'     => 'nullable|string|max:255',
            'genre'        => 'nullable|array',
            'cast'         => 'nullable|array',
            'tmdb_id'      => 'nullable|integer',
            'imdb_id'      => 'nullable|string|max:20',
            'season_count' => 'nullable|integer|min:0',
            'episode_count'=> 'nullable|integer|min:0',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:content_categories,id',
            'bouquet_ids'  => 'nullable|array',
            'bouquet_ids.*'  => 'exists:bouquets,id',
             'is_active'    => 'nullable|boolean',
             'is_featured'  => 'nullable|boolean',
             'episode_urls' => 'nullable|array',
             'episodes_data' => 'nullable|array',
             'episodes_data.*.season_number' => 'required|integer|min:0',
             'episodes_data.*.episode_number' => 'required|integer|min:1',
             'episodes_data.*.episode_title' => 'nullable|string',
             'episodes_data.*.stream_url' => 'nullable|string',
             'episodes_data.*.air_date' => 'nullable|date',
             'episodes_data.*.duration' => 'nullable|integer|min:0',
             'episodes_data.*.media_id' => 'nullable|integer',
         ]);

        $streamUrl   = $validated['stream_url'] ?? null;
        $bouquetIds  = $validated['bouquet_ids'] ?? [];
        $episodeUrls = $validated['episode_urls'] ?? [];
        $episodesData = $validated['episodes_data'] ?? [];
        unset($validated['bouquet_ids'], $validated['stream_url'], $validated['episode_urls'], $validated['episodes_data']);

        $slug = \Str::slug($validated['title']);
        if (VODContent::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $slug . '-' . time();
        }

        $vod = VODContent::create([
            'title'         => $validated['title'],
            'slug'          => $slug,
            'description'   => $validated['description'] ?? null,
            'poster_url'    => $validated['poster_url'] ?? null,
            'backdrop_url'  => $validated['backdrop_url'] ?? null,
            'trailer_url'   => $validated['trailer_url'] ?? null,
            'type'          => $validated['type'],
            'year'          => ($validated['year'] ?? null) ?: null,
            'duration'      => ($validated['duration'] ?? null) ?: null,
            'rating'        => $validated['rating'] ?? 0,
            'director'      => $validated['director'] ?? null,
            'genre'         => $validated['genre'] ?? null,
            'cast'          => $validated['cast'] ?? null,
            'tmdb_id'       => ($validated['tmdb_id'] ?? null) ?: null,
            'imdb_id'       => $validated['imdb_id'] ?? null,
            'season_count'  => $validated['season_count'] ?? 0,
            'episode_count' => $validated['episode_count'] ?? 0,
            'is_active'     => $validated['is_active'] ?? true,
            'is_featured'   => $validated['is_featured'] ?? false,
        ]);

        if (!empty($validated['category_ids'])) $vod->categories()->sync($validated['category_ids']);
        if (!empty($bouquetIds)) $vod->bouquets()->sync($bouquetIds);

        // Store stream URL as VODMedia for movies/non-series
        if ($streamUrl && !in_array($vod->type, ['series', 'tv_show'])) {
            $ext = pathinfo(parse_url($streamUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'mp4';
            \App\Models\VODMedia::create([
                'vod_content_id' => $vod->id,
                'season_number'  => 0,
                'episode_number' => 1,
                'stream_url'     => $streamUrl,
                'stream_type'    => $ext,
                'quality'        => '1080p',
                'resolution'     => '1080p',
                'codec'          => 'h264',
                'is_available'   => true,
            ]);
        }

        // Save full episode data for series — create or update VODMedia records
        if (!empty($episodesData) && in_array($vod->type, ['series', 'tv_show', 'anime'])) {
            foreach ($episodesData as $epData) {
                if (empty($epData['season_number']) || empty($epData['episode_number'])) continue;

                \App\Models\VODMedia::updateOrCreate(
                    [
                        'vod_content_id' => $vod->id,
                        'season_number'  => $epData['season_number'],
                        'episode_number' => $epData['episode_number'],
                    ],
                    [
                        'episode_title'  => $epData['episode_title'] ?? null,
                        'stream_url'     => $epData['stream_url'] ?? '',
                        'air_date'       => $epData['air_date'] ?? null,
                        'duration'       => $epData['duration'] ?? null,
                        'is_available'   => !empty($epData['stream_url']),
                        'stream_type'    => pathinfo(parse_url($epData['stream_url'] ?? '', PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'mp4',
                        'quality'        => '1080p',
                        'resolution'     => '1080p',
                        'codec'          => 'h264',
                    ]
                );
            }

            // Sync season_count and episode_count
            $vod->season_count  = (int) $vod->vodMedia()->distinct('season_number')->where('season_number', '>', 0)->count('season_number');
            $vod->episode_count = (int) $vod->vodMedia()->whereNotNull('episode_number')->count();
            $vod->save();
        }

        // Backwards-compatible: update episode stream URLs (only updates existing records)
        if (!empty($episodeUrls) && in_array($vod->type, ['series', 'tv_show'])) {
            foreach ($episodeUrls as $key => $url) {
                if (!$url) continue;
                [$seasonNum, $episodeNum] = array_map('intval', explode('_', $key, 2));
                \App\Models\VODMedia::where('vod_content_id', $vod->id)
                    ->where('season_number', $seasonNum)
                    ->where('episode_number', $episodeNum)
                    ->update(['stream_url' => $url, 'is_available' => true]);
            }
        }

        if (empty($validated['tmdb_id']) && $this->tmdbService->isConfigured()) {
            try {
                $this->tmdbService->autoPopulateVOD($vod);
            } catch (\Exception $e) {
                \Log::warning('TMDB auto-populate failed after VOD creation', [
                    'vod_id' => $vod->id,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('admin.vod.show', $vod->id)
            ->with('success', 'VOD content created successfully.');
    }

    public function update(Request $request, VODContent $vod): RedirectResponse
    {
        if ($request->filled('episodes_data') && is_string($request->input('episodes_data'))) {
            $decoded = json_decode($request->input('episodes_data'), true);
            if (is_array($decoded)) {
                $request->merge(['episodes_data' => $decoded]);
            }
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'poster_url' => 'nullable|string',
            'backdrop_url' => 'nullable|string',
            'trailer_url' => 'nullable|string',
            'stream_url' => 'nullable|string',
            'type' => 'sometimes|in:movie,series,documentary,tv_show,anime,kids',
            'year' => 'nullable|integer',
            'duration' => 'nullable|integer',
            'rating' => 'nullable|numeric|min:0|max:10',
            'director' => 'nullable|string',
            'genre' => 'nullable|array',
            'cast' => 'nullable|array',
            'tmdb_id' => 'nullable|integer',
            'imdb_id' => 'nullable|string',
            'season_count' => 'nullable|integer',
            'episode_count' => 'nullable|integer',
            'category_ids' => 'sometimes|array',
            'category_ids.*' => 'exists:content_categories,id',
            'bouquet_ids' => 'sometimes|array',
            'bouquet_ids.*' => 'exists:bouquets,id',
             'is_active' => 'sometimes|boolean',
             'is_featured' => 'sometimes|boolean',
             'file' => 'nullable|file|mimes:mp4,mkv,avi,mov,wmv,flv,webm|max:512000',
             'episode_urls' => 'nullable|array',
             'episodes_data' => 'nullable|array',
             'episodes_data.*.season_number' => 'required|integer|min:0',
             'episodes_data.*.episode_number' => 'required|integer|min:1',
             'episodes_data.*.episode_title' => 'nullable|string',
             'episodes_data.*.stream_url' => 'nullable|string',
             'episodes_data.*.air_date' => 'nullable|date',
             'episodes_data.*.duration' => 'nullable|integer|min:0',
             'episodes_data.*.media_id' => 'nullable|integer',
         ]);

        if (isset($validated['category_ids'])) {
            $vod->categories()->sync($validated['category_ids']);
            unset($validated['category_ids']);
        }

        if (isset($validated['bouquet_ids'])) {
            $vod->bouquets()->sync($validated['bouquet_ids']);
            unset($validated['bouquet_ids']);
        }

        $episodeUrls = $validated['episode_urls'] ?? [];
        $episodesData = $validated['episodes_data'] ?? [];
        unset($validated['episode_urls']);
        unset($validated['episodes_data']);

        $streamUrl = $validated['stream_url'] ?? null;
        unset($validated['stream_url']);

        // Ensure season_count / episode_count are never null (DB constraint)
        $validated['season_count'] = $validated['season_count'] ?? 0;
        $validated['episode_count'] = $validated['episode_count'] ?? 0;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileExt = strtolower($file->getClientOriginalExtension());
            $filename = time() . '_' . \Str::slug($validated['title'] ?? $vod->title) . '.' . $fileExt;
            $path = $file->storeAs('vod', $filename, 'public');
            $streamUrl = '/storage/' . $path;
        }

        if (isset($validated['title']) && empty($vod->slug)) {
            $validated['slug'] = \Str::slug($validated['title']);
        }

        $vod->update($validated);

        // Save full episode data for series — create new VODMedia records or update existing ones
        if (!empty($episodesData) && in_array($vod->type, ['series', 'tv_show', 'anime'])) {
            foreach ($episodesData as $epData) {
                if (empty($epData['season_number']) || empty($epData['episode_number'])) continue;

                if (!empty($epData['media_id'])) {
                    \App\Models\VODMedia::where('id', $epData['media_id'])
                        ->where('vod_content_id', $vod->id)
                        ->update([
                            'episode_title' => $epData['episode_title'] ?? null,
                            'stream_url'    => $epData['stream_url'] ?? '',
                            'air_date'      => $epData['air_date'] ?? null,
                            'duration'      => $epData['duration'] ?? null,
                            'is_available'  => !empty($epData['stream_url']),
                        ]);
                } else {
                    \App\Models\VODMedia::updateOrCreate(
                        [
                            'vod_content_id' => $vod->id,
                            'season_number'  => $epData['season_number'],
                            'episode_number' => $epData['episode_number'],
                        ],
                        [
                            'episode_title'  => $epData['episode_title'] ?? null,
                            'stream_url'     => $epData['stream_url'] ?? '',
                            'air_date'       => $epData['air_date'] ?? null,
                            'duration'       => $epData['duration'] ?? null,
                            'is_available'   => !empty($epData['stream_url']),
                            'stream_type'    => pathinfo(parse_url($epData['stream_url'] ?? '', PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'mp4',
                            'quality'        => '1080p',
                            'resolution'     => '1080p',
                            'codec'          => 'h264',
                        ]
                    );
                }
            }

            // Sync season_count and episode_count
            $vod->season_count  = (int) $vod->vodMedia()->distinct('season_number')->where('season_number', '>', 0)->count('season_number');
            $vod->episode_count = (int) $vod->vodMedia()->whereNotNull('episode_number')->count();
            $vod->save();
        }

        // Backwards-compatible: save episode stream URLs (only updates existing records)
        if (!empty($episodeUrls) && in_array($vod->type, ['series', 'tv_show', 'anime'])) {
            foreach ($episodeUrls as $key => $url) {
                if (!$url) continue;
                [$seasonNum, $episodeNum] = array_map('intval', explode('_', $key, 2));
                $vod->vodMedia()
                    ->where('season_number', $seasonNum)
                    ->where('episode_number', $episodeNum)
                    ->update(['stream_url' => $url, 'is_available' => !empty($url)]);
            }
        }

        // Save stream URL to vod_media (movie/documentary etc — non-series, or season 0 for series)
        if ($streamUrl !== null) {
            $media = $vod->vodMedia()->where('season_number', 0)->first();
            if ($media) {
                $media->update(['stream_url' => $streamUrl, 'stream_type' => $fileExt ?? $media->stream_type, 'is_available' => !empty($streamUrl)]);
            } else {
                $vod->vodMedia()->create([
                    'season_number'  => 0,
                    'episode_number' => 1,
                    'stream_url'     => $streamUrl,
                    'stream_type'    => $fileExt ?? 'mp4',
                    'quality'        => '1080p',
                    'is_available'   => !empty($streamUrl),
                ]);
            }
        }

        return redirect()->route('admin.vod.index')
            ->with('success', 'VOD content updated successfully.');
    }

    public function destroy(Request $request, VODContent $vod): RedirectResponse
    {
        $vod->categories()->detach();
        $vod->bouquets()->detach();
        $vod->vodMedia()->delete();
        $vod->seasons()->delete();
        $vod->cast()->delete();
        $vod->crew()->delete();
        $vod->reviews()->delete();
        $vod->favorites()->delete();
        $vod->watchlist()->delete();
        $vod->watchHistory()->delete();
        $vod->forceDelete();

        return redirect()->route('admin.vod.index')
            ->with('success', 'VOD content deleted successfully.');
    }

    public function getEpisodes(Request $request, VODContent $vod): JsonResponse
    {
        $episodes = $vod->vodMedia()
            ->orderBy('season_number')
            ->orderBy('episode_number')
            ->get(['id', 'season_number', 'episode_number', 'episode_title', 'stream_url', 'air_date', 'duration', 'is_available']);

        // Group by season
        $seasons = [];
        foreach ($episodes as $ep) {
            $s = $ep->season_number ?? 0;
            if (!isset($seasons[$s])) $seasons[$s] = ['season_number' => $s, 'episodes' => []];
            $seasons[$s]['episodes'][] = $ep;
        }
        ksort($seasons);

        return response()->json(['data' => array_values($seasons)]);
    }

    public function storeEpisode(Request $request, VODContent $vod): JsonResponse
    {
        $validated = $request->validate([
            'season_number'  => 'required|integer|min:0',
            'episode_number' => 'required|integer|min:1',
            'episode_title'  => 'nullable|string|max:255',
            'stream_url'     => 'nullable|string|max:2048',
            'air_date'       => 'nullable|date',
            'duration'       => 'nullable|integer|min:0',
        ]);

        // Prevent duplicate season+episode
        $exists = $vod->vodMedia()
            ->where('season_number', $validated['season_number'])
            ->where('episode_number', $validated['episode_number'])
            ->exists();

        if ($exists) {
            return response()->json(['error' => "S{$validated['season_number']}E{$validated['episode_number']} already exists."], 422);
        }

        $episode = \App\Models\VODMedia::create([
            'vod_content_id' => $vod->id,
            'season_number'  => $validated['season_number'],
            'episode_number' => $validated['episode_number'],
            'episode_title'  => $validated['episode_title'] ?? null,
            'stream_url'     => $validated['stream_url'] ?? '',
            'air_date'       => $validated['air_date'] ?? null,
            'duration'       => $validated['duration'] ?? null,
            'stream_type'    => 'hls',
            'quality'        => '1080p',
            'resolution'     => '1080p',
            'codec'          => 'h264',
            'is_available'   => !empty($validated['stream_url']),
        ]);

        // Keep season_count / episode_count in sync
        $vod->season_count  = (int) $vod->vodMedia()->distinct('season_number')->where('season_number', '>', 0)->count('season_number');
        $vod->episode_count = (int) $vod->vodMedia()->whereNotNull('episode_number')->count();
        $vod->save();

        return response()->json(['data' => $episode], 201);
    }

    public function uploadEpisodeFile(Request $request, VODContent $vod): JsonResponse
    {
        $request->validate([
            'file'           => 'required|file|mimes:mp4,mkv,avi,mov,wmv,flv,webm|max:2097152',
            'season_number'  => 'required|integer|min:1',
            'episode_number' => 'required|integer|min:1',
        ]);

        $file = $request->file('file');
        $filename = time() . '_' . \Str::slug("{$vod->title}_S{$request->season_number}E{$request->episode_number}") . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('vod/episodes', $filename, 'public');

        return response()->json(['stream_url' => '/storage/' . $path]);
    }

    public function updateEpisode(Request $request, VODContent $vod, \App\Models\VODMedia $media): JsonResponse
    {
        if ($media->vod_content_id !== $vod->id) {
            return response()->json(['error' => 'Not found.'], 404);
        }

        $validated = $request->validate([
            'episode_title' => 'nullable|string|max:255',
            'stream_url'    => 'nullable|string|max:2048',
            'air_date'      => 'nullable|date',
            'duration'      => 'nullable|integer|min:0',
            'is_available'  => 'nullable|boolean',
        ]);

        if (array_key_exists('stream_url', $validated) && $validated['stream_url'] !== null) {
            $validated['is_available'] = !empty($validated['stream_url']);
        }

        $media->update($validated);

        return response()->json(['data' => $media->fresh()]);
    }

    public function destroyEpisode(Request $request, VODContent $vod, \App\Models\VODMedia $media): JsonResponse
    {
        if ($media->vod_content_id !== $vod->id) {
            return response()->json(['error' => 'Not found.'], 404);
        }

        $media->delete();

        $vod->season_count  = (int) $vod->vodMedia()->distinct('season_number')->where('season_number', '>', 0)->count('season_number');
        $vod->episode_count = (int) $vod->vodMedia()->whereNotNull('episode_number')->count();
        $vod->save();

        return response()->json(['message' => 'Episode deleted.']);
    }

    public function toggleStatus(Request $request, VODContent $vod): JsonResponse
    {
        $vod->is_active = !$vod->is_active;
        $vod->save();

        return response()->json([
            'message' => 'VOD status updated successfully.',
            'data' => ['is_active' => $vod->is_active],
        ]);
    }

    public function toggleFeatured(Request $request, VODContent $vod): JsonResponse
    {
        $vod->is_featured = !$vod->is_featured;
        $vod->save();

        return response()->json([
            'message' => 'VOD featured status updated successfully.',
            'data' => ['is_featured' => $vod->is_featured],
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:json,xml|max:10240',
            'format' => 'required|in:json,xml',
        ]);

        $file = $request->file('file');
        $content = file_get_contents($file->getRealPath());

        if ($validated['format'] === 'json') {
            $items = json_decode($content, true);
        } else {
            $xml = simplexml_load_string($content);
            $items = json_decode(json_encode($xml), true);
        }

        $imported = 0;
        $errors = [];

        foreach ($items as $item) {
            try {
                $vod = VODContent::create([
                    'title' => $item['title'] ?? 'Untitled',
                    'slug' => \Str::slug($item['title'] ?? 'untitled'),
                    'description' => $item['description'] ?? null,
                    'poster_url' => $item['poster_url'] ?? null,
                    'type' => $item['type'] ?? 'movie',
                    'is_active' => true,
                ]);

                if (!empty($item['categories'])) {
                    $categoryIds = ContentCategory::whereIn('name', $item['categories'])->pluck('id');
                    $vod->categories()->sync($categoryIds);
                }

                $imported++;
            } catch (\Exception $e) {
                $errors[] = ['item' => $item, 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'message' => "Import completed. {$imported} items imported.",
            'data' => ['imported' => $imported, 'errors' => $errors],
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file'          => 'nullable|file|mimes:mp4,mkv,avi,mov,wmv,flv,webm|max:2097152',
            'title'         => 'required|string|max:255',
            'type'          => 'nullable|in:movie,series,documentary,tv_show,anime,kids',
            'description'   => 'nullable|string',
            'year'          => 'nullable|integer',
            'duration'      => 'nullable|integer',
            'rating'        => 'nullable|numeric|min:0|max:10',
            'director'      => 'nullable|string',
            'genre'         => 'nullable|array',
            'cast'          => 'nullable|array',
            'poster_url'    => 'nullable|string|max:2048',
            'backdrop_url'  => 'nullable|string|max:2048',
            'trailer_url'   => 'nullable|string|max:2048',
            'tmdb_id'       => 'nullable|integer',
            'imdb_id'       => 'nullable|string|max:20',
            'category_ids'  => 'nullable|array',
            'category_ids.*'  => 'exists:content_categories,id',
            'bouquet_ids'   => 'nullable|array',
            'bouquet_ids.*'   => 'exists:bouquets,id',
            'is_active'     => 'nullable|boolean',
            'is_featured'   => 'nullable|boolean',
            'episodes'      => 'nullable|array',
        ]);

        $streamUrl = null;
        $fileExt = 'mp4';
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileExt = strtolower($file->getClientOriginalExtension());
            $filename = time() . '_' . \Str::slug($validated['title']) . '.' . $fileExt;
            $path = $file->storeAs('vod', $filename, 'public');
            $streamUrl = '/storage/' . $path;
        } elseif (!empty($validated['stream_url'])) {
            $streamUrl = $validated['stream_url'];
            $fileExt = pathinfo(parse_url($streamUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'mp4';
        }

        $slug = \Str::slug($validated['title']);
        if (VODContent::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $slug . '-' . time();
        }

        $vod = VODContent::create([
            'title'       => $validated['title'],
            'slug'        => $slug,
            'description' => $validated['description'] ?? null,
            'type'        => $validated['type'] ?? 'movie',
            'year'        => ($validated['year'] ?? null) ?: null,
            'duration'    => ($validated['duration'] ?? null) ?: null,
            'rating'      => ($validated['rating'] ?? null) ?: 0,
            'director'    => $validated['director'] ?? null,
            'genre'       => $validated['genre'] ?? null,
            'cast'        => $validated['cast'] ?? null,
            'poster_url'  => $validated['poster_url'] ?? null,
            'backdrop_url'=> $validated['backdrop_url'] ?? null,
            'trailer_url' => $validated['trailer_url'] ?? null,
            'tmdb_id'     => ($validated['tmdb_id'] ?? null) ?: null,
            'imdb_id'     => $validated['imdb_id'] ?? null,
            'season_count'  => 0,
            'episode_count' => 0,
            'is_active'   => $validated['is_active'] ?? true,
            'is_featured' => $validated['is_featured'] ?? false,
        ]);

        if (!empty($validated['category_ids'])) $vod->categories()->sync($validated['category_ids']);
        if (!empty($validated['bouquet_ids']))  $vod->bouquets()->sync($validated['bouquet_ids']);

        if ($streamUrl) {
            \App\Models\VODMedia::create([
                'vod_content_id' => $vod->id,
                'season_number'  => 0,
                'episode_number' => 1,
                'stream_url'     => $streamUrl,
                'stream_type'    => $fileExt,
                'quality'        => '1080p',
                'resolution'     => '1080p',
                'codec'          => 'h264',
                'is_available'   => true,
            ]);
        }

        if (!empty($validated['episodes']) && in_array($vod->type, ['series', 'tv_show'])) {
            foreach ($validated['episodes'] as $seasonNum => $episodes) {
                foreach ($episodes as $episodeNum => $file) {
                    if ($file instanceof \Illuminate\Http\UploadedFile) {
                        $filename = time() . '_' . \Str::slug("{$validated['title']}_S{$seasonNum}E{$episodeNum}") . '.' . $file->getClientOriginalExtension();
                        $path = $file->storeAs('vod/episodes', $filename, 'public');
                        \App\Models\VODMedia::where('vod_content_id', $vod->id)
                            ->where('season_number', $seasonNum)
                            ->where('episode_number', $episodeNum)
                            ->update(['stream_url' => '/storage/' . $path, 'is_available' => true]);
                    }
                }
            }
        }

        return redirect()->route('admin.vod.show', $vod->id)
            ->with('success', 'VOD content uploaded successfully.');
    }

    public function importFromUrl(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'url' => 'required|url',
            'category_ids' => 'nullable|array',
            'bouquet_ids' => 'nullable|array',
        ]);
        $response = \Illuminate\Support\Facades\Http::timeout(30)->get($validated['url']);
        if (!$response->successful()) {
            return back()->with('error', 'Failed to fetch content from URL.');
        }
        $content = $response->body();
        $ext = pathinfo(parse_url($validated['url'], PHP_URL_PATH), PATHINFO_EXTENSION);
        return $this->processImportContent($content, $ext, $validated['category_ids'] ?? [], $validated['bouquet_ids'] ?? []);
    }

    public function importFromXtream(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'server_url' => 'required|url',
            'username' => 'required|string',
            'password' => 'required|string',
            'type' => 'nullable|in:live,vod,series',
            'category_ids' => 'nullable|array',
            'bouquet_ids' => 'nullable|array',
        ]);
        $type = $validated['type'] ?? 'vod';
        $actionMap = ['live' => 'get_live_streams', 'vod' => 'get_vod_streams', 'series' => 'get_series'];
        $response = \Illuminate\Support\Facades\Http::timeout(30)->get($validated['server_url'], [
            'username' => $validated['username'],
            'password' => $validated['password'],
            'action' => $actionMap[$type] ?? 'get_vod_streams',
        ]);
        if (!$response->successful()) {
            return back()->with('error', 'Failed to connect to Xtream server.');
        }
        $data = $response->json();
        $streams = $data['streams'] ?? [];
        $imported = 0;
        foreach ($streams as $stream) {
            VODContent::create([
                'title' => $stream['name'] ?? 'Untitled',
                'type' => $type === 'series' ? 'series' : 'movie',
                'stream_id' => $stream['stream_id'] ?? null,
                'stream_icon' => $stream['stream_icon'] ?? null,
                'rating' => $stream['rating'] ?? 0,
                'season_count' => 0,
                'episode_count' => 0,
                'category_ids' => $stream['category_ids'] ?? null,
                'is_active' => true,
            ]);
            $imported++;
        }
        return back()->with('success', "Imported {$imported} items from Xtream server.");
    }

    private function processImportContent(string $content, string $ext, array $categoryIds, array $bouquetIds): RedirectResponse
    {
        $items = [];
        if ($ext === 'json' || $ext === '') {
            $items = json_decode($content, true) ?? [];
        } elseif ($ext === 'xml') {
            $xml = simplexml_load_string($content);
            $items = $xml ? json_decode(json_encode($xml), true) : [];
            if (isset($items['channel'])) {
                $items = is_array($items['channel']) && isset($items['channel'][0]) ? $items['channel'] : [$items['channel']];
            }
        }

        if (empty($items)) {
            return back()->with('error', 'No valid content found in the provided URL.');
        }

        $imported = 0;
        foreach ($items as $item) {
            VODContent::create([
                'title' => $item['title'] ?? $item['name'] ?? 'Untitled',
                'slug' => \Str::slug($item['title'] ?? $item['name'] ?? 'untitled'),
                'description' => $item['description'] ?? $item['plot'] ?? null,
                'poster_url' => $item['poster_url'] ?? $item['stream_icon'] ?? null,
                'type' => $item['type'] ?? 'movie',
                'year' => $item['year'] ?? null,
                'rating' => $item['rating'] ?? 0,
                'tmdb_id' => $item['tmdb_id'] ?? null,
                'is_active' => true,
            ]);
            $imported++;
        }

        return back()->with('success', "Imported {$imported} items from URL.");
    }

    public function searchTMDB(Request $request): JsonResponse
    {
        if (!$this->tmdbService->isConfigured()) {
            return response()->json(['error' => 'TMDB API key not configured. Please set it in Settings > VOD.'], 422);
        }

        $validated = $request->validate([
            'query' => 'required|string|min:2',
            'type' => 'nullable|in:movie,tv',
        ]);

        $type = $validated['type'] ?? 'movie';
        $results = $this->tmdbService->search($validated['query'], $type);

        return response()->json(['data' => $results]);
    }

    public function tmdbDetails(Request $request): JsonResponse
    {
        if (!$this->tmdbService->isConfigured()) {
            return response()->json(['error' => 'TMDB API key not configured. Please set it in Settings > VOD.'], 422);
        }

        $validated = $request->validate([
            'tmdb_id' => 'required|integer',
            'type' => 'required|in:movie,tv',
            'season' => 'nullable|integer|min:1',
        ]);

        $details = $validated['type'] === 'tv'
            ? $this->tmdbService->getTVShow($validated['tmdb_id'])
            : $this->tmdbService->getMovie($validated['tmdb_id']);

        if (!$details) {
            return response()->json(['error' => 'Failed to fetch details from TMDB'], 404);
        }

        if ($validated['type'] === 'tv') {
            if (isset($validated['season'])) {
                $details['episodes'] = $this->tmdbService->getTVEpisodes($validated['tmdb_id'], $validated['season']);
            } else {
                if (isset($details['seasons']) && is_array($details['seasons'])) {
                    foreach ($details['seasons'] as $seasonIndex => $season) {
                        $seasonNum = $season['season_number'] ?? 0;
                        if ($seasonNum === 0) {
                            continue;
                        }
                        $details['seasons'][$seasonIndex]['episodes'] = $this->tmdbService->getTVEpisodes($validated['tmdb_id'], $seasonNum);
                    }
                }
            }
        }

        return response()->json(['data' => $details]);
    }

    public function importFromTMDB(Request $request): JsonResponse
    {
        if (!$this->tmdbService->isConfigured()) {
            return response()->json(['error' => 'TMDB API key not configured. Please set it in Settings > VOD.'], 422);
        }

        $validated = $request->validate([
            'tmdb_id' => 'required|string',
            'type' => 'nullable|in:movie,tv',
        ]);

        // Auto-detect type if not provided
        $type = $validated['type'] ?? null;
        $tmdbData = null;
        $detectedType = null;

        if ($type) {
            // Use provided type
            $tmdbData = $type === 'movie'
                ? $this->tmdbService->getMovie($validated['tmdb_id'])
                : $this->tmdbService->getTVShow($validated['tmdb_id']);
            $detectedType = $type;
        } else {
            // Auto-detect: try movie first, then TV show
            $tmdbData = $this->tmdbService->getMovie($validated['tmdb_id']);
            if ($tmdbData) {
                $detectedType = 'movie';
            } else {
                $tmdbData = $this->tmdbService->getTVShow($validated['tmdb_id']);
                if ($tmdbData) {
                    $detectedType = 'tv';
                }
            }
        }

        if (!$tmdbData) {
            return response()->json(['error' => 'Failed to fetch data from TMDB'], 404);
        }

        // Ensure we have seasons/episodes for TV shows
        if ($detectedType === 'tv' && (!isset($tmdbData['seasons']) || empty($tmdbData['seasons']))) {
            $fullData = $this->tmdbService->getTVShow($validated['tmdb_id'], ['seasons']);
            if ($fullData && isset($fullData['seasons'])) {
                $tmdbData['seasons'] = $fullData['seasons'];
            }
        }

        $vod = VODContent::create([
            'title' => $tmdbData['title'] ?? $tmdbData['name'] ?? 'Untitled',
            'description' => $tmdbData['overview'] ?? null,
            'year' => $tmdbData['release_year'] ?? $tmdbData['first_air_date_year'] ?? null,
            'rating' => $tmdbData['vote_average'] ?? 0,
            'poster_url' => $tmdbData['poster_url'] ?? null,
            'backdrop_url' => $tmdbData['backdrop_url'] ?? null,
            'genre' => $tmdbData['genres'] ?? null,
            'cast' => $tmdbData['cast'] ?? null,
            'director' => $tmdbData['director'] ?? null,
            'duration' => $tmdbData['runtime'] ?? $tmdbData['episode_run_time'][0] ?? null,
            'tmdb_id' => $validated['tmdb_id'],
            'imdb_id' => $tmdbData['imdb_id'] ?? null,
            'type' => $detectedType === 'tv' ? 'series' : 'movie',
            'slug' => \Str::slug($tmdbData['title'] ?? $tmdbData['name'] ?? 'untitled') . '-' . $validated['tmdb_id'],
            'season_count' => $tmdbData['number_of_seasons'] ?? 0,
            'episode_count' => $tmdbData['number_of_episodes'] ?? 0,
            'is_active' => true,
        ]);

        \App\Models\TMDBMapping::create([
            'content_type' => 'vod',
            'content_id' => $vod->id,
            'tmdb_id' => $validated['tmdb_id'],
            'media_type' => $detectedType,
            'is_primary' => true,
            'mapped_at' => now(),
        ]);

        // Import seasons and episodes for TV shows
        $seasonsCount = 0;
        $episodesCount = 0;
        if ($detectedType === 'tv' && isset($tmdbData['seasons'])) {
            foreach ($tmdbData['seasons'] as $season) {
                $seasonNum = $season['season_number'] ?? 0;
                if ($seasonNum === 0) continue;
                $seasonsCount++;
                $episodes = $this->tmdbService->getTVEpisodes($validated['tmdb_id'], $seasonNum);
                foreach ($episodes as $ep) {
                    \App\Models\VODMedia::create([
                        'vod_content_id' => $vod->id,
                        'season_number' => $ep['season_number'] ?? $seasonNum,
                        'episode_number' => $ep['episode_number'] ?? null,
                        'episode_title' => $ep['title'] ?? null,
                        'stream_url' => '',
                        'stream_type' => 'hls',
                        'quality' => '1080p',
                        'resolution' => '1080p',
                        'codec' => 'h264',
                        'duration' => $ep['runtime'] ?? null,
                        'air_date' => $ep['air_date'] ?? null,
                        'still_url' => $ep['still_url'] ?? null,
                        'is_available' => true,
                    ]);
                    $episodesCount++;
                }
            }
        }

        return response()->json([
            'message' => 'Content imported from TMDB successfully',
            'vod_id' => $vod->id,
            'detected_type' => $detectedType,
            'seasons' => $seasonsCount,
            'episodes' => $episodesCount,
        ]);
    }

    public function autoPopulateTMDB(Request $request, $vod): JsonResponse
    {
        if (!$this->tmdbService->isConfigured()) {
            return response()->json(['error' => 'TMDB API key not configured. Please set it in Settings > VOD.'], 422);
        }

        $vod = VODContent::findOrFail($vod);
        $result = $this->tmdbService->autoPopulateVOD($vod);

        return response()->json($result);
    }

    public function tmdbEpisodes(Request $request, $vod): JsonResponse
    {
        if (!$this->tmdbService->isConfigured()) {
            return response()->json(['error' => 'TMDB API key not configured. Please set it in Settings > VOD.'], 422);
        }

        $vod = VODContent::findOrFail($vod);
        $validated = $request->validate([
            'season' => 'required|integer|min:1',
        ]);

        $tmdbId = $vod->tmdb_id;
        if (!$tmdbId) {
            return response()->json(['error' => 'This VOD is not linked to TMDB'], 422);
        }

        $episodes = $this->tmdbService->getTVEpisodes($tmdbId, $validated['season']);
        $existing = $vod->vodMedia()->where('season_number', $validated['season'])->get()->keyBy('episode_number');

        $merged = [];
        foreach ($episodes as $ep) {
            $epNum = $ep['episode_number'] ?? null;
            $existingRecord = $existing[$epNum] ?? null;
            $merged[] = [
                'tmdb_episode_id' => $ep['id'] ?? null,
                'season_number' => $ep['season_number'] ?? $validated['season'],
                'episode_number' => $epNum,
                'title' => $ep['title'] ?? $existingRecord?->episode_title ?? '',
                'stream_url' => $existingRecord?->stream_url ?? '',
                'overview' => $ep['overview'] ?? '',
                'air_date' => $ep['air_date'] ?? null,
                'duration' => $ep['runtime'] ?? null,
                'still_url' => $ep['still_url'] ?? null,
                'existing' => $existingRecord !== null,
                'media_id' => $existingRecord?->id,
            ];
        }

        return response()->json(['data' => $merged]);
    }

    public function trending(Request $request): JsonResponse
    {
        if (!$this->tmdbService->isConfigured()) {
            return response()->json(['error' => 'TMDB API key not configured. Please set it in Settings > VOD.'], 422);
        }

        $validated = $request->validate([
            'time_window' => 'nullable|in:day,week',
        ]);

        $timeWindow = $validated['time_window'] ?? 'week';
        $results = $this->tmdbService->getTrending($timeWindow);

        return response()->json(['data' => $results]);
    }

    public function popular(Request $request): JsonResponse
    {
        if (!$this->tmdbService->isConfigured()) {
            return response()->json(['error' => 'TMDB API key not configured. Please set it in Settings > VOD.'], 422);
        }

        $validated = $request->validate([
            'type' => 'nullable|in:movie,tv',
        ]);

        $type = $validated['type'] ?? 'movie';
        $results = $this->tmdbService->getPopular($type);

        return response()->json(['data' => $results]);
    }
}
