<?php

declare(strict_types=1);

namespace App\Services\XcVm;

use App\Models\Channel;
use App\Models\XcVmMapping;
use App\Services\XcVm\Syncers\ChannelSyncer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * UDP → XC-VM Bridge
 *
 * The middleware reads UDP/RTP multicast streams via its own FFmpeg ingest
 * and writes per-channel HLS playlists to storage/app/streams/hls/{id}/.
 * XC-VM cannot join a multicast group directly, so this bridge:
 *
 *   1. Detects when a multicast channel's HLS playlist becomes available
 *      (i.e. the ingest has started writing segments).
 *   2. Pushes the loopback HLS URL (http://127.0.0.1:{port}/hls/{id}/playlist.m3u8)
 *      to XC-VM as the stream_source for that channel.
 *   3. Calls start_stream on XC-VM so it begins serving the HLS feed.
 *
 * This is called by the watchdog scheduler every minute so XC-VM always
 * has the correct source URL even after an ingest restart.
 */
class UdpXcVmBridge
{
    // Only re-push to XC-VM if the playlist was updated within this window.
    // Avoids hammering the XC-VM API for channels that are already in sync.
    private const SYNC_COOLDOWN_SECONDS = 60;

    public function __construct(
        private readonly XcVmClient $client,
    ) {}

    /**
     * Scan all active UDP/RTP channels and push their HLS URLs to XC-VM
     * when the ingest has produced a fresh playlist.
     *
     * Returns the number of channels updated.
     */
    public function syncAll(): int
    {
        if (! $this->client->isConfigured()) {
            return 0;
        }

        $channels = Channel::where('is_active', true)
            ->where(function ($q) {
                $q->where('stream_url', 'like', 'udp://%')
                  ->orWhere('stream_url', 'like', 'rtp://%');
            })
            ->get();

        $updated = 0;

        foreach ($channels as $channel) {
            if ($this->syncChannel($channel)) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Push the HLS URL for a single UDP channel to XC-VM.
     * Returns true when XC-VM was actually updated.
     */
    public function syncChannel(Channel $channel): bool
    {
        $hlsPlaylist = storage_path("app/streams/hls/{$channel->id}/playlist.m3u8");

        // Only push when the ingest has produced a playlist recently.
        if (! is_file($hlsPlaylist)) {
            return false;
        }

        $age = time() - (int) @filemtime($hlsPlaylist);

        // Playlist is stale — ingest is down, don't update XC-VM with a dead URL.
        if ($age > 120) {
            return false;
        }

        $cacheKey = "xcvm:udp_bridge:{$channel->id}";

        // Cooldown: skip if we pushed this channel recently and the playlist
        // hasn't changed since the last push.
        if (Cache::has($cacheKey)) {
            return false;
        }

        $remoteId = XcVmMapping::lookup('channel', (int) $channel->id);

        if ($remoteId === null) {
            // Channel not yet in XC-VM — run a full channel sync to create it.
            try {
                $syncer = new ChannelSyncer($this->client);
                $result = $syncer->syncOne($channel);

                if ($result->action === 'failed') {
                    Log::warning('UdpXcVmBridge: channel sync failed', [
                        'channel_id' => $channel->id,
                        'error'      => $result->error,
                    ]);
                    return false;
                }

                $remoteId = $result->xcVmId;
            } catch (\Throwable $e) {
                Log::warning('UdpXcVmBridge: channel sync exception', [
                    'channel_id' => $channel->id,
                    'error'      => $e->getMessage(),
                ]);
                return false;
            }
        }

        if (! $remoteId) {
            return false;
        }

        $hlsUrl = $this->hlsUrl((int) $channel->id);

        try {
            $this->client->editStream($remoteId, ['stream_source' => $hlsUrl]);
            $this->client->startStream($remoteId);

            Cache::put($cacheKey, true, self::SYNC_COOLDOWN_SECONDS);

            Log::info('UdpXcVmBridge: pushed HLS URL to XC-VM', [
                'channel_id' => $channel->id,
                'xc_vm_id'   => $remoteId,
                'hls_url'    => $hlsUrl,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('UdpXcVmBridge: failed to update XC-VM stream source', [
                'channel_id' => $channel->id,
                'xc_vm_id'   => $remoteId,
                'error'      => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Invalidate the cooldown cache for a channel so the next watchdog tick
     * re-pushes its HLS URL. Called when the ingest restarts.
     */
    public function invalidate(int $channelId): void
    {
        Cache::forget("xcvm:udp_bridge:{$channelId}");
    }

    private function hlsUrl(int $channelId): string
    {
        // Use 127.0.0.1 so XC-VM fetches over loopback — never leaves the server.
        $port = (int) config('stream_server_port', config('xcvm.proxy_port', 25460));
        return "http://127.0.0.1:{$port}/hls/{$channelId}/playlist.m3u8";
    }
}
