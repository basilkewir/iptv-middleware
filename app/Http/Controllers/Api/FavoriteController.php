<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserFavorite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $favorites = UserFavorite::with(['vodContent', 'channel'])
                ->where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $favorites,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching favorites.',
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'vod_content_id' => 'nullable|required_without:channel_id|exists:vod_contents,id',
                'channel_id' => 'nullable|required_without:vod_content_id|exists:channels,id',
            ]);

            $userId = $request->user()->id;

            $existing = UserFavorite::where('user_id', $userId)
                ->where(function ($q) use ($validated) {
                    if (isset($validated['vod_content_id'])) {
                        $q->where('vod_content_id', $validated['vod_content_id']);
                    }
                    if (isset($validated['channel_id'])) {
                        $q->where('channel_id', $validated['channel_id']);
                    }
                })
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'This item is already in your favorites.',
                ], 409);
            }

            $favorite = UserFavorite::create(array_merge(
                ['user_id' => $userId],
                $validated
            ));

            $favorite->load(['vodContent', 'channel']);

            return response()->json([
                'success' => true,
                'message' => 'Added to favorites.',
                'data' => [
                    'favorite' => $favorite,
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
                'message' => 'An error occurred while adding favorite.',
            ], 500);
        }
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $favorite = UserFavorite::where('id', $id)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            $favorite->delete();

            return response()->json([
                'success' => true,
                'message' => 'Removed from favorites.',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Favorite not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while removing favorite.',
            ], 500);
        }
    }
}
