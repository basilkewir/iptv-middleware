<?php

declare(strict_types=1);

namespace App\Services\XcVm\Syncers;

use App\Http\Controllers\XtreamController;
use App\Models\AdminChannel\AdminChannel;
use App\Models\XcVmMapping;
use App\Services\XcVm\SyncReport;
use App\Services\XcVm\SyncResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sync "My Channels" (AdminChannels) to XC-VM as live streams.
 *
 * Each admin channel gets a stream_id of (admin_channel.id + ADMIN_CHANNEL_OFFSET)
 * so it coexists with regular channel IDs without collisions.
 *
 * Only channels that are actively broadcasting are started on XC-VM;
 * others are created/stopped so they appear in the panel but don't ingest.
 */
class AdminChannelSyncer extends AbstractSyncer
{
    public function entityType(): string
    {
        return 'admin_channel';
    }

    public function syncOne(int|Model $entity): SyncResult
    {
        $channel = $entity instanceof AdminChannel ? $entity : AdminChannel::find((int) $entity);
        if (! $channel) {
            return $this->skipped((int) $entity, 'admin_channel not found');
        }

        $label = (string) $channel->channel_name;

        try {
            $streamSource = $this->resolveStreamSource($channel);

            $payload = [
                'stream_display_name' => $label,
                'stream_source'       => $streamSource,
                'stream_icon'         => (string) ($channel->logo_url ?? ''),
                'tv_archive'          => 0,
                'direct_source'       => 0,
                'notes'               => mb_substr((string) $channel->description, 0, 255),
            ];

            // XC-VM stream id uses the offset so it doesn't collide with regular channels.
            $xcVmStreamId = (int) $channel->id + XtreamController::ADMIN_CHANNEL_OFFSET;

            // Check if we already have a mapping for this admin channel.
            $remoteId = $this->remoteId((int) $channel->id);

            if ($remoteId !== null) {
                // Update existing stream — use the real XC-VM id (not the offset id).
                $this->client->editStream($remoteId, $payload);
                $action = SyncResult::UPDATED;
            } else {
                // Create a new stream in XC-VM with the offset-based id.
                // XC-VM create_stream returns the id it assigned — we store
                // that as the remote id but track the offset id in metadata.
                $remoteId = $this->client->createStream($payload);
                $action = SyncResult::CREATED;

                if (! $remoteId) {
                    return $this->failure((int) $channel->id, 'XC-VM returned no stream id', $label);
                }
            }

            // Only start the stream on XC-VM if the channel is actively broadcasting.
            $isLive = $channel->is_active
                && $channel->broadcast_status === 'live'
                && $channel->is_approved;

            if ($isLive) {
                if (config('xcvm.start_streams_on_sync', true)) {
                    $this->client->startStream($remoteId);
                }
            } else {
                $this->client->stopStream($remoteId);
            }

            $this->remember((int) $channel->id, $remoteId, [
                'stream_source' => $streamSource,
                'xc_vm_stream_id' => $xcVmStreamId,
                'broadcast_status' => $channel->broadcast_status,
            ]);

            return new SyncResult($this->entityType(), (int) $channel->id, $action, $remoteId, $label);
        } catch (Throwable $e) {
            return $this->failure((int) $channel->id, $e->getMessage(), $label);
        }
    }

    /**
     * Resolve the HLS stream source URL for an admin channel.
     *
     * Active broadcast channels produce HLS via MyChannelHlsService at:
     *   storage/app/streams/hls/admin-channel-{slug}/index.m3u8
     *
     * XC-VM fetches this over loopback via Nginx.
     */
    private function resolveStreamSource(AdminChannel $channel): string
    {
        $port = (int) config('stream_server_port', config('xcvm.proxy_port', 25460));
        $slug = $channel->channel_slug ?? "admin-channel-{$channel->id}";

        return "http://127.0.0.1:{$port}/hls/{$slug}/index.m3u8";
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
        return AdminChannel::query()
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
    }

    public function syncAll(?callable $progress = null): SyncReport
    {
        $results = $this->runQueue($progress, config('xcvm.prune_remote', false));

        return $this->report($results);
    }
}
