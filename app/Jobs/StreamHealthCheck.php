<?php

namespace App\Jobs;

use App\Models\Channel;
use App\Models\StreamLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StreamHealthCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 2;

    public function __construct(
        public int $channelId
    ) {}

    public function handle(): void
    {
        $channel = Channel::find($this->channelId);

        if (! $channel || ! $channel->is_active) {
            return;
        }

        $start = microtime(true);

        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'IPTV-Middleware/1.0'])
                ->head($channel->stream_url);

            $latency = round((microtime(true) - $start) * 1000, 2);
            $isHealthy = $response->successful();

            StreamLog::create([
                'channel_id' => $channel->id,
                'status' => $isHealthy ? 'healthy' : 'unhealthy',
                'status_code' => $response->status(),
                'latency_ms' => $latency,
                'checked_at' => now(),
            ]);

            if (! $isHealthy) {
                $this->handleUnhealthyStream($channel, $response->status());
            }

        } catch (\Exception $e) {
            StreamLog::create([
                'channel_id' => $channel->id,
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
                'checked_at' => now(),
            ]);

            $this->handleUnhealthyStream($channel, 0);
        }
    }

    private function handleUnhealthyStream(Channel $channel, int $statusCode): void
    {
        $recentFailures = StreamLog::where('channel_id', $channel->id)
            ->where('status', '!=', 'healthy')
            ->where('checked_at', '>=', now()->subMinutes(30))
            ->count();

        if ($recentFailures >= 3) {
            $channel->update(['is_active' => false]);

            Log::warning('Channel deactivated due to repeated failures', [
                'channel_id' => $channel->id,
                'failures' => $recentFailures,
            ]);
        }
    }
}
