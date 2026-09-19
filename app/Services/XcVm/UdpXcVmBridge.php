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
 * UDP / AdminChannel → XC-VM Bridge
 *
 * The middleware reads UDP/RTP multicast streams via its own FFmpeg ingest
 * and writes per-channel HLS playlists to storage/app/streams/hls/{id}/.
 * XC-VM cannot join a multicast group directly, so this bridge:
 *
 *   1. Detects when a multicast channel's HLS playlist becomes available.
 *   2. Pushes the loopback HLS URL to XC-VM as the stream_source.
 *   3. Calls start_stream on XC-VM so it begins serving the HLS feed.
 *
 * Also syncs live AdminChannels (My Channels) so their HLS output is
 * available through XC-VM player endpoints.
 *
 * Scheduled every minute by the kernel.
 */
class UdpXcVmBridge
{
    private const SYNC_COOLDOWN_SECONDS = 30;
    private const MAX_RETRIES = 2;

    public function __construct(
        private readonly XcVmClient $client,
    ) {}

    /**
     * Scan all active UDP/RTP channels and live AdminChannels,
     * pushing their HLS URLs to XC-VM when the ingest is fresh.
     *
     * Returns the number of channels updated.
     */
    public function syncAll(): int
    {
        if (! $this->client->isConfigured()) {
            return 0;
        }

        $updated = 0;

        // Sync regular UDP/RTP multicast channels
        $channels = Channel::where('is_active', true)
            ->where(function ($q) {
                $q->where('stream_url', 'like', 'udp://%')
                  ->orWhere('stream_url', 'like', 'rtp://%');
            })
            ->get();

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

        return $this->pushToXcVm($remoteId, $hlsUrl, 'channel', (int) $channel->id);
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
        if ($age > 120) {
            return false;
        }

        $cacheKey = "xcvm:udp_bridge:admin_channel:{$channel->id}";
        if (Cache::has($cacheKey)) {
            return false;
        }

        $remoteId = XcVmMapping::lookup('admin_channel', (int) $channel->id);

        if ($remoteId === null) {
            try {
                $syncer = new AdminChannelSyncer($this->client);
                $result = $syncer->syncOne($channel);

                if ($result->action === 'failed') {
                    Log::warning('UdpXcVmBridge: admin_channel sync failed', [
                        'admin_channel_id' => $channel->id,
                        'error'            => $result->error,
                    ]);
                    return false;
                }

                $remoteId = $result->xcVmId;
            } catch (\Throwable $e) {
                Log::warning('UdpXcVmBridge: admin_channel sync exception', [
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
     * Push a stream source URL to XC-VM with retry logic.
     */
    private function pushToXcVm(int $remoteId, string $hlsUrl, string $type, int $localId): bool
    {
        for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $this->client->editStream($remoteId, ['stream_source' => $hlsUrl]);
                $this->client->startStream($remoteId);

                $cacheKey = $type === 'admin_channel'
                    ? "xcvm:udp_bridge:admin_channel:{$localId}"
                    : "xcvm:udp_bridge:{$localId}";
                Cache::put($cacheKey, true, self::SYNC_COOLDOWN_SECONDS);

                Log::info('UdpXcVmBridge: pushed HLS URL to XC-VM', [
                    'type'       => $type,
                    'local_id'   => $localId,
                    'xc_vm_id'   => $remoteId,
                    'hls_url'    => $hlsUrl,
                ]);

                return true;
            } catch (\Throwable $e) {
                if ($attempt === self::MAX_RETRIES) {
                    Log::warning('UdpXcVmBridge: failed to update XC-VM stream source after retries', [
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
     * Invalidate the cooldown cache for a channel so the next watchdog tick
     * re-pushes its HLS URL. Called when the ingest restarts.
     */
    public function invalidate(int $channelId, string $type = 'channel'): void
    {
        Cache::forget("xcvm:udp_bridge:{$type}:{$channelId}");
        // Also invalidate legacy cache key format for channels
        if ($type === 'channel') {
            Cache::forget("xcvm:udp_bridge:{$channelId}");
        }
    }

    private function hlsUrl(int $channelId): string
    {
        // Use 127.0.0.1 so XC-VM fetches over loopback — never leaves the server.
        $port = (int) config('stream_server_port', config('xcvm.proxy_port', 25460));
        return "http://127.0.0.1:{$port}/hls/{$channelId}/playlist.m3u8";
    }
}
