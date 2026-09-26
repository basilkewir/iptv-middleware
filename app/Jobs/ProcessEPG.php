<?php

namespace App\Jobs;

use App\Models\Channel;
use App\Models\EPGProgram;
use App\Services\EPGService\EPGManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessEPG implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 3;

    public function __construct(
        public string $epgUrl,
        public ?int $channelId = null
    ) {}

    public function handle(EPGManager $epgManager): void
    {
        try {
            $result = $epgManager->fetchEPG($this->epgUrl);
            $programs = $result['programs'] ?? [];
            $count = 0;

            foreach ($programs as $program) {
                $epgChannelId = $program['epg_channel_id'] ?? null;

                if (!$epgChannelId) {
                    continue;
                }

                $channel = $this->resolveChannel($epgChannelId);

                if (!$channel) {
                    continue;
                }

                EPGProgram::updateOrCreate(
                    [
                        'program_id'    => $program['external_id'],
                        'channel_id'    => $channel->id,
                    ],
                    [
                        'title'         => $program['title'],
                        'description'   => $program['description'] ?? '',
                        'start_time'    => $program['start_time'],
                        'end_time'      => $program['end_time'],
                        'category'      => $program['genre'] ?? null,
                        'language'      => $program['language'] ?? null,
                        'rating'        => $program['rating'] ?? null,
                        'season'        => $program['season'] ?? null,
                        'episode'       => $program['episode'] ?? null,
                        'episode_title' => $program['episode_title'] ?? null,
                    ]
                );

                $count++;
            }

            Log::info('EPG processed successfully', [
                'url'         => $this->epgUrl,
                'programs'    => $count,
                'total_parse' => count($programs),
            ]);

        } catch (\Exception $e) {
            Log::error('EPG processing failed', [
                'url'   => $this->epgUrl,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function resolveChannel(string $epgChannelId): ?Channel
    {
        if ($this->channelId) {
            return Channel::find($this->channelId);
        }

        return Channel::where('epg_channel_id', $epgChannelId)
            ->orWhere('epg_id', $epgChannelId)
            ->first();
    }
}
