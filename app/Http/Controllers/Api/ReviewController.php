<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserReview;
use App\Models\VODContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'vod_content_id' => 'required|exists:vod_contents,id',
                'rating' => 'required|numeric|min:1|max:10',
                'title' => 'nullable|string|max:255',
                'review' => 'nullable|string',
            ]);

            $userId = $request->user()->id;

            $existingReview = UserReview::where('user_id', $userId)
                ->where('vod_content_id', $validated['vod_content_id'])
                ->first();

            if ($existingReview) {
                $existingReview->update([
                    'rating' => $validated['rating'],
                    'title' => $validated['title'] ?? $existingReview->title,
                    'review' => $validated['review'] ?? $existingReview->review,
                ]);

                $existingReview->load('user');

                return response()->json([
                    'success' => true,
                    'message' => 'Review updated successfully.',
                    'data' => [
                        'review' => $existingReview,
                    ],
                ], 200);
            }

            $review = UserReview::create([
                'user_id' => $userId,
                'vod_content_id' => $validated['vod_content_id'],
                'rating' => $validated['rating'],
                'title' => $validated['title'] ?? null,
                'review' => $validated['review'] ?? null,
                'is_approved' => false,
            ]);

            $review->load('user');

            return response()->json([
                'success' => true,
                'message' => 'Review submitted successfully.',
                'data' => [
                    'review' => $review,
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
                'message' => 'An error occurred while submitting review.',
            ], 500);
        }
    }

    public function index(Request $request, string $vodContentId): JsonResponse
    {
        try {
            VODContent::where('id', $vodContentId)->where('is_active', true)->firstOrFail();

            $reviews = UserReview::with('user')
                ->where('vod_content_id', $vodContentId)
                ->where('is_approved', true)
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));

            $averageRating = UserReview::where('vod_content_id', $vodContentId)
                ->where('is_approved', true)
                ->avg('rating');

            return response()->json([
                'success' => true,
                'data' => [
                    'reviews' => $reviews,
                    'average_rating' => round($averageRating, 1),
                ],
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'VOD content not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching reviews.',
            ], 500);
        }
    }
}
