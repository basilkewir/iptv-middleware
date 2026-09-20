<?php

declare(strict_types=1);

namespace App\Services\XcVm;

use App\Models\AdminChannel\AdminChannel;
use App\Models\Channel;
use App\Models\XcVmMapping;
use App\Services\XcVm\Syncers\AdminChannelSyncer;
use App\Services\XcVm\Syncers\ChannelSyncer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Stream Source → XC-VM Bridge
 *
 * Ensures every active channel's current source URL is registered in XC-VM
 * so the streaming engine always knows where to pull from. Handles:
 *
 *   - ALL active channels (UDP/RTP multicast, HTTP/HLS, RTMP, YouTube, etc.)
 *   - Live AdminChannels (My Channels) — their loopback HLS output
 *   - Source URL changes (backup failover, YouTube refresh)
 *   - Ingest restarts (push fresh URL immediately)
 *
 * Scheduled every minute by the kernel. The bridge is idempotent — running
 * it multiple times per minute is safe and cheap (cooldown prevents
 * redundant API calls).
 */
class XcVmStreamBridge
{
    private const SYNC_COOLDOWN_SECONDS = 10;

    private const MAX_RETRIES = 2;

    public function __construct(
        private readonly XcVmClient $client,
    ) {}

    /**
     * Scan all active channels and live AdminChannels,
     * pushing their source URLs to XC-VM when the ingest is fresh.
     *
     * Returns the number of channels updated.
     */
    public function syncAll(): int
    {
        if (! $this->client->isConfigured()) {
            return 0;
        }

        $updated = 0;

        // Sync ALL active channels (UDP, HTTP, HLS, RTMP, YouTube, etc.)
        $channels = Channel::where('is_active', true)->get();

        foreach ($channels as $channel) {
            if ($this->syncChannel($channel)) {
                $updated++;
            }
        }

        // Sync live AdminChannels (My Channels) to XC-VM
        $liveAdminChannels = AdminChannel::where('is_active', true)
            ->where('broadcast_status', 'live')
            ->where('is_approved', true)
            ->get();

        foreach ($liveAdminChannels as $adminChannel) {
            if ($this->syncAdminChannel($adminChannel)) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Push the source URL for a single channel to XC-VM.
     * Returns true when XC-VM was actually updated.
     */
    public function syncChannel(Channel $channel): bool
    {
        $sourceUrl = $channel->active_stream_url ?? $channel->stream_url;

        if (empty($sourceUrl)) {
            return false;
        }

        // For UDP/RTP multicast channels, check that the local HLS ingest
        // has produced a playlist before pushing to XC-VM.
        $isMulticast = str_starts_with($sourceUrl, 'udp://') || str_starts_with($sourceUrl, 'rtp://');

        if ($isMulticast) {
            $hlsPlaylist = storage_path("app/streams/hls/{$channel->id}/playlist.m3u8");

            if (! is_file($hlsPlaylist)) {
                return false;
            }

            $age = time() - (int) @filemtime($hlsPlaylist);
            $maxAge = (int) config('xcvm.max_playlist_age', 120);

            if ($age > $maxAge) {
                return false;
            }
        }

        $cacheKey = "xcvm:stream_bridge:{$channel->id}";

        // Cooldown: skip if we pushed this channel recently.
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
                    Log::warning('XcVmStreamBridge: channel sync failed', [
                        'channel_id' => $channel->id,
                        'error'      => $result->error,
                    ]);
                    return false;
                }

                $remoteId = $result->xcVmId;
            } catch (\Throwable $e) {
                Log::warning('XcVmStreamBridge: channel sync exception', [
                    'channel_id' => $channel->id,
                    'error'      => $e->getMessage(),
                ]);
                return false;
            }
        }

        if (! $remoteId) {
            return false;
        }

        // Resolve the source URL XC-VM should use.
        $xcVmSourceUrl = $this->resolveXcVmSourceUrl($channel, $sourceUrl);

