<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\VOD\VODService;
use App\Models\VODContent;
use App\Models\VODEpisode;
use App\Models\VODMedia;
use App\Models\VODWatchHistory;
use App\Models\VODFavorite;
use App\Models\VODWatchlist;
use App\Models\ContentCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VODController extends Controller
{
    protected VODService $vodService;

    public function __construct(VODService $vodService)
    {
        $this->vodService = $vodService;
    }

    /**
     * Display VOD library
     */
    public function index(Request $request)
    {
        $filters = $request->only(['type', 'category', 'genre', 'year', 'quality', 'rating', 'search', 'sort']);

        $vod = $this->vodService->getVODForClient(auth()->id(), $filters);

        $categories = ContentCategory::where('is_active', true)->get();

        $genres = VODContent::where('is_active', true)
            ->whereNotNull('genre')
            ->select('genre')
            ->get()
            ->flatMap(function ($item) {
                $genre = $item->genre;
                if (is_array($genre)) {
                    return $genre;
                }
                $decoded = json_decode($genre, true);
                return is_array($decoded) ? $decoded : [];
            })
            ->unique()
            ->filter()
            ->values();

        $years = VODContent::where('is_active', true)
            ->whereNotNull('year')
            ->select('year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');

        $qualityLevels = ['4k', 'fhd', 'hd', 'sd', 'low', 'mobile'];

        return Inertia::render('Client/VOD/Index', [
            'vod' => $vod,
            'filters' => $filters,
            'categories' => $categories,
            'genres' => $genres,
            'years' => $years,
            'qualityLevels' => $qualityLevels,
        ]);
    }

    /**
     * Display movie details
     */
    public function showMovie($id)
    {
        $movie = $this->vodService->getMovie($id, auth()->id());

        return Inertia::render('Client/VOD/MovieDetails', [
            'movie' => $movie,
            'similar' => $movie->similar ?? [],
        ]);
    }

    /**
     * Display series details
     */
    public function showSeries($id)
    {
        $series = $this->vodService->getSeries($id, auth()->id());

        return Inertia::render('Client/VOD/SeriesDetails', [
            'series' => $series,
            'similar' => $series->similar ?? [],
        ]);
    }

    /**
     * Play VOD content
     */
    public function play($id)
    {
        $vod = VODContent::findOrFail($id);

        $media = $vod->vodMedia()->where('is_available', true)->first();

        if (!$media) {
            abort(404, 'Media file not found');
        }

        return Inertia::render('Client/VOD/Player', [
            'vod' => $vod,
            'media' => $media,
            'watchProgress' => $this->vodService->getWatchProgress(auth()->id(), $vod->id),
        ]);
    }

    /**
     * Play episode
     */
    public function playEpisode($id)
    {
        $episode = VODEpisode::with(['season.vodContent'])->findOrFail($id);
        $vod = $episode->season->vodContent;

        return Inertia::render('Client/VOD/Player', [
            'vod' => $vod,
            'episode' => $episode,
            'watchProgress' => $this->vodService->getEpisodeWatchProgress(auth()->id(), $episode->id),
            'nextEpisode' => $this->vodService->getNextEpisode(auth()->id(), $vod->id),
        ]);
    }

    /**
     * Record watch progress
     */
    public function progress(Request $request)
    {
        $request->validate([
            'vod_id' => 'required|exists:vod_content,id',
            'progress' => 'required|integer|min:0|max:100',
            'duration' => 'nullable|integer',
            'episode_id' => 'nullable|exists:vod_episodes,id',
        ]);

        $this->vodService->recordWatch(
            auth()->id(),
            $request->vod_id,
            $request->progress,
            $request->duration,
            $request->episode_id
        );

        return response()->json(['success' => true]);
    }

    /**
     * Toggle favorite
     */
    public function toggleFavorite(Request $request)
    {
        $request->validate([
            'vod_id' => 'required|exists:vod_content,id',
        ]);

        $isFavorite = $this->vodService->isFavorite(auth()->id(), $request->vod_id);

        if ($isFavorite) {
            $this->vodService->removeFromFavorites(auth()->id(), $request->vod_id);
            $message = 'Removed from favorites';
        } else {
            $this->vodService->addToFavorites(auth()->id(), $request->vod_id);
            $message = 'Added to favorites';
        }

        return response()->json([
            'success' => true,
            'is_favorite' => !$isFavorite,
            'message' => $message,
        ]);
    }

    /**
     * Toggle watchlist
     */
    public function toggleWatchlist(Request $request)
    {
        $request->validate([
            'vod_id' => 'required|exists:vod_content,id',
        ]);

        $isWatchlisted = $this->vodService->isWatchlisted(auth()->id(), $request->vod_id);

        if ($isWatchlisted) {
            $this->vodService->removeFromWatchlist(auth()->id(), $request->vod_id);
            $message = 'Removed from watchlist';
        } else {
            $this->vodService->addToWatchlist(auth()->id(), $request->vod_id);
            $message = 'Added to watchlist';
        }

        return response()->json([
            'success' => true,
            'is_watchlisted' => !$isWatchlisted,
            'message' => $message,
        ]);
    }

    /**
     * Add review
     */
    public function review(Request $request)
    {
        $request->validate([
            'vod_id' => 'required|exists:vod_content,id',
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review = $this->vodService->addReview(
            auth()->id(),
            $request->vod_id,
            $request->rating,
            $request->comment,
            $request->title
        );

        return response()->json([
            'success' => true,
            'review' => $review,
            'message' => 'Review submitted successfully',
        ]);
    }

    /**
     * Get user's watch history
     */
    public function history()
    {
        $history = VODWatchHistory::with(['vodContent', 'episode'])
            ->where('user_id', auth()->id())
            ->orderBy('last_watched', 'desc')
            ->paginate(20);

        return Inertia::render('Client/VOD/History', [
            'history' => $history,
        ]);
    }

    /**
     * Get user's favorites
     */
    public function favorites()
    {
        $favorites = VODFavorite::with('vodContent')
            ->where('user_id', auth()->id())
            ->orderBy('favorite_order')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Client/VOD/Favorites', [
            'favorites' => $favorites,
        ]);
    }

    /**
     * Get user's watchlist
     */
    public function watchlist()
    {
        $watchlist = VODWatchlist::with('vodContent')
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Client/VOD/Watchlist', [
            'watchlist' => $watchlist,
        ]);
    }

    /**
     * Search VOD content
     */
    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2',
            'type' => 'nullable|in:movie,series,documentary,tv_show,anime,kids',
        ]);

        $results = VODContent::where('is_active', true)
            ->where('is_available', true)
            ->where(function ($query) use ($request) {
                $query->where('title', 'LIKE', "%{$request->q}%")
                    ->orWhere('description', 'LIKE', "%{$request->q}%")
                    ->orWhere('director', 'LIKE', "%{$request->q}%");
            });

        if ($request->type) {
            $results->where('type', $request->type);
        }

        $results = $results->limit(50)->get();

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }

    /**
     * Get streaming URL for VOD
     */
    public function stream($id)
    {
        $media = VODMedia::findOrFail($id);

        $vod = null;
        if ($media->vod_content_id) {
            $vod = VODContent::find($media->vod_content_id);
        }

        if (!$vod) {
            abort(404);
        }

        $this->vodService->recordWatch(
            auth()->id(),
            $vod->id,
            0,
            null,
            null
        );

        return response()->json([
            'url' => $media->stream_url,
            'quality' => $media->quality ?? 'hd',
            'duration' => $media->duration,
        ]);
    }
}