<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ContentCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Channel::with('categories')
                ->where('is_active', true);

            if ($request->filled('category')) {
                $query->whereHas('categories', function ($q) use ($request) {
                    $q->where('slug', $request->category);
                });
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');

            $channels = $query->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $channels,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching channels.',
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $channel = Channel::with(['categories', 'epgPrograms' => function ($q) {
                $q->where('start_time', '>=', now()->subHours(2))
                    ->where('end_time', '>=', now())
                    ->orderBy('start_time', 'asc')
                    ->limit(20);
            }])->where('is_active', true)->where(function ($q) use ($id) {
                $q->where('slug', $id)->orWhere('id', $id);
            })->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => [
                    'channel' => $channel,
                ],
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Channel not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching the channel.',
            ], 500);
        }
    }

    public function categories(): JsonResponse
    {
        try {
            $categories = ContentCategory::withCount('channels')
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
}
