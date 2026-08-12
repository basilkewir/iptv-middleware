<?php

declare(strict_types=1);

namespace App\Services\StreamingService;

use App\Models\Stream;
use App\Models\Channel;
use App\Enums\Stream\StreamStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StreamAnalyzer
{
    private StreamManager $streamManager;

    private const ANALYSIS_CACHE_TTL = 300;

    public function __construct(StreamManager $streamManager)
    {
        $this->streamManager = $streamManager;
    }

    public function getStreamAnalytics(Stream $stream, int $days = 30): array
    {
        $cacheKey = "analytics:stream:{$stream->id}:{$days}";

        return Cache::remember($cacheKey, self::ANALYSIS_CACHE_TTL, function () use ($stream, $days) {
            $startDate = now()->subDays($days);

            return [
                'stream_id' => $stream->id,
                'period_days' => $days,
                'total_viewers' => $this->getTotalViewers($stream, $startDate),
                'peak_viewers' => $this->getPeakViewers($stream, $startDate),
                'avg_viewers' => $this->getAvgViewers($stream, $startDate),
                'total_watch_time' => $this->getTotalWatchTime($stream, $startDate),
                'avg_watch_duration' => $this->getAvgWatchDuration($stream, $startDate),
                'viewer_retention' => $this->getViewerRetention($stream, $startDate),
                'peak_hours' => $this->getPeakHours($stream, $startDate),
                'geographic_distribution' => $this->getGeographicDistribution($stream, $startDate),
                'device_distribution' => $this->getDeviceDistribution($stream, $startDate),
            ];
        });
    }

    public function getChannelAnalytics(Channel $channel, int $days = 30): array
    {
        $cacheKey = "analytics:channel:{$channel->id}:{$days}";

        return Cache::remember($cacheKey, self::ANALYSIS_CACHE_TTL, function () use ($channel, $days) {
            $startDate = now()->subDays($days);
            $streams = Stream::where('channel_id', $channel->id)
                ->where('started_at', '>=', $startDate)
                ->get();

            $totalViewers = 0;
            $peakViewers = 0;
            $totalWatchTime = 0;

            foreach ($streams as $stream) {
                $totalViewers += $stream->current_viewers;
                $peakViewers = max($peakViewers, $stream->current_viewers);
                $totalWatchTime += $stream->total_watch_time ?? 0;
            }

            return [
                'channel_id' => $channel->id,
                'period_days' => $days,
                'total_streams' => $streams->count(),
                'total_viewers' => $totalViewers,
                'peak_viewers' => $peakViewers,
                'avg_viewers_per_stream' => $streams->count() > 0
                    ? round($totalViewers / $streams->count(), 2)
                    : 0,
                'total_watch_time' => $totalWatchTime,
                'popular_streams' => $this->getPopularStreams($channel, $startDate),
            ];
        });
    }

    public function getPlatformAnalytics(int $days = 30): array
    {
        $cacheKey = "analytics:platform:{$days}";

        return Cache::remember($cacheKey, self::ANALYSIS_CACHE_TTL, function () use ($days) {
            $startDate = now()->subDays($days);

            return [
                'period_days' => $days,
                'total_streams' => Stream::where('started_at', '>=', $startDate)->count(),
                'active_streams' => Stream::where('status', StreamStatus::ACTIVE)->count(),
                'total_viewers' => Stream::where('status', StreamStatus::ACTIVE)
                    ->sum('current_viewers'),
                'peak_concurrent' => $this->getPeakConcurrent($startDate),
                'avg_stream_duration' => $this->getAvgStreamDuration($startDate),
                'top_channels' => $this->getTopChannels($startDate),
                'stream_status_distribution' => $this->getStreamStatusDistribution($startDate),
                'hourly_distribution' => $this->getHourlyDistribution($startDate),
            ];
        });
    }

    public function getQualityMetrics(Stream $stream, int $hours = 24): array
    {
        $cacheKey = "quality:stream:{$stream->id}:{$hours}";

        return Cache::remember($cacheKey, 60, function () use ($stream, $hours) {
            $startDate = now()->subHours($hours);

            return [
                'stream_id' => $stream->id,
                'period_hours' => $hours,
                'avg_bitrate' => $stream->avg_bitrate ?? 0,
                'avg_fps' => $stream->avg_fps ?? 0,
                'avg_latency' => $stream->avg_latency ?? 0,
                'buffer_ratio' => $this->getBufferRatio($stream, $startDate),
                'error_rate' => $this->getErrorRate($stream, $startDate),
                'quality_score' => $this->calculateQualityScore($stream, $startDate),
            ];
        });
    }

    public function generateReport(int $days = 7): array
    {
        $cacheKey = "report:platform:{$days}";

        return Cache::remember($cacheKey, self::ANALYSIS_CACHE_TTL, function () use ($days) {
            $startDate = now()->subDays($days);

            return [
                'report_type' => 'platform_summary',
                'period_days' => $days,
                'generated_at' => now()->toISOString(),
                'summary' => $this->getPlatformAnalytics($days),
                'trends' => $this->getTrends($startDate),
                'recommendations' => $this->generateRecommendations($days),
            ];
        });
    }

    private function getTotalViewers(Stream $stream, Carbon $startDate): int
    {
        return $stream->connection_logs()
            ->where('created_at', '>=', $startDate)
            ->distinct('user_id')
            ->count('user_id');
    }

    private function getPeakViewers(Stream $stream, Carbon $startDate): int
    {
        return $stream->connection_logs()
            ->where('created_at', '>=', $startDate)
            ->max('connections') ?? 0;
    }

    private function getAvgViewers(Stream $stream, Carbon $startDate): float
    {
        return $stream->connection_logs()
            ->where('created_at', '>=', $startDate)
            ->avg('connections') ?? 0;
    }

    private function getTotalWatchTime(Stream $stream, Carbon $startDate): int
    {
        return $stream->connection_logs()
            ->where('created_at', '>=', $startDate)
            ->sum('watch_duration') ?? 0;
    }

    private function getAvgWatchDuration(Stream $stream, Carbon $startDate): float
    {
        $totalDuration = $this->getTotalWatchTime($stream, $startDate);
        $totalViewers = $this->getTotalViewers($stream, $startDate);

        return $totalViewers > 0 ? round($totalDuration / $totalViewers, 2) : 0;
    }

    private function getViewerRetention(Stream $stream, Carbon $startDate): array
    {
        $totalViewers = $this->getTotalViewers($stream, $startDate);

        if ($totalViewers === 0) {
            return [];
        }

        $retentionPoints = [5, 10, 15, 20, 30, 45, 60];
        $retention = [];

        foreach ($retentionPoints as $minutes) {
            $retained = $stream->connection_logs()
                ->where('created_at', '>=', $startDate)
                ->where('watch_duration', '>=', $minutes * 60)
                ->distinct('user_id')
                ->count('user_id');

            $retention["{$minutes}_minutes"] = round(($retained / $totalViewers) * 100, 2);
        }

        return $retention;
    }

    private function getPeakHours(Stream $stream, Carbon $startDate): array
    {
        return $stream->connection_logs()
            ->where('created_at', '>=', $startDate)
            ->selectRaw('HOUR(created_at) as hour, COUNT(DISTINCT user_id) as viewers')
            ->groupBy('hour')
            ->orderByDesc('viewers')
            ->limit(5)
            ->get()
            ->pluck('viewers', 'hour')
            ->toArray();
    }

    private function getGeographicDistribution(Stream $stream, Carbon $startDate): array
    {
        return $stream->connection_logs()
            ->where('created_at', '>=', $startDate)
            ->selectRaw('country, COUNT(DISTINCT user_id) as viewers')
            ->groupBy('country')
            ->orderByDesc('viewers')
            ->get()
            ->pluck('viewers', 'country')
            ->toArray();
    }

    private function getDeviceDistribution(Stream $stream, Carbon $startDate): array
    {
        return $stream->connection_logs()
            ->where('created_at', '>=', $startDate)
            ->selectRaw('device_type, COUNT(DISTINCT user_id) as viewers')
            ->groupBy('device_type')
            ->orderByDesc('viewers')
            ->get()
            ->pluck('viewers', 'device_type')
            ->toArray();
    }

    private function getPopularStreams(Channel $channel, Carbon $startDate): array
    {
        return Stream::where('channel_id', $channel->id)
            ->where('started_at', '>=', $startDate)
            ->orderByDesc('current_viewers')
            ->limit(5)
            ->get()
            ->toArray();
    }

    private function getPeakConcurrent(Carbon $startDate): int
    {
        return Stream::where('started_at', '>=', $startDate)
            ->max('current_viewers') ?? 0;
    }

    private function getAvgStreamDuration(Carbon $startDate): float
    {
        return Stream::where('started_at', '>=', $startDate)
            ->whereNotNull('stopped_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, stopped_at)) as avg_duration')
            ->value('avg_duration') ?? 0;
    }

    private function getTopChannels(Carbon $startDate): array
    {
        return Stream::where('started_at', '>=', $startDate)
            ->selectRaw('channel_id, SUM(current_viewers) as total_viewers')
            ->groupBy('channel_id')
            ->orderByDesc('total_viewers')
            ->limit(10)
            ->get()
            ->toArray();
    }

    private function getStreamStatusDistribution(Carbon $startDate): array
    {
        return Stream::where('started_at', '>=', $startDate)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();
    }

    private function getHourlyDistribution(Carbon $startDate): array
    {
        return Stream::where('started_at', '>=', $startDate)
            ->selectRaw('HOUR(started_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->pluck('count', 'hour')
            ->toArray();
    }

    private function getBufferRatio(Stream $stream, Carbon $startDate): float
    {
        $totalEvents = $stream->error_logs()
            ->where('created_at', '>=', $startDate)
            ->count();

        if ($totalEvents === 0) {
            return 0;
        }

        $bufferEvents = $stream->error_logs()
            ->where('type', 'buffer_underrun')
            ->where('created_at', '>=', $startDate)
            ->count();

        return round(($bufferEvents / $totalEvents) * 100, 2);
    }

    private function getErrorRate(Stream $stream, Carbon $startDate): float
    {
        $totalConnections = $stream->connection_logs()
            ->where('created_at', '>=', $startDate)
            ->count();

        if ($totalConnections === 0) {
            return 0;
        }

        $errors = $stream->error_logs()
            ->where('created_at', '>=', $startDate)
            ->count();

        return round(($errors / $totalConnections) * 100, 2);
    }

    private function calculateQualityScore(Stream $stream, Carbon $startDate): float
    {
        $score = 100;

        $bufferRatio = $this->getBufferRatio($stream, $startDate);
        $errorRate = $this->getErrorRate($stream, $startDate);

        $score -= $bufferRatio * 2;
        $score -= $errorRate * 3;

        $avgBitrate = $stream->avg_bitrate ?? 0;
        if ($avgBitrate < 1000) {
            $score -= 10;
        } elseif ($avgBitrate < 3000) {
            $score -= 5;
        }

        return max(0, min(100, $score));
    }

    private function getTrends(Carbon $startDate): array
    {
        $dailyStats = Stream::where('started_at', '>=', $startDate)
            ->selectRaw('DATE(started_at) as date, COUNT(*) as streams, SUM(current_viewers) as viewers')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'daily_streams' => $dailyStats->pluck('streams', 'date')->toArray(),
            'daily_viewers' => $dailyStats->pluck('viewers', 'date')->toArray(),
        ];
    }

    private function generateRecommendations(int $days): array
    {
        $recommendations = [];

        $avgViewers = Stream::where('started_at', '>=', now()->subDays($days))
            ->avg('current_viewers');

        if ($avgViewers < 10) {
            $recommendations[] = [
                'type' => 'engagement',
                'message' => 'Average viewer count is low. Consider improving content or marketing.',
                'priority' => 'medium',
            ];
        }

        $errorRate = Stream::where('started_at', '>=', now()->subDays($days))
            ->where('status', StreamStatus::FAILED)
            ->count();

        $totalStreams = Stream::where('started_at', '>=', now()->subDays($days))->count();

        if ($totalStreams > 0 && ($errorRate / $totalStreams) > 0.1) {
            $recommendations[] = [
                'type' => 'reliability',
                'message' => 'Stream failure rate is above 10%. Check server health and network stability.',
                'priority' => 'high',
            ];
        }

        return $recommendations;
    }
}
