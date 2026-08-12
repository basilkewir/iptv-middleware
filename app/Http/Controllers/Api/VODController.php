<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentCategory;
use App\Models\VODContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VODController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = VODContent::with('categories')
                ->where('is_active', true);

            if ($request->filled('category')) {
                $query->whereHas('categories', function ($q) use ($request) {
                    $q->where('slug', $request->category);
                });
            }

            if ($request->filled('genre')) {
                $query->whereJsonContains('genre', $request->genre);
            }

            if ($request->filled('year')) {
                $query->where('year', $request->year);
            }

            if ($request->filled('rating_min')) {
                $query->where('rating', '>=', $request->rating_min);
            }

            if ($request->filled('rating_max')) {
                $query->where('rating', '<=', $request->rating_max);
            }

            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            $query->orderBy('created_at', 'desc');

            $vod = $query->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $vod,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching VOD content.',
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $vod = VODContent::with(['categories', 'vodMedia', 'reviews' => function ($q) {
                $q->where('is_approved', true)->orderBy('created_at', 'desc')->limit(10);
            }, 'reviews.user'])->where('is_active', true)->where(function ($q) use ($id) {
                $q->where('slug', $id)->orWhere('id', $id);
            })->firstOrFail();

            $vod->increment('view_count');

            return response()->json([
                'success' => true,
                'data' => [
                    'content' => $vod,
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
                'message' => 'An error occurred while fetching VOD content.',
            ], 500);
        }
    }

    public function categories(): JsonResponse
    {
        try {
            $categories = ContentCategory::withCount('vodContent')
                ->where('is_active', true)
                ->orderBy('sort_order', 'asc')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'categories' => $categories,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching categories.',
            ], 500);
        }
    }

    public function genres(): JsonResponse
    {
        try {
            $genres = VODContent::where('is_active', true)
                ->selectRaw('DISTINCT genre')
                ->pluck('genre')
                ->flatten()
                ->unique()
                ->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'genres' => $genres,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching genres.',
            ], 500);
        }
    }

    public function latest(): JsonResponse
    {
        try {
            $content = VODContent::with('categories')
                ->where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'content' => $content,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching latest content.',
            ], 500);
        }
    }

    public function featured(): JsonResponse
    {
        try {
            $content = VODContent::with('categories')
                ->where('is_active', true)
                ->where('is_featured', true)
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'content' => $content,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching featured content.',
            ], 500);
        }
    }

    public function search(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'q' => 'required|string|min:2',
            ]);

            $query = $request->q;

            $content = VODContent::with('categories')
                ->where('is_active', true)
                ->where(function ($q) use ($query) {
                    $q->where('title', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%")
                        ->orWhereJsonContains('genre', $query)
                        ->orWhereJsonContains('cast', $query);
                })
                ->orderBy('title', 'asc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $content,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while searching.',
            ], 500);
        }
    }

    public function similar(string $id): JsonResponse
    {
        try {
            $content = VODContent::findOrFail($id);

            $categoryIds = $content->categories()->pluck('content_categories.id');

            $similar = VODContent::with('categories')
                ->where('is_active', true)
                ->where('id', '!=', $id)
                ->whereHas('categories', function ($q) use ($categoryIds) {
                    $q->whereIn('content_categories.id', $categoryIds);
                })
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'similar' => $similar,
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
                'message' => 'An error occurred while fetching similar content.',
            ], 500);
        }
    }

    public function seasons(string $id): JsonResponse
    {
        try {
            $content = VODContent::with(['vodMedia' => function ($q) {
                $q->orderBy('quality', 'asc');
            }])->where('is_active', true)->where(function ($q) use ($id) {
                $q->where('slug', $id)->orWhere('id', $id);
            })->firstOrFail();

            if ($content->type !== 'series') {
                return response()->json([
                    'success' => false,
                    'message' => 'This content is not a series.',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'content' => $content,
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
                'message' => 'An error occurred while fetching seasons.',
            ], 500);
        }
    }
}
