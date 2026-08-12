<?php

namespace App\Jobs;

use App\Models\Channel;
use App\Models\EPGEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
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

    public function handle(): void
    {
        try {
            $response = Http::timeout(120)->get($this->epgUrl);

            if ($response->failed()) {
                Log::error('Failed to fetch EPG data', ['url' => $this->epgUrl]);
                return;
            }

            $xml = simplexml_load_string($response->body());

            if ($xml === false) {
                Log::error('Invalid EPG XML', ['url' => $this->epgUrl]);
                return;
            }

            foreach ($xml->programme as $programme) {
                $channel = $this->resolveChannel((string) $programme['channel']);

                if (! $channel) {
                    continue;
                }

                EPGEntry::updateOrCreate(
                    [
                        'channel_id' => $channel->id,
                        'external_id' => (string) $programme['id'],
                    ],
                    [
                        'title' => (string) $programme->title,
                        'description' => (string) $programme->desc ?? '',
                        'start_time' => $this->parseEpgTime((string) $programme['start']),
                        'end_time' => $this->parseEpgTime((string) $programme['stop']),
                        'language' => (string) $programme->title['lang'] ?? 'en',
                        'category' => (string) $programme->category ?? null,
                        'icon' => (string) $programme->icon['src'] ?? null,
                    ]
                );
            }

            Log::info('EPG processed successfully', ['url' => $this->epgUrl]);

        } catch (\Exception $e) {
            Log::error('EPG processing failed', [
                'url' => $this->epgUrl,
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

        return Channel::where('epg_id', $epgChannelId)->first();
    }

    private function parseEpgTime(string $time): \Carbon\Carbon
    {
        $formatted = substr($time, 0, 4) . '-' . substr($time, 4, 2) . '-' . substr($time, 6, 2) . ' ' . substr($time, 8, 2) . ':' . substr($time, 10, 2) . ':' . substr($time, 12, 2);

        return \Carbon\Carbon::parse($formatted);
    }
}
