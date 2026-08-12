<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Channel;
use App\Models\VODMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QualityDetectionService
{
    public array $qualityLevels = [
        '4k' => [
            'min_width' => 3840,
            'min_height' => 2160,
            'min_bitrate' => 20000,
            'badge' => '🟣 4K',
        ],
        'fhd' => [
            'min_width' => 1920,
            'min_height' => 1080,
            'min_bitrate' => 8000,
            'badge' => '🔵 FHD',
        ],
        'hd' => [
            'min_width' => 1280,
            'min_height' => 720,
            'min_bitrate' => 4500,
            'badge' => '🟢 HD',
        ],
        'sd' => [
            'min_width' => 640,
            'min_height' => 480,
            'min_bitrate' => 1000,
            'badge' => '🟡 SD',
        ],
        'low' => [
            'min_width' => 0,
            'min_height' => 0,
            'min_bitrate' => 0,
            'badge' => '🔴 Low',
        ],
    ];

    protected ?object $settings = null;

    protected function getSettings(): object
    {
        if ($this->settings === null) {
            $row = DB::table('quality_detection_settings')->first();

            if (!$row) {
                DB::table('quality_detection_settings')->insert([
                    'detection_method' => 'combined',
                    'auto_scan_enabled' => true,
                    'scan_interval' => 86400,
                    'max_concurrent_scans' => 10,
                    'scan_timeout' => 30,
                    'notify_on_change' => true,
                    'show_badge_channels' => true,
                    'show_badge_epg' => true,
                    'show_badge_player' => true,
                    'show_badge_channel_list' => true,
                'badge_style' => 'modern',
                'auto_update_new' => true,
                'auto_update_existing' => true,
                'update_interval' => 'daily',
                'vod_detection_enabled' => true,
                'detect_file_metadata' => true,
                'detect_stream_analysis' => true,
                'detect_ffprobe' => true,
                'detect_ai_based' => false,
                'detect_new_uploads' => true,
                'detect_existing_files' => true,
                'detect_series' => true,
                'detect_imported' => true,
                'detect_multi_quality' => true,
                'auto_select_best' => true,
                'allow_manual_override' => true,
                'transcode_lower_qualities' => false,
                'show_vod_badge_thumbnail' => true,
                'show_vod_badge_details' => true,
                'show_vod_badge_player' => true,
                'show_vod_quality_options' => true,
                'auto_select_best_device' => true,
                'vod_badge_position' => 'top-right',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->settings = DB::table('quality_detection_settings')->first();
            }
        }

        return $this->settings;
    }

    public function detectChannelQuality(Channel $channel): array
    {
        $log = DB::table('quality_detection_logs')->insertGetId([
            'content_type' => 'channel',
            'content_id' => $channel->id,
            'status' => 'processing',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $streamUrl = $channel->stream_url;

            $analysis = $this->analyzeStream($streamUrl);

            $qualityLevel = $this->determineQualityLevel(
                $analysis['width'] ?? null,
                $analysis['height'] ?? null,
                $analysis['bitrate'] ?? null
            );

            $badge = $this->getQualityBadge($qualityLevel);

            DB::table('channel_quality_cache')->updateOrInsert(
                ['channel_id' => $channel->id],
                [
                    'quality_level' => $qualityLevel,
                    'resolution_width' => $analysis['width'] ?? null,
                    'resolution_height' => $analysis['height'] ?? null,
                    'bitrate' => $analysis['bitrate'] ?? null,
                    'video_codec' => $analysis['video_codec'] ?? null,
                    'audio_codec' => $analysis['audio_codec'] ?? null,
                    'frame_rate' => $analysis['frame_rate'] ?? null,
                    'scan_timestamp' => now(),
                    'is_verified' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $channel->update([
                'quality_level' => $qualityLevel,
                'quality_badge' => $badge,
                'quality_updated_at' => now(),
            ]);

            DB::table('quality_detection_logs')->where('id', $log)->update([
                'status' => 'completed',
                'detected_quality' => $qualityLevel,
                'detection_method' => $analysis['method'] ?? 'unknown',
                'metadata' => json_encode($analysis),
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'success' => true,
                'quality' => $qualityLevel,
                'badge' => $badge,
                'data' => $analysis,
            ];
        } catch (\Exception $e) {
            DB::table('quality_detection_logs')->where('id', $log)->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

            Log::error('Channel quality detection failed', [
                'channel_id' => $channel->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'quality' => null,
                'badge' => null,
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    public function detectVODQuality(VODMedia $vodMedia): array
    {
        $log = DB::table('quality_detection_logs')->insertGetId([
            'content_type' => 'vod',
            'content_id' => $vodMedia->id,
            'status' => 'processing',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $streamUrl = $vodMedia->stream_url;

            $analysis = $this->analyzeStream($streamUrl);

            $qualityLevel = $this->determineQualityLevel(
                $analysis['width'] ?? null,
                $analysis['height'] ?? null,
                $analysis['bitrate'] ?? null
            );

            $badge = $this->getQualityBadge($qualityLevel);

            DB::table('vod_quality_cache')->updateOrInsert(
                ['vod_media_id' => $vodMedia->id],
                [
                    'quality_level' => $qualityLevel,
                    'resolution_width' => $analysis['width'] ?? null,
                    'resolution_height' => $analysis['height'] ?? null,
                    'bitrate' => $analysis['bitrate'] ?? null,
                    'video_codec' => $analysis['video_codec'] ?? null,
                    'audio_codec' => $analysis['audio_codec'] ?? null,
                    'frame_rate' => $analysis['frame_rate'] ?? null,
                    'file_size' => $vodMedia->file_size,
                    'scan_timestamp' => now(),
                    'is_transcoded' => false,
                    'source_quality' => $qualityLevel,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $vodMedia->update([
                'quality' => $qualityLevel,
            ]);

            DB::table('quality_detection_logs')->where('id', $log)->update([
                'status' => 'completed',
                'detected_quality' => $qualityLevel,
                'detection_method' => $analysis['method'] ?? 'unknown',
                'metadata' => json_encode($analysis),
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'success' => true,
                'quality' => $qualityLevel,
                'badge' => $badge,
                'data' => $analysis,
            ];
        } catch (\Exception $e) {
            DB::table('quality_detection_logs')->where('id', $log)->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

            Log::error('VOD quality detection failed', [
                'vod_media_id' => $vodMedia->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'quality' => null,
                'badge' => null,
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    public function scanAllChannels(): array
    {
        $channels = Channel::where('is_active', true)->get();
        $results = ['success' => 0, 'failed' => 0, 'total' => $channels->count()];

        foreach ($channels as $channel) {
            $result = $this->detectChannelQuality($channel);

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
            }

            usleep(500000);
        }

        return $results;
    }

    public function scanAllVOD(): array
    {
        $mediaItems = VODMedia::where('is_available', true)->get();
        $results = ['success' => 0, 'failed' => 0, 'total' => $mediaItems->count()];

        foreach ($mediaItems as $media) {
            $result = $this->detectVODQuality($media);

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
            }

            usleep(500000);
        }

        return $results;
    }

    public function getQualityBadge(string $level): string
    {
        return $this->qualityLevels[$level]['badge'] ?? '❓ Unknown';
    }

    public function determineQualityLevel(?int $width, ?int $height, ?int $bitrate): string
    {
        $resolutionLevel = $this->determineByResolution($height);
        $bitrateLevel = $this->determineByBitrate($bitrate);

        $resolutionOrder = array_flip(array_keys($this->qualityLevels));
        $resIdx = $resolutionOrder[$resolutionLevel] ?? count($resolutionOrder) - 1;
        $bitIdx = $resolutionOrder[$bitrateLevel] ?? count($resolutionOrder) - 1;

        $conservativeIdx = max($resIdx, $bitIdx);

        return array_keys($this->qualityLevels)[$conservativeIdx] ?? 'low';
    }

    protected function determineByResolution(?int $height): string
    {
        if ($height === null) {
            return 'low';
        }

        $settings = $this->settings;

        if ($height >= ($settings->resolution_4k_min ?? 2160)) {
            return '4k';
        }
        if ($height >= ($settings->resolution_fhd_min ?? 1080)) {
            return 'fhd';
        }
        if ($height >= ($settings->resolution_hd_min ?? 720)) {
            return 'hd';
        }
        if ($height >= ($settings->resolution_sd_min ?? 480)) {
            return 'sd';
        }

        return 'low';
    }

    protected function determineByBitrate(?int $bitrate): string
    {
        if ($bitrate === null) {
            return 'low';
        }

        $settings = $this->settings;

        if ($bitrate >= ($settings->bitrate_4k_min ?? 20000)) {
            return '4k';
        }
        if ($bitrate >= ($settings->bitrate_fhd_min ?? 8000)) {
            return 'fhd';
        }
        if ($bitrate >= ($settings->bitrate_hd_min ?? 4500)) {
            return 'hd';
        }
        if ($bitrate >= ($settings->bitrate_sd_min ?? 1000)) {
            return 'sd';
        }

        return 'low';
    }

    protected function analyzeStream(string $streamUrl): array
    {
        try {
            return $this->ffprobeAnalysis($streamUrl);
        } catch (\Exception $e) {
            Log::info('FFProbe unavailable or failed, using fallback detection', [
                'url' => $streamUrl,
                'error' => $e->getMessage(),
            ]);

            return $this->fallbackDetection($streamUrl);
        }
    }

    protected function ffprobeAnalysis(string $streamUrl): array
    {
        $timeout = $this->getSettings()->scan_timeout ?? 30;
        $command = sprintf(
            'ffprobe -v quiet -print_format json -show_format -show_streams %s 2>&1',
            escapeshellarg($streamUrl)
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            throw new \RuntimeException('Failed to start FFProbe process');
        }

        stream_set_timeout($pipes[1], $timeout);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $data = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to parse FFProbe output: ' . json_last_error_msg());
        }

        $width = null;
        $height = null;
        $bitrate = null;
        $videoCodec = null;
        $audioCodec = null;
        $frameRate = null;

        foreach ($data['streams'] ?? [] as $stream) {
            if (($stream['codec_type'] ?? '') === 'video') {
                $width = (int) ($stream['width'] ?? 0);
                $height = (int) ($stream['height'] ?? 0);
                $videoCodec = $stream['codec_name'] ?? null;

                if (isset($stream['r_frame_rate'])) {
                    $parts = explode('/', $stream['r_frame_rate']);
                    if (count($parts) === 2 && (int) $parts[1] > 0) {
                        $frameRate = round((int) $parts[0] / (int) $parts[1], 2);
                    }
                }
            } elseif (($stream['codec_type'] ?? '') === 'audio') {
                $audioCodec = $stream['codec_name'] ?? null;
            }
        }

        if (isset($data['format']['bit_rate'])) {
            $bitrate = (int) ($data['format']['bit_rate'] / 1000);
        }

        return [
            'width' => $width ?: null,
            'height' => $height ?: null,
            'bitrate' => $bitrate,
            'video_codec' => $videoCodec,
            'audio_codec' => $audioCodec,
            'frame_rate' => $frameRate,
            'method' => 'ffprobe',
        ];
    }

    protected function fallbackDetection(string $streamUrl): array
    {
        $url = strtolower($streamUrl);

        $patterns = [
            '4k' => ['/4k/', '/2160p/', '/uhd/'],
            'fhd' => ['/1080p/', '/fhd/'],
            'hd' => ['/720p/', '/hd/'],
            'sd' => ['/480p/', '/sd/'],
            'low' => ['/360p/', '/240p/', '/low/'],
        ];

        foreach ($patterns as $level => $regexes) {
            foreach ($regexes as $pattern) {
                if (preg_match($pattern, $url)) {
                    $resolutionMap = [
                        '4k' => ['width' => 3840, 'height' => 2160, 'bitrate' => 20000],
                        'fhd' => ['width' => 1920, 'height' => 1080, 'bitrate' => 8000],
                        'hd' => ['width' => 1280, 'height' => 720, 'bitrate' => 4500],
                        'sd' => ['width' => 640, 'height' => 480, 'bitrate' => 1000],
                        'low' => ['width' => 320, 'height' => 240, 'bitrate' => 500],
                    ];

                    $data = $resolutionMap[$level];

                    return [
                        'width' => $data['width'],
                        'height' => $data['height'],
                        'bitrate' => $data['bitrate'],
                        'video_codec' => null,
                        'audio_codec' => null,
                        'frame_rate' => null,
                        'method' => 'url_pattern',
                    ];
                }
            }
        }

        return [
            'width' => null,
            'height' => null,
            'bitrate' => null,
            'video_codec' => null,
            'audio_codec' => null,
            'frame_rate' => null,
            'method' => 'unknown',
        ];
    }

    public function getStats(): array
    {
        $channelStats = DB::table('channel_quality_cache')
            ->select('quality_level', DB::raw('count(*) as count'))
            ->groupBy('quality_level')
            ->pluck('count', 'quality_level')
            ->toArray();

        $vodStats = DB::table('vod_quality_cache')
            ->select('quality_level', DB::raw('count(*) as count'))
            ->groupBy('quality_level')
            ->pluck('count', 'quality_level')
            ->toArray();

        return [
            'channels' => $channelStats,
            'vod' => $vodStats,
            'total_channels' => Channel::where('is_active', true)->count(),
            'total_vod' => VODMedia::where('is_available', true)->count(),
        ];
    }
}
