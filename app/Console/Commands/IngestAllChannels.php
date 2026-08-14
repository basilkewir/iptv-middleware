<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\XtreamController;
use App\Models\Channel;
use Illuminate\Console\Command;

class IngestAllChannels extends Command
{
    protected $signature = 'channels:ingest-all';

    protected $description = 'Start local HLS ingest for every active channel so all channels are streamed by this middleware to the local network at the same time';

    public function handle(XtreamController $xtream): int
    {
        $channels = Channel::query()
            ->where('is_active', true)
            ->whereNotNull('stream_url')
            ->where('stream_url', '!=', '')
            ->orderBy('channel_number')
            ->get();

        if ($channels->isEmpty()) {
            $this->info('No active channels to ingest.');
            return self::SUCCESS;
        }

        foreach ($channels as $channel) {
            $xtream->ensureHlsStream((int) $channel->id, $channel->stream_url);
            $this->line(sprintf(
                '  - channel %d (#%s) %s',
                $channel->id,
                $channel->channel_number,
                $channel->name ?: '(no name)'
            ));
        }

        $this->info(sprintf('Ensured HLS ingest for %d active channel(s).', $channels->count()));

        return self::SUCCESS;
    }
}
