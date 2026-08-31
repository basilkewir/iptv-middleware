<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Channel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class YouTubeService
{
    private const YT_COOKIE_CACHE_TTL = 86400;
    private const YT_RESOLVE_TIMEOUT = 60;
    private const YT_DLP_COOKIE_FILE_TTL = 2592000;

    private string $ytDlpPath;

    public function __construct()
    {
        $this->ytDlpPath = $this->findYtDlp();
    }

    private function findYtDlp(): string
    {
        $paths = ['yt-dlp', '/usr/local/bin/yt-dlp', '/usr/bin/yt-dlp'];
        foreach ($paths as $path) {
            $output = shell_exec("which {$path} 2>/dev/null");
            if (!empty(trim($output ?? ''))) {
                return trim($output);
            }
        }
        return 'yt-dlp';
    }

    public function isYtDlpAvailable(): bool
    {
        return $this->ytDlpPath !== 'yt-dlp' || shell_exec('which yt-dlp 2>/dev/null') !== null;
    }

    /**
     * Parse a YouTube URL and extract the channel ID or video ID.
     */
    public function parseYouTubeUrl(string $url): array
    {
        $patterns = [
            'channel' => [
                '/youtube\.com\/@([\w-]+)/i',
                '/youtube\.com\/channel\/([a-zA-Z0-9_-]+)/i',
                '/youtube\.com\/c\/([a-zA-Z0-9_-]+)/i',
                '/youtube\.com\/user\/([a-zA-Z0-9_-]+)/i',
                '/youtube\.com\/handle\/([a-zA-Z0-9_-]+)/i',
            ],
            'video' => [
                '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/i',
                '/youtube\.com\/live\/([a-zA-Z0-9_-]+)/i',
                '/youtube\.com\/shorts\/([a-zA-Z0-9_-]+)/i',
                '/youtu\.be\/([a-zA-Z0-9_-]+)/i',
            ],
        ];

        foreach ($patterns['channel'] as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return [
                    'type' => 'channel',
                    'id' => $matches[1],
                    'url' => $url,
                    'resolved_url' => "https://www.youtube.com/@{$matches[1]}",
                ];
            }
        }

        foreach ($patterns['video'] as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return [
                    'type' => 'video',
                    'id' => $matches[1],
                    'url' => $url,
                    'resolved_url' => "https://www.youtube.com/watch?v={$matches[1]}",
                ];
            }
        }

        return [
            'type' => 'unknown',
            'id' => null,
            'url' => $url,
            'resolved_url' => $url,
        ];
    }

    /**
     * Resolve a YouTube URL to an HLS stream URL using yt-dlp.
     * Stores cookies to always bypass robot verification on subsequent calls.
     */
    public function resolveToStreamUrl(?Channel $channel): array
    {
        if ($channel === null) {
            return ['success' => false, 'error' => 'No channel provided'];
        }

        if (!$channel->youtube_url) {
            return ['success' => false, 'error' => 'No YouTube URL configured'];
        }

        $parsed = $this->parseYouTubeUrl($channel->youtube_url);

        if ($parsed['type'] === 'unknown') {
            return ['success' => false, 'error' => 'Invalid YouTube URL format'];
        }

        $cookieData = $this->loadCookies($channel);

        try {
            $streamUrl = $this->fetchStreamUrl($channel->youtube_url, $cookieData);

            if ($streamUrl) {
                $channel->source_url = $streamUrl;
                $channel->source_type = 'youtube';
                $channel->stream_type = 'hls';
                $channel->save();

                return [
                    'success' => true,
                    'stream_url' => $streamUrl,
                    'type' => $parsed['type'],
                    'channel_id' => $parsed['id'],
                ];
            }

            return ['success' => false, 'error' => 'Failed to resolve stream URL'];
        } catch (\Exception $e) {
            Log::error('YouTube stream resolution failed', [
                'channel_id' => $channel?->id,
                'url' => $channel?->youtube_url ?? '',
                'error' => $e->getMessage(),
            ]);

            $this->handleVerificationFailure($channel, $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fetch the actual HLS stream URL from YouTube using yt-dlp.
     * Uses stored cookies to bypass robot verification.
     */
    public function fetchStreamUrl(string $url, array $cookies = []): ?string
    {
        $cookieFile = $this->getCookieFilePath($url);

        if (!empty($cookies)) {
            $this->writeCookieFile($cookieFile, $cookies);
        }

        $parts = [
            'timeout ' . self::YT_RESOLVE_TIMEOUT,
            escapeshellarg($this->ytDlpPath),
            '-j --no-download',
        ];

        if (!empty($cookies)) {
            $parts[] = '--cookies ' . escapeshellarg($cookieFile);
        }

        $parts[] = escapeshellarg($url);

        $command = implode(' ', $parts);

        $output = shell_exec($command . ' 2>/dev/null');

        if (!empty($cookies) && is_file($cookieFile)) {
            @unlink($cookieFile);
        }

        if (empty($output)) {
            return null;
        }

        $data = json_decode(trim($output), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('yt-dlp JSON parse error', ['output' => substr($output, 0, 500)]);
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        $manifestUrl = $data['manifest_url'] ?? $data['formats'][0]['manifest_url'] ?? null;
        if ($manifestUrl && str_contains($manifestUrl, 'hls_variant')) {
            return $manifestUrl;
        }

        $requestedFormats = $data['requested_formats'] ?? [];
        if (!empty($requestedFormats)) {
            $combinedUrl = $requestedFormats[0]['url'] ?? null;
            if ($combinedUrl && str_contains($combinedUrl, '.m3u8')) {
                return $combinedUrl;
            }
        }

        $urlField = $data['url'] ?? $data['webpage_url'] ?? null;
        $formats = $data['formats'] ?? [];

        if (!empty($formats)) {
            usort($formats, fn ($a, $b) => ($b['width'] ?? 0) <=> ($a['width'] ?? 0));
            foreach ($formats as $format) {
                $ext = $format['ext'] ?? '';
                if (!empty($format['url']) && ($ext === 'mp4' || $ext === 'webm' || $ext === 'm3u8' || str_contains($format['url'] ?? '', '.m3u8'))) {
                    return $format['url'];
                }
            }
            $firstUrl = $formats[0]['url'] ?? null;
            if ($firstUrl) {
                return $firstUrl;
            }
        }

        if ($urlField) {
            return $urlField;
        }

        if (is_string($output)) {
            if (preg_match('/https?:\/\/[^\s"]+\.m3u8[^\s"]*/', $output, $m)) {
                return $m[0];
            }
            if (preg_match('/https?:\/\/[^\s"]+/', $output, $m)) {
                return $m[0];
            }
        }

        return null;
    }

    /**
     * Bypass YouTube robot verification by extracting cookies from a successful yt-dlp session
     * and storing them for future requests.
     */
    public function bypassVerification(?Channel $channel, string $url): array
    {
        $cookieFile = $this->getCookieFilePath($url);

        file_put_contents($cookieFile, "# Netscape HTTP Cookie File\n");

        $command = sprintf(
            'timeout %d %s --cookies %s -j --flat-playlist --no-download --skip-download %s 2>&1',
            self::YT_RESOLVE_TIMEOUT,
            escapeshellarg($this->ytDlpPath),
            escapeshellarg($cookieFile),
            escapeshellarg($url)
        );

        $output = shell_exec($command . ' 2>&1');

        $cookies = [];
        if (is_file($cookieFile)) {
            $rawCookies = file_get_contents($cookieFile);
            $cookies = $this->parseRawCookies($rawCookies);
            @unlink($cookieFile);
        }

        if (!empty($cookies) && $channel !== null) {
            $channel->setYouTubeCookies($cookies);
            $channel->save();
        }

        $parsed = $this->parseYouTubeUrl($url);

        return [
            'success' => !empty($cookies),
            'cookies' => $cookies,
            'type' => $parsed['type'],
            'channel_id' => $parsed['id'],
            'verified_at' => now()->toISOString(),
        ];
    }

    /**
     * Force verification bypass using stored cookies.
     * Always uses remembered cookies to bypass robot verification.
     */
    public function forceBypassWithStoredCookies(Channel $channel): array
    {
        if (!$channel->isYouTube()) {
            return ['success' => false, 'error' => 'Not a YouTube channel'];
        }

        $cookies = $channel->getYouTubeCookies();

        if (empty($cookies)) {
            $result = $this->bypassVerification($channel, $channel->youtube_url);
            return $result;
        }

        $streamUrl = $this->fetchStreamUrl($channel->youtube_url, $cookies);

        if ($streamUrl) {
            $channel->source_url = $streamUrl;
            $channel->save();

            return [
                'success' => true,
                'stream_url' => $streamUrl,
                'bypass_method' => 'stored_cookies',
            ];
        }

        $result = $this->bypassVerification($channel, $channel->youtube_url);
        return $result;
    }

    /**
     * Verify a YouTube URL standalone (without a persisted channel) and
     * resolve it to a playable HLS stream URL. Used by the create/edit
     * verify buttons for both the primary source and backup sources, where
     * the channel may not exist yet.
     */
    public function verifyUrl(string $url): array
    {
        $parsed = $this->parseYouTubeUrl($url);

        if ($parsed['type'] === 'unknown') {
            return ['success' => false, 'error' => 'Invalid YouTube URL format'];
        }

        $bypass = $this->bypassVerification(null, $url);
        $cookies = $bypass['cookies'] ?? [];

        try {
            $streamUrl = $this->fetchStreamUrl($url, $cookies);

            if ($streamUrl) {
                return [
                    'success' => true,
                    'stream_url' => $streamUrl,
                    'cookies' => $cookies,
                    'verified' => !empty($cookies),
                    'type' => $parsed['type'],
                    'channel_id' => $parsed['id'],
                    'verified_at' => now()->toISOString(),
                ];
            }

            return [
                'success' => !empty($cookies),
                'stream_url' => null,
                'cookies' => $cookies,
                'verified' => !empty($cookies),
                'error' => $streamUrl === null ? 'Failed to resolve stream URL' : null,
                'type' => $parsed['type'],
                'channel_id' => $parsed['id'],
            ];
        } catch (\Exception $e) {
            Log::warning('YouTube standalone URL verification failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'type' => $parsed['type'],
                'channel_id' => $parsed['id'],
            ];
        }
    }

    /**
     * Resolve a YouTube URL to a playable HLS stream URL using stored cookies.
     * Accepts an optional URL so backup sources can be resolved in the same
     * way as the primary source without mutating the primary's fields.
     */
    public function resolveUrlToStreamUrl(string $url, array $cookies = []): ?string
    {
        try {
            return $this->fetchStreamUrl($url, $cookies);
        } catch (\Exception $e) {
            Log::warning('YouTube backup URL resolution failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Check if verification is needed and automatically bypass it.
     * Always remembers and uses cookies for bypass.
     */
    public function ensureVerified(?Channel $channel): array
    {
        if ($channel !== null && !$channel->isYouTube()) {
            return ['success' => false, 'error' => 'Not a YouTube channel'];
        }

        if ($channel !== null && $channel->isYouTubeVerified()) {
            return $this->forceBypassWithStoredCookies($channel);
        }

        $url = $channel?->youtube_url ?? '';
        if (empty($url)) {
            return ['success' => false, 'error' => 'No YouTube URL configured'];
        }

        return $this->bypassVerification($channel, $url);
    }

    /**
     * Load stored cookies for a channel.
     */
     private function loadCookies(?Channel $channel): array
     {
         if ($channel === null) {
             return [];
         }

         $cacheKey = "youtube:cookies:{$channel->id}";

         $cached = Cache::get($cacheKey);
         if ($cached) {
             return $cached;
         }

         $cookies = $channel->getYouTubeCookies();

         if (!empty($cookies)) {
             Cache::put($cacheKey, $cookies, self::YT_COOKIE_CACHE_TTL);
             return $cookies;
         }

         return [];
     }

     /**
      * Store cookies in cache and database.
      */
     private function storeCookies(?Channel $channel, array $cookies): void
     {
         if ($channel === null) {
             return;
         }

         $channel->setYouTubeCookies($cookies);
         Cache::put("youtube:cookies:{$channel->id}", $cookies, self::YT_COOKIE_CACHE_TTL);
     }

    /**
     * Write cookies to a cookie file for yt-dlp.
     */
    private function writeCookieFile(string $path, array $cookies): void
    {
        $lines = ["# Netscape HTTP Cookie File"];
        $lines[] = "# http://www.netscape.com/rfc/guidelines.html";
        $lines[] = "";

        foreach ($cookies as $name => $value) {
            $lines[] = implode("\t", [
                '.youtube.com',
                'TRUE',
                '/',
                'FALSE',
                '0',
                $name,
                $value,
            ]);
        }

        file_put_contents($path, implode("\n", $lines));
    }

    /**
     * Get a unique cookie file path for a URL.
     */
    private function getCookieFilePath(string $url): string
    {
        return sys_get_temp_dir() . '/yt_cookies_' . md5($url) . '.txt';
    }

    /**
     * Parse raw cookie file format into an associative array.
     */
    private function parseRawCookies(string $rawCookies): array
    {
        $cookies = [];
        $lines = explode("\n", trim($rawCookies));

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }
            $parts = preg_split('/\t+/', $line);
            if (count($parts) >= 7) {
                $cookies[$parts[5]] = $parts[6];
            }
        }

        return $cookies;
    }

    /**
     * Handle verification failure by attempting re-bypass.
     */
    private function handleVerificationFailure(?Channel $channel, string $error): void
    {
        Log::warning('YouTube verification failed, attempting re-bypass', [
            'channel_id' => $channel?->id,
            'error' => $error,
        ]);

        if ($channel !== null) {
            $channel->youtube_verified = false;
            $channel->save();
            $this->bypassVerification($channel, $channel->youtube_url);
        }
    }
}