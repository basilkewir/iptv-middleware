<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserWatchHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WatchHistoryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'channel_id' => 'nullable|required_without:vod_content_id|exists:channels,id',
                'vod_content_id' => 'nullable|required_without:channel_id|exists:vod_contents,id',
                'duration_watched' => 'nullable|integer|min:0',
                'progress' => 'nullable|numeric|min:0|max:100',
                'completed' => 'boolean',
            ]);

            $userId = $request->user()->id;

            $history = UserWatchHistory::updateOrCreate(
                [
                    'user_id' => $userId,
                    'channel_id' => $validated['channel_id'] ?? null,
                    'vod_content_id' => $validated['vod_content_id'] ?? null,
                ],
                [
                    'watched_at' => now(),
                    'duration_watched' => $validated['duration_watched'] ?? 0,
                    'progress' => $validated['progress'] ?? 0,
                    'completed' => $validated['completed'] ?? false,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Watch history saved.',
                'data' => [
                    'history' => $history,
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while saving watch history.',
            ], 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = UserWatchHistory::with(['channel', 'vodContent'])
                ->where('user_id', $request->user()->id);

            if ($request->filled('type')) {
                if ($request->type === 'channel') {
                    $query->whereNotNull('channel_id');
                } elseif ($request->type === 'vod') {
                    $query->whereNotNull('vod_content_id');
                }
            }

            $history = $query->orderBy('watched_at', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $history,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching watch history.',
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'duration_watched' => 'nullable|integer|min:0',
                'progress' => 'nullable|numeric|min:0|max:100',
                'completed' => 'boolean',
            ]);

            $history = UserWatchHistory::where('id', $id)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            $history->update([
                'duration_watched' => $validated['duration_watched'] ?? $history->duration_watched,
                'progress' => $validated['progress'] ?? $history->progress,
                'completed' => $validated['completed'] ?? $history->completed,
                'watched_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Watch history updated.',
                'data' => [
                    'history' => $history,
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Watch history not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating watch history.',
            ], 500);
        }
    }
}
