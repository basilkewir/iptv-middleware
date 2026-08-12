<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\EPGProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EPGController extends Controller
{
    public function channelEPG(Request $request, string $channelId): JsonResponse
    {
        try {
            $channel = Channel::findOrFail($channelId);

            $query = EPGProgram::where('channel_id', $channelId)
                ->orderBy('start_time', 'asc');

            if ($request->filled('date')) {
                $date = $request->date;
                $query->whereDate('start_time', $date);
            } else {
                $query->where('end_time', '>=', now()->subHours(2));
            }

            $programs = $query->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'channel' => $channel,
                    'programs' => $programs,
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
                'message' => 'An error occurred while fetching EPG data.',
            ], 500);
        }
    }

    public function programs(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            $query = EPGProgram::with('channel')
                ->where('start_time', '>=', $request->start_date)
                ->where('end_time', '<=', $request->end_date)
                ->orderBy('start_time', 'asc');

            if ($request->filled('channel_id')) {
                $query->where('channel_id', $request->channel_id);
            }

            $programs = $query->paginate($request->get('per_page', 100));

            return response()->json([
                'success' => true,
                'data' => $programs,
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
                'message' => 'An error occurred while fetching programs.',
            ], 500);
        }
    }

    public function current(Request $request): JsonResponse
    {
        try {
            $query = EPGProgram::with('channel')
                ->where('start_time', '<=', now())
                ->where('end_time', '>=', now());

            if ($request->filled('channel_id')) {
                $query->where('channel_id', $request->channel_id);
            }

            $programs = $query->orderBy('start_time', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'programs' => $programs,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching current programs.',
            ], 500);
        }
    }

    public function upcoming(Request $request): JsonResponse
    {
        try {
            $query = EPGProgram::with('channel')
                ->where('start_time', '>', now())
                ->orderBy('start_time', 'asc');

            if ($request->filled('channel_id')) {
                $query->where('channel_id', $request->channel_id);
            }

            $limit = $request->get('limit', 50);
            $programs = $query->limit($limit)->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'programs' => $programs,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching upcoming programs.',
            ], 500);
        }
    }
}
