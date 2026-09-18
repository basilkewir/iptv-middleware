<?php

namespace App\Services\XcVm\Syncers;

use App\Models\Channel;
use App\Models\XcVmMapping;
use App\Services\XcVm\SyncReport;
use App\Services\XcVm\SyncResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

class ChannelSyncer extends AbstractSyncer
{
    public function entityType(): string
    {
        return 'channel';
    }

    public function syncOne(int|Model $entity): SyncResult
    {
        $channel = $entity instanceof Channel ? $entity : Channel::find((int) $entity);
        if (! $channel) {
            return $this->skipped((int) $entity, 'channel not found');
        }

        $label = (string) $channel->name;

        try {
            // For UDP/RTP multicast sources the middleware reads the stream
            // via its own FFmpeg ingest and exposes it as a local HLS URL.
            // XC-VM cannot join a multicast group directly, so we give it the
            // loopback HLS URL instead of the raw udp:// address.
            $streamSource = $this->resolveStreamSource($channel);

            $payload = [
                'stream_display_name' => $label,
                'stream_source'       => $streamSource,
                'stream_icon'         => (string) ($channel->logo_url ?? ''),
                'tv_archive'          => 0,
                'direct_source'       => 0,
                'notes'               => mb_substr((string) $channel->description, 0, 255),
            ];

            // Attach the XC-VM category id when the channel belongs to a
            // mapped, active, live category.
            $category = $channel->categories()->first();
            if ($category && $category->is_active && $category->category_type === 'live') {
                $remoteCategoryId = $this->categoryRemoteId((int) $category->id);
                if ($remoteCategoryId !== null) {
                    $payload['category_id'] = $remoteCategoryId;
                }
            }

            $remoteId = $this->remoteId((int) $channel->id);

            if ($remoteId !== null) {
                $this->client->editStream($remoteId, $payload);
                $action = SyncResult::UPDATED;
            } else {
                $remoteId = $this->client->createStream($payload);
                $action = SyncResult::CREATED;

                if (! $remoteId) {
                    return $this->failure((int) $channel->id, 'XC-VM returned no stream id', $label);
                }
            }

            // Toggle remote stream state so enabled channels are ingested.
            if ($channel->is_active) {
                if (config('xcvm.start_streams_on_sync', true)) {
                    $this->client->startStream($remoteId);
                }
            } else {
                $this->client->stopStream($remoteId);
            }

            $this->remember((int) $channel->id, $remoteId, [
                'stream_source' => $streamSource,
            ]);

            return new SyncResult($this->entityType(), (int) $channel->id, $action, $remoteId, $label);
        } catch (Throwable $e) {
            return $this->failure((int) $channel->id, $e->getMessage(), $label);
        }
    }

    /**
     * Resolve the stream source URL to push to XC-VM.
     *
     * UDP/RTP multicast channels are read by the middleware's own FFmpeg ingest
     * and fanned out as per-channel HLS playlists under storage/app/streams/hls/.
     * XC-VM cannot join a multicast group directly, so we give it the loopback
     * HLS URL (http://127.0.0.1:{MW_PORT}/hls/{id}/playlist.m3u8) instead of
     * the raw udp:// address. The middleware's Nginx serves those segments
     * directly from disk with no PHP overhead.
     *
     * All other source types (HTTP, HLS, RTMP, YouTube-resolved) are passed
     * through unchanged — XC-VM handles them natively.
     */
    private function resolveStreamSource(Channel $channel): string
    {
        $raw = (string) ($channel->getSourceUrlAttribute() ?? '');

        if (str_starts_with($raw, 'udp://') || str_starts_with($raw, 'rtp://')) {
            // Build the loopback HLS URL that the middleware's Nginx serves.
            // XC-VM fetches this over 127.0.0.1 so it never leaves the server.
            $base = rtrim((string) config('app.url'), '/');
            return "{$base}/hls/{$channel->id}/playlist.m3u8";
        }

        return $raw;
    }

    public function delete(int $entityId): SyncResult
    {
        $mapping = XcVmMapping::where('entity_type', $this->entityType())
            ->where('entity_id', $entityId)
            ->first();

        if (! $mapping) {
            return $this->skipped($entityId, 'no remote mapping');
        }

        try {
            $this->client->stopStream((int) $mapping->xc_vm_id);
            $this->client->deleteStream((int) $mapping->xc_vm_id);
            $this->forget($entityId);

            return $this->deleted($entityId);
        } catch (Throwable $e) {
            return $this->failure($entityId, $e->getMessage());
        }
    }

    public function queue(): Collection
    {
        return Channel::query()
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
    }

    public function syncAll(?callable $progress = null): SyncReport
    {
        $results = $this->runQueue($progress, config('xcvm.prune_remote', false));

        return $this->report($results);
    }

    private function categoryRemoteId(int $categoryId): ?int
    {
        return XcVmMapping::lookup('category', $categoryId);
    }
}