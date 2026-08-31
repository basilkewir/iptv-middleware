<?php

declare(strict_types=1);

namespace App\Services\StreamingService;

/**
 * Scans a multicast MPEG-TS stream and enumerates every program (channel)
 * multiplexed inside it. One udp://@... group typically carries several
 * channels; each program is later ingested individually via -map p:N.
 */
class MulticastScanner
{
    private string $ffprobe;

    public function __construct(?string $ffprobe = null)
    {
        $this->ffprobe = $ffprobe ?? (string) config('streaming.transcoding.ffprobe_path', 'ffprobe');
    }

    /**
     * Probe the multicast stream and return its programs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function scan(string $url, ?string $localAddress = null, int $timeout = 15): array
    {
        if (! str_starts_with($url, 'udp://') && ! str_starts_with($url, 'rtp://')) {
            return [];
        }

        $output = $this->probe($this->buildProbeCommand($url, $localAddress, $timeout));

        if ($output === null || $output === '') {
            return [];
        }

        $data = json_decode($output, true);

        if (! is_array($data)) {
            return [];
        }

        return $this->extractPrograms($data);
    }

    public function buildInputUrl(string $url, ?string $localAddress = null): string
    {
        if ($localAddress === null || $localAddress === '') {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . 'localaddr=' . $localAddress;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractPrograms(array $data): array
    {
        $programs = array_values(array_filter(
            $data['programs'] ?? [],
            fn ($p) => isset($p['program_id'])
        ));

        if (empty($programs)) {
            return [];
        }

        // Fall back to single-program TS where the demuxer exposed streams but
        // no PAT/PMT was seen yet — treat program 1 as the whole multiplex.
        $streams = $data['streams'] ?? [];
        if (count($programs) === 1 && ($programs[0]['nb_streams'] ?? 0) <= 0) {
            $programs[0]['nb_streams'] = count($streams);
        }

        foreach ($programs as &$program) {
            $programId = (int) $program['program_id'];

            // ffprobe nests streams inside each program object under 'streams';
            // fall back to top-level streams filtered by program_id.
            $owned = $program['streams'] ?? array_values(array_filter(
                $streams,
                fn ($s) => (int) ($s['program_id'] ?? -1) === $programId
            ));

            $video = null;
            $audioCodecs = [];

            foreach ($owned as $stream) {
                $type = $stream['codec_type'] ?? null;

                if ($type === 'video' && $video === null) {
                    $video = [
                        'codec'  => $stream['codec_name'] ?? null,
                        'width'  => isset($stream['width'])  ? (int) $stream['width']  : null,
                        'height' => isset($stream['height']) ? (int) $stream['height'] : null,
                    ];
                } elseif ($type === 'audio') {
                    $audioCodecs[] = $stream['codec_name'] ?? 'audio';
                }
            }

            $program['video'] = $video;
            $program['audio'] = $audioCodecs !== [] ? implode(', ', array_unique($audioCodecs)) : null;
        }

        return $programs;
    }

    protected function buildProbeCommand(string $url, ?string $localAddress, int $timeout): string
    {
        $input = escapeshellarg($this->buildInputUrl($url, $localAddress));

        $base = sprintf(
            '%s -v quiet -print_format json -show_programs -show_streams -rw_timeout 30000000 -probesize 5M -analyzeduration 5M %s 2>&1',
            escapeshellarg($this->ffprobe),
            $input
        );

        $timeoutBin = trim((string) shell_exec('command -v timeout 2>/dev/null'));

        return $timeoutBin !== ''
            ? escapeshellarg($timeoutBin) . ' ' . ((int) $timeout) . ' ' . $base
            : $base;
    }

    protected function probe(string $command): ?string
    {
        $output = shell_exec($command);

        return is_string($output) ? $output : null;
    }
}
