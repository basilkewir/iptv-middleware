<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ChannelPushDestination;
use App\Models\PushDestination;
use App\Services\StreamingService\ChannelPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class ChannelPushController extends Controller
{
    public function index(ChannelPushService $pushService): Response
    {
        $channels = Channel::where('is_active', true)
            ->orderBy('channel_number')
            ->with(['categories', 'bouquets'])
            ->get([
                'id', 'name', 'channel_number', 'stream_url', 'stream_type',
                'active_stream_url', 'logo_url', 'quality_level',
                'is_active', 'source_status',
            ]);

        $destinations = PushDestination::orderBy('name')->get();

        $activePushes = $pushService->getActivePushes();

        $pushMap = [];
        foreach (ChannelPushDestination::where('status', 'pushing')->get() as $cpd) {
            $pushMap[$cpd->channel_id][$cpd->push_destination_id] = true;
        }

        return Inertia::render('Admin/Channels/StreamPush', [
            'channels' => $channels,
            'destinations' => $destinations,
            'activePushes' => $activePushes,
            'pushMap' => $pushMap,
        ]);
    }

    public function storeDestination(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'protocol' => 'required|in:rtmp,srt',
            'url' => 'required|string|max:500',
            'stream_key' => 'nullable|string|max:500',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $destination = PushDestination::create($validated);

        return response()->json([
            'message' => 'Destination created.',
            'destination' => $destination->makeVisible(['username']),
        ]);
    }

    public function updateDestination(Request $request): JsonResponse
    {
        $destination = PushDestination::findOrFail(Route::current()->parameter('destination'));

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'protocol' => 'required|in:rtmp,srt',
            'url' => 'required|string|max:500',
            'stream_key' => 'nullable|string|max:500',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        $destination->update($validated);

        return response()->json([
            'message' => 'Destination updated.',
            'destination' => $destination->makeVisible(['username']),
        ]);
    }

    public function destroyDestination(): JsonResponse
    {
        $destination = PushDestination::findOrFail(Route::current()->parameter('destination'));
        ChannelPushDestination::where('push_destination_id', $destination->id)
            ->where('status', 'pushing')
            ->each(fn (ChannelPushDestination $cpd) => app(ChannelPushService::class)->stopPush($cpd));

        $destination->delete();

        return response()->json(['message' => 'Destination deleted.']);
    }

    public function startPush(Request $request, ChannelPushService $pushService): JsonResponse
    {
        $validated = $request->validate([
            'channel_id' => 'required|exists:channels,id',
            'destination_id' => 'required|exists:push_destinations,id',
        ]);

        $channel = Channel::findOrFail($validated['channel_id']);
        $destination = PushDestination::findOrFail($validated['destination_id']);

        if (! $destination->is_active) {
            return response()->json(['message' => 'Destination is disabled.'], 422);
        }

        try {
            $push = $pushService->startPush($channel, $destination);

            return response()->json([
                'message' => "Push started: {$channel->name} → {$destination->name}",
                'push' => [
                    'id' => $push->id,
                    'status' => $push->status,
                    'started_at' => $push->started_at->toISOString(),
                    'pid' => $push->ffmpeg_pid,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function stopPush(Request $request, ChannelPushService $pushService): JsonResponse
    {
        $validated = $request->validate([
            'channel_id' => 'required|exists:channels,id',
            'destination_id' => 'required|exists:push_destinations,id',
        ]);

        $push = ChannelPushDestination::where('channel_id', $validated['channel_id'])
            ->where('push_destination_id', $validated['destination_id'])
            ->first();

        if (! $push || ! $push->isPushing()) {
            return response()->json(['message' => 'No active push found.'], 404);
        }

        $pushService->stopPush($push);

        return response()->json(['message' => 'Push stopped.']);
    }

    public function stopAll(ChannelPushService $pushService): JsonResponse
    {
        $pushService->stopAllPushes();

        return response()->json(['message' => 'All pushes stopped.']);
    }
}
