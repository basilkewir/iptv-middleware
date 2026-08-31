<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AdminChannel\AdminChannel;
use App\Services\AdminChannel\MyChannelHlsService;
use Illuminate\Console\Command;

class PrepareMyChannelContent extends Command
{
    protected $signature = 'my-channel:prepare {channel? : Channel slug or id — all my-channels when omitted} {--force : Re-transcode even when up to date}';

    protected $description = 'Prepare (normalize) playout content for one or all my-channels';

    public function handle(MyChannelHlsService $hls): int
    {
        $channels = AdminChannel::query();

        if ($target = $this->argument('channel')) {
            $channels = $channels->where(fn ($q) => $q->where('id', $target)->orWhere('channel_slug', $target));
        }

        $channels = $channels->where('is_my_channel', true)->get();

        if ($channels->isEmpty()) {
            $this->error('No my-channels found.');
            return self::FAILURE;
        }

        $errors = 0;

        foreach ($channels as $channel) {
            $this->line("── Preparing {$channel->channel_name} ──");
            $stats = $hls->prepareChannel($channel, (bool) $this->option('force'));

            $this->line("  prepared: {$stats['prepared']}");

            foreach ($stats['failed'] as $f) {
                $this->warn("  FAILED: {$f}");
                $errors++;
            }
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}