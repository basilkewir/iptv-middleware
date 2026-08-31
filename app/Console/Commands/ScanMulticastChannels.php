<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\ContentCategory;
use App\Services\StreamingService\FlussonicService;
use App\Services\StreamingService\MulticastScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ScanMulticastChannels extends Command
{
    protected $signature = 'channels:scan-multicast
        {url : Multicast TS URL, e.g. udp://@239.0.0.1:32768 or rtp://239.0.0.4:32768}
        {--local-addr= : Local interface IP used to join the multicast group}
        {--import : Create a Channel row for every program found}
        {--select= : Comma-separated list of program IDs to import (use with --import; e.g. 3,7,12)}
        {--name-map= : Comma-separated program_number=ChannelName overrides, e.g. \\"104=CRTV,1=RTS 1\\"}
        {--category= : Content category id assigned to imported channels}
        {--timeout=15 : Seconds to wait for the PAT/PMT before giving up}';

    protected $description = 'List all channels (programs) inside a multicast MPEG-TS and optionally import them as channels';

    public function handle(MulticastScanner $scanner, FlussonicService $flussonic): int
    {
        $url = $this->argument('url');
        $localAddr = $this->option('local-addr');
        $timeout = max(5, (int) $this->option('timeout'));

        if (! str_starts_with($url, 'udp://') && ! str_starts_with($url, 'rtp://')) {
            $this->error('Only udp:// or rtp:// multicast URLs are supported.');

            return self::FAILURE;
        }

        $this->line(sprintf(
            'Probing %s%s ...',
            $url,
            $localAddr ? ' via ' . $localAddr : ''
        ));

        $programs = $scanner->scan($url, $localAddr, $timeout);

        if (empty($programs)) {
            $this->error('No programs detected. Is the multicast stream up and is ' .
                'ffprobe installed (FFPROBE_PATH)?');

            return self::FAILURE;
        }

        $this->table(
            ['Program', 'Name', 'Provider', 'Video', 'Audio'],
            array_map(fn ($p) => [
                $p['program_id'],
                $p['tags']['service_name'] ?? $p['tags']['service_provider'] ?? '(unnamed)',
                $p['tags']['service_provider'] ?? '-',
                $p['video'] ? ($p['video']['codec'] . ' ' . ($p['video']['height'] ?: '?')) : '-',
                $p['audio'] ?? '-',
            ], $programs)
        );

        if (! $this->option('import')) {
            $this->info(sprintf('Found %d channel(s). Re-run with --import to create them.', count($programs)));

            return self::SUCCESS;
        }

        $select = $this->option('select');

        if (($select === null || trim((string) $select) === '') && $this->isInteractiveShell()) {
            $select = $this->promptForSelection($programs) ?: null;
        }

        if ($select !== null && trim((string) $select) !== '' && ! $this->isValidSelection($select)) {
            $this->error(sprintf('--select="%s" contains invalid program IDs. Only comma-separated integers are allowed (e.g. 3,7,12).', $select));

            return self::FAILURE;
        }

        return $this->importPrograms($url, $localAddr, $programs, $select, $flussonic);
    }

    private function importPrograms(string $url, ?string $localAddr, array $programs, ?string $select, FlussonicService $flussonic): int
    {
        $categoryId = $this->option('category')
            ? (int) $this->option('category')
            : null;

        if ($categoryId !== null && ! ContentCategory::whereKey($categoryId)->exists()) {
            $this->error('The --category id does not exist.');

            return self::FAILURE;
        }

        $selected = $this->parseSelection($select);

        if ($selected !== null) {
            $programIds = array_map(fn ($p) => (int) $p['program_id'], $programs);
            $programs = array_filter($programs, fn ($p) => in_array((int) $p['program_id'], $selected, true));

            if (count($programs) === 0) {
                $this->error(sprintf(
                    'No matching programs for --select=%s. Available program IDs: %s',
                    $this->option('select'),
                    implode(', ', $programIds) ?: '(none)'
                ));

                return self::FAILURE;
            }

            $this->line(sprintf('Selected %d of %d program(s) for import.', count($programs), count($programIds)));
        }

                        $imported = 0;
        $skipped = 0;

        // Build a program_number => ChannelName override map from --name-map.
        // Any program not listed in the map still falls back to the service_name
        // embedded in the TS, or "Multicast Program <pid>" if unnamed.
        $nameMap = $this->parseNameMap((string) $this->option('name-map'));

        foreach ($programs as $program) {
            $programId = (int) $program['program_id'];

            // Prefer the caller-supplied name for this program, otherwise the
            // service_name/service_provider tag embedded in the MPEG-TS, and
            // finally a generic fallback.
            $name = $nameMap[$programId]
                ?? trim($program['tags']['service_name'] ?? $program['tags']['service_provider'] ?? '')
                ?: 'Multicast Program ' . $programId;

            $existing = Channel::where('stream_url', $url)
                ->where('program_number', $programId)
                ->first();

            if ($existing) {
                $skipped++;
                $this->line(sprintf('  - %s (program %d): already exists (#%s)', $name, $programId, $existing->channel_number));
                continue;
            }

            // Push the UDP program into Flussonic and get back its HLS URL.
            // The stream name embeds the MPEG-TS program (PAT) number for
            // stable, human-readable identification in Flussonic.
            $streamName = 'ch-' . $programId . '-' . Str::slug($name);
            $hlsUrl     = $url; // fallback: keep raw UDP if Flussonic is unreachable

            try {
                $hlsUrl = $flussonic->ensureStream($streamName, $url, $programId, $localAddr);
                $this->line(sprintf('  - pushed to Flussonic as "%s" → %s', $streamName, $hlsUrl));
            } catch (\Throwable $e) {
                $this->warn(sprintf('  - Flussonic unavailable (%s), storing raw UDP URL', $e->getMessage()));
            }

            $channel = Channel::create([
                'name'           => $name,
                'slug'           => $this->uniqueSlug($name),
                'channel_number' => $programId,
                'stream_url'     => $hlsUrl,
                'stream_type'    => (str_starts_with($hlsUrl, 'udp://') || str_starts_with($hlsUrl, 'rtp://')) ? 'udp' : 'hls',
                'program_number' => $programId,
                'local_address'  => $localAddr,
                'is_active'      => true,
            ]);

            if ($categoryId !== null) {
                $channel->categories()->sync([$categoryId]);
            }

            $imported++;
            $this->line(sprintf('  - imported %s (program %d) as channel #%s', $name, $programId, $channel->channel_number));
        }

        $this->info(sprintf('Import complete: %d created, %d skipped.', $imported, $skipped));

        if ($imported > 0) {
            $this->line('Run channels:ingest-all (or wait for the scheduler) to start HLS ingests.');
        }

        return self::SUCCESS;
    }

    /**
     * Parse a --name-map value like "104=CRTV,1=RTS 1" into a
     * [program_number => ChannelName] array. Non-numeric keys are silently
     * skipped so partial maps still work.
     *
     * @return array<int, string>
     */
    private function parseNameMap(string $map): array
    {
        $result = [];

        if (trim($map) === '') {
            return $result;
        }

        foreach (explode(',', $map) as $entry) {
            $entry = trim($entry);

            if ($entry === '' || ! str_contains($entry, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $entry, 2);
            $key = trim($key);
            $value = trim($value);

            if (ctype_digit($key) && $value !== '') {
                $result[(int) $key] = $value;
            }
        }

        return $result;
    }
    private function uniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $base = $slug;
        $i = 2;

        while (Channel::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    /**
     * Interactively let the operator pick which program IDs to import.
     * Returns a comma-separated string (e.g. "3,7,12") of chosen program IDs,
     * or null/empty string if nothing was chosen.
     */
    private function promptForSelection(array $programs): ?string
    {
        $choices = array_map(function ($p): string {
            $id = (int) $p['program_id'];
            $name = $p['tags']['service_name'] ?? $p['tags']['service_provider'] ?? '(unnamed)';

            return sprintf('%d — %s', $id, $name);
        }, $programs);

        if ($this->confirm('Import only some of these channels? (choose below)', true)) {
            $selected = $this->choice(
                'Select the channels to import (arrows / space to toggle, enter to confirm)',
                $choices,
                null,
                true,
                true
            );

            $ids = [];
            foreach ((array) $selected as $choice) {
                $ids[] = (int) explode(' — ', $choice, 2)[0];
            }

            return $ids === [] ? null : implode(',', array_values(array_unique($ids)));
        }

        return null;
    }

    /**
     * Only offer the interactive program picker when we are really attached to
     * a human terminal. In automated contexts (cron, scheduler, HTTP tests)
     * there is no TTY, so isInteractive() would otherwise block forever on the
     * prompt and break batch imports (which use --select or --import-all).
     */
    private function isInteractiveShell(): bool
    {
        if (! function_exists('posix_isatty')) {
            return false;
        }

        return @posix_isatty(STDIN) === true;
    }

    /**
     * Parse a comma-separated list of program IDs into a list of ints.
     *
     * @return int[]|null
     */
    private function parseSelection(?string $select): ?array
    {
        if ($select === null || trim($select) === '') {
            return null;
        }

        $ids = [];
        foreach (explode(',', $select) as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            $ids[] = (int) $item;
        }

        return $ids === [] ? [] : array_values(array_unique($ids));
    }

    /**
     * Check --select contains only comma-separated integers (non-empty).
     */
    private function isValidSelection(string $select): bool
    {
        foreach (explode(',', $select) as $item) {
            if (! ctype_digit(trim($item))) {
                return false;
            }
        }

        return true;
    }
}
