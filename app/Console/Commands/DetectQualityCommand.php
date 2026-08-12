<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\QualityDetectionService;
use Illuminate\Console\Command;

class DetectQualityCommand extends Command
{
    protected $signature = 'quality:detect
                            {--type=all : Type to detect (all, channels, vod)}
                            {--force : Force re-detection even if quality exists}';

    protected $description = 'Detect quality for channels and VOD content';

    public function __construct(
        protected QualityDetectionService $qualityDetectionService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $type = $this->option('type');
        $force = $this->option('force');

        $this->info('Starting quality detection...');
        $this->newLine();

        if ($type === 'all' || $type === 'channels') {
            $this->detectChannels($force);
        }

        if ($type === 'all' || $type === 'vod') {
            $this->detectVOD($force);
        }

        $this->newLine();
        $this->info('Quality detection completed.');

        return Command::SUCCESS;
    }

    protected function detectChannels(bool $force): void
    {
        $this->info('Detecting channel quality...');

        $channels = \App\Models\Channel::where('is_active', true)->get();

        if ($channels->isEmpty()) {
            $this->warn('No active channels found.');
            return;
        }

        $channels = $force
            ? $channels
            : $channels->filter(fn ($ch) => !$ch->quality_level);

        if ($channels->isEmpty()) {
            $this->warn('All channels already have quality detected. Use --force to re-detect.');
            return;
        }

        $bar = $this->output->createProgressBar($channels->count());
        $bar->start();

        $success = 0;
        $failed = 0;

        foreach ($channels as $channel) {
            $result = $this->qualityDetectionService->detectChannelQuality($channel);

            if ($result['success']) {
                $success++;
            } else {
                $failed++;
            }

            $bar->advance();
            usleep(500000);
        }

        $bar->finish();
        $this->newLine();
        $this->info("Channels: {$success} succeeded, {$failed} failed out of {$channels->count()} total.");
    }

    protected function detectVOD(bool $force): void
    {
        $this->info('Detecting VOD quality...');

        $mediaItems = \App\Models\VODMedia::where('is_available', true)->get();

        if ($mediaItems->isEmpty()) {
            $this->warn('No available VOD media found.');
            return;
        }

        $mediaItems = $force
            ? $mediaItems
            : $mediaItems->filter(fn ($media) => !$media->quality);

        if ($mediaItems->isEmpty()) {
            $this->warn('All VOD media already have quality detected. Use --force to re-detect.');
            return;
        }

        $bar = $this->output->createProgressBar($mediaItems->count());
        $bar->start();

        $success = 0;
        $failed = 0;

        foreach ($mediaItems as $media) {
            $result = $this->qualityDetectionService->detectVODQuality($media);

            if ($result['success']) {
                $success++;
            } else {
                $failed++;
            }

            $bar->advance();
            usleep(500000);
        }

        $bar->finish();
        $this->newLine();
        $this->info("VOD: {$success} succeeded, {$failed} failed out of {$mediaItems->count()} total.");
    }
}