        return $this->pushToXcVm($remoteId, $xcVmSourceUrl, 'channel', (int) $channel->id);
    }

    /**
     * Push a live AdminChannel's HLS URL to XC-VM.
     */
    public function syncAdminChannel(AdminChannel $channel): bool
    {
        $slug = $channel->channel_slug ?? "admin-channel-{$channel->id}";
        $hlsPlaylist = storage_path("app/streams/hls/{$slug}/index.m3u8");

        if (! is_file($hlsPlaylist)) {
            return false;
        }

        $age = time() - (int) @filemtime($hlsPlaylist);
        $maxAge = (int) config('xcvm.max_playlist_age', 120);

        if ($age > $maxAge) {
            return false;
        }

        $cacheKey = "xcvm:stream_bridge:admin_channel:{$channel->id}";
        if (Cache::has($cacheKey)) {
            return false;
        }

        $remoteId = XcVmMapping::lookup('admin_channel', (int) $channel->id);

        if ($remoteId === null) {
            try {
                $syncer = new AdminChannelSyncer($this->client);
                $result = $syncer->syncOne($channel);

                if ($result->action === 'failed') {
                    Log::warning('XcVmStreamBridge: admin_channel sync failed', [
                        'admin_channel_id' => $channel->id,
                        'error'            => $result->error,
                    ]);
                    return false;
                }

                $remoteId = $result->xcVmId;
            } catch (\Throwable $e) {
                Log::warning('XcVmStreamBridge: admin_channel sync exception', [
                    'admin_channel_id' => $channel->id,
                    'error'            => $e->getMessage(),
                ]);
                return false;
            }
        }

        if (! $remoteId) {
            return false;
        }

        $port = (int) config('stream_server_port', config('xcvm.proxy_port', 25460));
        $hlsUrl = "http://127.0.0.1:{$port}/hls/{$slug}/index.m3u8";

        return $this->pushToXcVm($remoteId, $hlsUrl, 'admin_channel', (int) $channel->id);
    }

    /**
     * Resolve the source URL that XC-VM should use for this channel.
     *
     * UDP/RTP multicast channels are ingested by the middleware's own FFmpeg
     * and exposed as loopback HLS — XC-VM pulls from there.
     * All other source types are passed through unchanged.
     */
    private function resolveXcVmSourceUrl(Channel $channel, string $sourceUrl): string
    {
        $isMulticast = str_starts_with($sourceUrl, 'udp://') || str_starts_with($sourceUrl, 'rtp://');

        if ($isMulticast) {
            $port = (int) config('stream_server_port', config('xcvm.proxy_port', 25460));
            return "http://127.0.0.1:{$port}/hls/{$channel->id}/playlist.m3u8";
        }

        return $sourceUrl;
    }

    /**
     * Push a stream source URL to XC-VM with retry logic.
     */
    private function pushToXcVm(int $remoteId, string $sourceUrl, string $type, int $localId): bool
    {
        for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $this->client->editStream($remoteId, ['stream_source' => $sourceUrl]);
                $this->client->startStream($remoteId);

                $cacheKey = $type === 'admin_channel'
                    ? "xcvm:stream_bridge:admin_channel:{$localId}"
                    : "xcvm:stream_bridge:{$localId}";
                Cache::put($cacheKey, true, self::SYNC_COOLDOWN_SECONDS);

                Log::info('XcVmStreamBridge: pushed source URL to XC-VM', [
                    'type'       => $type,
                    'local_id'   => $localId,
                    'xc_vm_id'   => $remoteId,
                    'source_url' => $sourceUrl,
                ]);

                return true;
            } catch (\Throwable $e) {
                if ($attempt === self::MAX_RETRIES) {
                    Log::warning('XcVmStreamBridge: failed to update XC-VM stream source after retries', [
                        'type'     => $type,
                        'local_id' => $localId,
                        'xc_vm_id' => $remoteId,
                        'error'    => $e->getMessage(),
                    ]);
                    return false;
                }
                usleep(500000 * ($attempt + 1));
            }
        }

        return false;
    }

    /**
     * Invalidate the cooldown cache for a channel so the next bridge tick
     * re-pushes its source URL. Called when an ingest restarts or a
     * channel's source URL changes.
     */
    public function invalidate(int $channelId, string $type = 'channel'): void
    {
        if ($type === 'admin_channel') {
            Cache::forget("xcvm:stream_bridge:admin_channel:{$channelId}");
        } else {
            Cache::forget("xcvm:stream_bridge:{$channelId}");
        }
    }
}
