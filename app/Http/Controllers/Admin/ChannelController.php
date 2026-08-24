<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ContentCategory;
use App\Models\EPGSource;
use App\Models\SubscriptionPackage;
use App\Models\AdminChannel\AdminChannel;
use App\Services\StreamingService\SourceHealthCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ChannelController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $query = Channel::with(['categories', 'epgSource', 'bouquets']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('stream_url', 'like', "%{$search}%")
                    ->orWhere('genre', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $request->input('category_id')) {
            $query->whereHas('categories', fn ($q) => $q->where('categories.id', $categoryId));
        }

        if ($request->has('status')) {
            $query->where('is_active', $request->boolean('status'));
        }

        if ($genre = $request->input('genre')) {
            $query->where('genre', $genre);
        }

        if ($country = $request->input('country')) {
            $query->where('country', $country);
        }

        $channels = $query->latest()->paginate($request->input('per_page', 15));
        $categories = ContentCategory::where('is_active', true)->orderBy('sort_order')->get();

        if ($request->expectsJson()) {
            return response()->json(['data' => $channels]);
        }

        return Inertia::render('Admin/Channels/Index', [
            'channels' => $channels,
            'categories' => $categories,
        ]);
    }

    public function show(Request $request, Channel $channel): Response|JsonResponse
    {
        $channel->load(['categories', 'epgPrograms', 'bouquets', 'epgSource', 'restrictedPackages']);

        if ($request->expectsJson()) {
            return response()->json(['data' => $channel]);
        }

        return Inertia::render('Admin/Channels/Edit', [
            'channel' => $channel,
            'categories' => ContentCategory::where('is_active', true)->get(),
            'bouquets' => \App\Models\Bouquet::where('is_active', true)->get(),
            'epgSources' => EPGSource::where('is_active', true)->get(),
            'packages' => SubscriptionPackage::where('is_active', true)->get(),
        ]);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate(['logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048']);
        $path = $request->file('logo')->store('channel_logos', 'public');
        return response()->json(['url' => asset('storage/' . $path)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'channel_number' => 'nullable|integer',
            'description' => 'nullable|string',
            'logo_url' => 'nullable|string',
            'genre' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:10',
            'language' => 'nullable|string|max:10',
            'stream_url' => 'required|string',
            'stream_type' => 'nullable|in:m3u8,rtmp,rtsp,udp,hls,dash',
            'program_number' => 'nullable|integer|min:1',
            'local_address' => 'nullable|string|max:45',
            'backup_url_1' => 'nullable|string',
            'backup_url_2' => 'nullable|string',
            'quality' => 'nullable|string',
            'bitrate' => 'nullable|integer',
            'epg_id' => 'nullable|string',
            'epg_source_id' => 'nullable|exists:epg_sources,id',
            'epg_language' => 'nullable|string|max:10',
            'timezone_offset' => 'nullable|string|max:10',
            'category_ids' => 'required|array',
            'category_ids.*' => 'exists:content_categories,id',
            'bouquet_ids' => 'nullable|array',
            'bouquet_ids.*' => 'exists:bouquets,id',
            'transcoding_enabled' => 'nullable|boolean',
            'transcoding_profile' => 'nullable|string',
            'transcoding_resolution' => 'nullable|string',
            'transcoding_video_codec' => 'nullable|string',
            'transcoding_audio_codec' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'is_free' => 'nullable|boolean',
            'is_adult' => 'nullable|boolean',
            'is_available_to_all' => 'nullable|boolean',
            'ip_restriction' => 'nullable|string',
            'restricted_package_ids' => 'nullable|array',
            'restricted_package_ids.*' => 'exists:subscription_packages,id',
        ]);

        $channel = Channel::create([
            'name' => $validated['name'],
            'slug' => \Str::slug($validated['name']),
            'channel_number' => $validated['channel_number'] ?? ((int) Channel::max('channel_number') + 1),
            'description' => $validated['description'] ?? null,
            'logo_url' => $validated['logo_url'] ?? null,
            'genre' => $validated['genre'] ?? null,
            'country' => $validated['country'] ?? null,
            'language' => $validated['language'] ?? null,
            'stream_url' => $validated['stream_url'],
            'active_stream_url' => $validated['stream_url'],
            'active_source_index' => 0,
            'stream_type' => $validated['stream_type'] ?? 'hls',
            'program_number' => $validated['program_number'] ?? null,
            'local_address' => $validated['local_address'] ?? null,
            'backup_url_1' => $validated['backup_url_1'] ?? null,
            'backup_url_2' => $validated['backup_url_2'] ?? null,
            'quality' => $validated['quality'] ?? '1080p',
            'bitrate' => $validated['bitrate'] ?? null,
            'epg_id' => $validated['epg_id'] ?? null,
            'epg_source_id' => $validated['epg_source_id'] ?? null,
            'epg_language' => $validated['epg_language'] ?? null,
            'timezone_offset' => $validated['timezone_offset'] ?? null,
            'transcoding_enabled' => $validated['transcoding_enabled'] ?? false,
            'transcoding_profile' => $validated['transcoding_profile'] ?? null,
            'transcoding_resolution' => $validated['transcoding_resolution'] ?? null,
            'transcoding_video_codec' => $validated['transcoding_video_codec'] ?? null,
            'transcoding_audio_codec' => $validated['transcoding_audio_codec'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'is_free' => $validated['is_free'] ?? false,
            'is_adult' => $validated['is_adult'] ?? false,
            'is_available_to_all' => $validated['is_available_to_all'] ?? true,
            'ip_restriction' => $validated['ip_restriction'] ?? null,
        ]);

        $channel->categories()->sync($validated['category_ids']);

        if (!empty($validated['bouquet_ids'])) {
            $channel->bouquets()->sync($validated['bouquet_ids']);
        }

        if (!empty($validated['restricted_package_ids'])) {
            $channel->restrictedPackages()->sync($validated['restricted_package_ids']);
        }

        return redirect()->route('admin.channels.index')
            ->with('success', 'Channel created successfully.');
    }

    public function update(Request $request, Channel $channel): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'channel_number' => 'nullable|integer',
            'description' => 'nullable|string',
            'logo_url' => 'nullable|string',
            'genre' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:10',
            'language' => 'nullable|string|max:10',
            'stream_url' => 'sometimes|string',
            'stream_type' => 'sometimes|in:m3u8,rtmp,rtsp,udp,hls,dash',
            'program_number' => 'sometimes|nullable|integer|min:1',
            'local_address' => 'sometimes|nullable|string|max:45',
            'backup_url_1' => 'nullable|string',
            'backup_url_2' => 'nullable|string',
            'quality' => 'nullable|string',
            'bitrate' => 'nullable|integer',
            'epg_id' => 'nullable|string',
            'epg_source_id' => 'nullable|exists:epg_sources,id',
            'epg_language' => 'nullable|string|max:10',
            'timezone_offset' => 'nullable|string|max:10',
            'category_ids' => 'sometimes|array',
            'category_ids.*' => 'exists:content_categories,id',
            'bouquet_ids' => 'nullable|array',
            'bouquet_ids.*' => 'exists:bouquets,id',
            'transcoding_enabled' => 'nullable|boolean',
            'transcoding_profile' => 'nullable|string',
            'transcoding_resolution' => 'nullable|string',
            'transcoding_video_codec' => 'nullable|string',
            'transcoding_audio_codec' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'is_free' => 'sometimes|boolean',
            'is_adult' => 'sometimes|boolean',
            'is_available_to_all' => 'sometimes|boolean',
            'ip_restriction' => 'nullable|string',
            'restricted_package_ids' => 'nullable|array',
            'restricted_package_ids.*' => 'exists:subscription_packages,id',
        ]);

        if (isset($validated['category_ids'])) {
            $channel->categories()->sync($validated['category_ids']);
            unset($validated['category_ids']);
        }

        if (isset($validated['bouquet_ids'])) {
            $channel->bouquets()->sync($validated['bouquet_ids']);
            unset($validated['bouquet_ids']);
        }

        if (isset($validated['restricted_package_ids'])) {
            $channel->restrictedPackages()->sync($validated['restricted_package_ids']);
            unset($validated['restricted_package_ids']);
        }

        if (isset($validated['name']) && empty($channel->slug)) {
            $validated['slug'] = \Str::slug($validated['name']);
        }

        if (array_key_exists('channel_number', $validated) && $validated['channel_number'] === null) {
            $validated['channel_number'] = $channel->channel_number;
        }

        // When primary stream_url changes and we're still on it, update active_stream_url too
        if (isset($validated['stream_url']) && $channel->active_source_index === 0) {
            $validated['active_stream_url'] = $validated['stream_url'];
        }

        $channel->update($validated);

        return redirect()->route('admin.channels.index')
            ->with('success', 'Channel updated successfully.');
    }

    public function destroy(Request $request, Channel $channel): RedirectResponse
    {
        $channel->categories()->detach();
        $channel->bouquets()->detach();
        $channel->restrictedPackages()->detach();
        $channel->streamAssignments()->delete();
        $channel->delete();

        return redirect()->route('admin.channels.index')
            ->with('success', 'Channel deleted successfully.');
    }

    public function toggleStatus(Request $request, Channel $channel): JsonResponse
    {
        $channel->is_active = !$channel->is_active;
        $channel->save();

        return response()->json([
            'message' => 'Channel status updated successfully.',
            'data' => ['is_active' => $channel->is_active],
        ]);
    }

    /**
     * Return all channels (regular + admin) merged and sorted by channel_number.
     */
    public function allChannels(): JsonResponse
    {
        $regular = Channel::where('is_active', true)
            ->get()
            ->map(fn ($ch) => [
                'id'             => $ch->id,
                'type'           => 'channel',
                'name'           => $ch->name,
                'channel_number' => $ch->channel_number,
                'stream_url'     => $ch->stream_url,
                'logo_url'       => $ch->logo_url ?? '',
                'is_active'      => $ch->is_active,
            ]);

        $admin = AdminChannel::where('is_active', true)
            ->get()
            ->map(fn ($ac) => [
                'id'             => $ac->id,
                'type'           => 'admin_channel',
                'name'           => $ac->channel_name,
                'channel_number' => $ac->channel_number ? (int) $ac->channel_number : null,
                'stream_url'     => $ac->stream_url,
                'logo_url'       => $ac->logo_url ?? '',
                'is_active'      => $ac->is_active,
            ]);

        $merged = $regular->concat($admin)
            ->sortBy('channel_number', SORT_NATURAL)
            ->values();

        return response()->json($merged);
    }

    /**
     * Bulk-update channel_number for an ordered list of channels.
     * Accepts: { items: [{ id: 1, type: 'channel' }, { id: 2, type: 'admin_channel' }] }
     * Assigns sequential numbers starting from 1.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items'   => 'required|array',
            'items.*.id'   => 'required|integer',
            'items.*.type' => 'required|in:channel,admin_channel',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['items'] as $index => $item) {
                $number = $index + 1;
                if ($item['type'] === 'channel') {
                    Channel::where('id', $item['id'])->update(['channel_number' => $number]);
                } else {
                    AdminChannel::where('id', $item['id'])->update(['channel_number' => (string) $number]);
                }
            }
        });

        return response()->json(['message' => 'Channel order updated successfully.']);
    }

    public function bulkImport(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => 'required_without:url|file|mimes:m3u,m3u8,csv|max:10240',
            'url' => 'required_without:file|url',
            'skip_duplicates' => 'nullable|boolean',
            'overwrite_details' => 'nullable|boolean',
            'default_category' => 'nullable|exists:content_categories,id',
            'default_bouquet' => 'nullable|exists:bouquets,id',
            'use_file_categories' => 'nullable|boolean',
            'default_language' => 'nullable|string|max:10',
        ]);

        if ($request->has('url')) {
            $content = file_get_contents($request->url);
        } else {
            $content = file_get_contents($request->file('file')->getRealPath());
        }

        preg_match_all('/^#EXTINF:.*?,(.+)$/m', $content, $nameMatches);
        preg_match_all('/^(?!#)(.+)$/m', $content, $urlMatches);

        $imported = 0;
        $skipped = 0;
        $preview = [];

        $names = $nameMatches[1] ?? [];
        $urls = $urlMatches[1] ?? [];

        $nextChannelNumber = (int) Channel::max('channel_number') + 1;

        foreach ($urls as $i => $url) {
            $url = trim($url);
            if (empty($url)) continue;

            $name = trim($names[$i] ?? 'Channel ' . ($i + 1));
            $existingChannel = Channel::where('name', $name)->first();

            if ($existingChannel) {
                if ($validated['skip_duplicates'] ?? false) {
                    $skipped++;
                    $preview[] = [
                        'name' => $name,
                        'category' => 'Existing',
                        'action' => 'skip',
                    ];
                    continue;
                } elseif ($validated['overwrite_details'] ?? false) {
                    $existingChannel->update([
                        'stream_url' => $url,
                    ]);
                    $imported++;
                    $preview[] = [
                        'name' => $name,
                        'category' => 'Updated',
                        'action' => 'update',
                    ];
                    continue;
                }
            }

            $streamType = 'hls';
            if (str_contains($url, 'rtmp://')) $streamType = 'rtmp';
            elseif (str_contains($url, 'rtsp://')) $streamType = 'rtsp';
            elseif (str_contains($url, 'udp://') || str_contains($url, 'rtp://')) $streamType = 'udp';
            elseif (str_contains($url, '.mpd')) $streamType = 'dash';

            $channel = Channel::create([
                'name' => $name,
                'slug' => \Str::slug($name),
                'channel_number' => $nextChannelNumber++,
                'stream_url' => $url,
                'stream_type' => $streamType,
                'language' => $validated['default_language'] ?? null,
                'is_active' => true,
            ]);

            if ($validated['default_category']) {
                $channel->categories()->sync([$validated['default_category']]);
            }
            if ($validated['default_bouquet']) {
                $channel->bouquets()->sync([$validated['default_bouquet']]);
            }

            $imported++;
            $preview[] = [
                'name' => $name,
                'category' => $validated['default_category'] ? 'New' : 'Uncategorized',
                'action' => 'import',
            ];
        }

        return redirect()->route('admin.channels.index')
            ->with('success', "Import completed. {$imported} channels imported, {$skipped} skipped.");
    }

    public function scanMulticast(Request $request): JsonResponse
    {
        $url = $request->input('url');

        if (empty($url)) {
            return response()->json([
                'success' => false,
                'data' => ['programs' => []],
            ]);
        }

        // Run ffprobe to detect programs in the multi-program TS
        $timeout = 20;
        $command = sprintf(
            'timeout %d ffprobe -v error -show_streams -show_format -of json -analyzeduration 10M -probesize 1M -user_agent "IPTV-Middleware-Scanner" %s 2>&1; echo "EXIT:$?"',
            $timeout,
            escapeshellarg($url)
        );

        $output = shell_exec($command . ' 2>&1');

        // Extract exit code
        $exitCode = 1;
        if (preg_match('/EXIT:(\d+)\s*$/', $output, $m)) {
            $exitCode = (int) $m[1];
            $output = preg_replace('/EXIT:\d+\s*$/', '', $output);
        }

        $data = json_decode(trim($output), true);

        $programs = [];

        if ($exitCode === 0 && $data && (isset($data['streams']) || isset($data['format']))) {
            // Extract program IDs from the streams
            // In a multi-program TS, program_id is found in the program_map_PID or stream indexing
            // We'll extract program IDs from the program_info or use stream indices

            // Get program map info if available
            if (isset($data['programs'])) {
                foreach ($data['programs'] as $prog) {
                    $programs[] = [
                        'program_id' => $prog['program_id'] ?? null,
                        'name' => $prog['name'] ?? 'Program ' . $prog['program_id'],
                    ];
                }
            } else {
                // Fallback: assign program IDs based on stream order
                // In practice, multi-program TS have program numbers in PAT/PMT
                // For now, use the stream indices as rough program IDs
                $streamCount = isset($data['streams']) ? count($data['streams']) : 0;
                for ($i = 0; $i < $streamCount; $i++) {
                    $programs[] = [
                        'program_id' => $i + 1,
                        'name' => 'Program ' . ($i + 1),
                    ];
                }
            }
        }

        return response()->json([
            'success' => $exitCode === 0,
            'data' => ['programs' => $programs],
        ]);
    }

    public function testStream(Request $request, Channel $channel): JsonResponse
    {
        $streamUrl = $channel->stream_url;
        $startTime = microtime(true);

        if (empty($streamUrl)) {
            return response()->json([
                'success' => false,
                'data' => [
                    'status' => 'offline',
                    'http_code' => 0,
                    'response_time' => 0,
                    'error' => 'No stream URL configured',
                ],
            ]);
        }

        try {
            $detectedType = $channel->stream_type ?: $this->detectStreamType($streamUrl);

            // Use ffprobe to validate the actual stream
            $probeResult = $this->probeStreamWithFfprobe($streamUrl, $detectedType);

            $responseTime = round((microtime(true) - $startTime) * 1000);

            // Detect quality from stream URL patterns and probe data
            $quality = $this->detectStreamQuality(
                $streamUrl,
                $probeResult['content_type'] ?? null,
                0
            );

            // Override quality with ffprobe-detected resolution if available
            if (!empty($probeResult['height'])) {
                $quality = $this->getQualityFromHeight($probeResult['height']);
            }

            return response()->json([
                'success' => $probeResult['online'],
                'data' => [
                    'status' => $probeResult['online'] ? 'online' : 'offline',
                    'http_code' => $probeResult['http_code'] ?? 0,
                    'response_time' => $responseTime,
                    'content_type' => $probeResult['content_type'] ?? null,
                    'quality' => $quality,
                    'detected_type' => $detectedType,
                    'codec' => $probeResult['codec'] ?? null,
                    'resolution' => $probeResult['resolution'] ?? null,
                    'bitrate' => $probeResult['bitrate'] ?? null,
                    'fps' => $probeResult['fps'] ?? null,
                    'error' => $probeResult['error'] ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => [
                    'status' => 'offline',
                    'http_code' => 0,
                    'response_time' => round((microtime(true) - $startTime) * 1000),
                    'error' => $e->getMessage(),
                ],
            ]);
        }
    }

    /**
     * Probe a stream using ffprobe to determine if it's actually live and working.
     */
    protected function probeStreamWithFfprobe(string $url, string $type): array
    {
        $result = [
            'online' => false,
            'error' => null,
        ];

        // Check if ffprobe exists first
        if (shell_exec('which ffprobe 2>/dev/null') === null) {
            // Fallback to curl if ffprobe is not available
            return $this->probeStreamWithCurl($url, $type);
        }

        // Build ffprobe command
        // -v error: only show errors
        // -show_streams: show stream info
        // -show_format: show format info
        // -of json: output as JSON
        // -analyzeduration 10M: analyze for max 10 seconds
        // -probesize 1M: probe max 1MB of data
        // 2>&1: redirect stderr to stdout so we capture error messages
        // ; echo "EXIT:$?": capture the exit code
        $timeout = 15;
        $isHls = in_array($type, ['hls', 'm3u8']) || str_contains(strtolower($url), '.m3u8');

        $rwTimeout = $isHls ? '-rw_timeout 15000000' : '';
        $command = sprintf(
            'timeout %d ffprobe -v error -show_streams -show_format -of json -analyzeduration 10M -probesize 1M -user_agent "IPTV-Middleware-Checker" %s %s 2>&1; echo "EXIT:$?"',
            $timeout,
            $rwTimeout,
            escapeshellarg($url)
        );

        $output = shell_exec($command);

        // Extract exit code from the output
        $exitCode = 1;
        if (preg_match('/EXIT:(\d+)\s*$/', $output, $m)) {
            $exitCode = (int) $m[1];
            // Remove the EXIT line from output
            $output = preg_replace('/EXIT:\d+\s*$/', '', $output);
        }

        // Parse ffprobe JSON output
        $data = json_decode($output, true);

        // A stream is only online if:
        // 1. ffprobe exited with code 0 (success)
        // 2. JSON was parsed successfully
        // 3. The JSON contains actual stream or format data (not just an empty {})
        $hasStreamData = is_array($data) && (!empty($data['streams']) || !empty($data['format']));

        if ($exitCode === 0 && $hasStreamData) {
            // Stream is online - extract stream info
            $result['online'] = true;

            // Extract video stream info
            if (!empty($data['streams'])) {
                foreach ($data['streams'] as $stream) {
                    if (($stream['codec_type'] ?? '') === 'video') {
                        $result['codec'] = $stream['codec_name'] ?? null;
                        $result['height'] = $stream['height'] ?? null;
                        $result['width'] = $stream['width'] ?? null;
                        $result['resolution'] = ($stream['width'] ?? '?') . 'x' . ($stream['height'] ?? '?');
                        $result['fps'] = $this->parseFps($stream['r_frame_rate'] ?? null);
                        $result['bitrate'] = isset($stream['bit_rate']) ? (int) $stream['bit_rate'] : null;
                        break;
                    }
                }
            }

            // Extract format-level info
            if (!empty($data['format'])) {
                $result['bitrate'] = $result['bitrate'] ?? (isset($data['format']['bit_rate']) ? (int) $data['format']['bit_rate'] : null);
                $result['content_type'] = $data['format']['format_name'] ?? null;
            }
        } else {
            // ffprobe failed - stream is offline or invalid
            $errorOutput = trim($output);
            $result['online'] = false;

            // Parse common ffprobe errors
            if (str_contains($errorOutput, 'Failed to resolve hostname') || str_contains($errorOutput, 'Name or service not known') || str_contains($errorOutput, 'Could not resolve host')) {
                $result['error'] = 'Could not resolve host (DNS failure) - domain does not exist';
            } elseif (str_contains($errorOutput, 'Connection refused')) {
                $result['error'] = 'Connection refused - server is not accepting connections';
            } elseif (str_contains($errorOutput, 'Connection timed out') || str_contains($errorOutput, 'timed out')) {
                $result['error'] = 'Connection timed out - stream is not responding';
            } elseif (str_contains($errorOutput, 'Server returned 404') || str_contains($errorOutput, '404 Not Found')) {
                $result['error'] = 'Stream not found (HTTP 404)';
            } elseif (str_contains($errorOutput, 'Server returned 403') || str_contains($errorOutput, '403 Forbidden')) {
                $result['error'] = 'Access denied (HTTP 403)';
            } elseif (str_contains($errorOutput, 'Server returned 5')) {
                $result['error'] = 'Server error - stream is currently unavailable';
            } elseif (str_contains($errorOutput, 'Invalid data') || str_contains($errorOutput, 'no stream')) {
                $result['error'] = 'Invalid stream data - stream may be offline';
            } elseif (str_contains($errorOutput, 'End of file') || str_contains($errorOutput, 'EOF')) {
                $result['error'] = 'Stream ended unexpectedly - no live data';
            } elseif (str_contains($errorOutput, 'Input/output error')) {
                $result['error'] = 'Input/output error - cannot reach the stream server';
            } elseif (str_contains($errorOutput, 'No such file or directory')) {
                $result['error'] = 'Stream file not found on server';
            } elseif (str_contains($errorOutput, 'Permission denied')) {
                $result['error'] = 'Permission denied - cannot access stream';
            } else {
                // Extract the most relevant error line from ffprobe output
                $errorLines = array_filter(explode("\n", $errorOutput), fn ($l) => trim($l) !== '' && trim($l) !== '{' && trim($l) !== '}');
                $result['error'] = !empty($errorLines) ? trim(end($errorLines)) : 'Stream is offline or unreachable';
            }
        }

        return $result;
    }

    /**
     * Fallback: Probe stream using curl when ffprobe is not available.
     */
    protected function probeStreamWithCurl(string $url, string $type): array
    {
        $result = [
            'online' => false,
            'error' => null,
        ];

        $isHls = in_array($type, ['hls', 'm3u8']) || str_contains(strtolower($url), '.m3u8');

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (IPTV-Middleware Stream Checker)');

        if ($isHls) {
            curl_setopt($ch, CURLOPT_NOBODY, false);
            curl_setopt($ch, CURLOPT_RANGE, '0-2048');
        } else {
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        $result['http_code'] = $httpCode;
        $result['content_type'] = $contentType;

        if ($httpCode === 0 || $response === false) {
            $result['error'] = $this->getCurlErrorMessage($errno, $error);
            return $result;
        }

        $isOnline = $httpCode >= 200 && $httpCode < 400;

        if ($isHls && $isOnline && $response) {
            $playlistContent = trim($response);
            if (!str_starts_with($playlistContent, '#EXTM3U')) {
                $isOnline = false;
                $result['error'] = 'Invalid HLS playlist: missing #EXTM3U header';
            }
        }

        $result['online'] = $isOnline;
        if (!$isOnline && empty($result['error'])) {
            $result['error'] = "HTTP {$httpCode} - Stream unavailable";
        }

        return $result;
    }

    /**
     * Parse frame rate from ffprobe rational format (e.g., "30000/1001").
     */
    protected function parseFps(?string $rFrameRate): ?float
    {
        if (!$rFrameRate) return null;

        if (str_contains($rFrameRate, '/')) {
            [$num, $den] = explode('/', $rFrameRate);
            $den = (float) $den;
            if ($den > 0) {
                return round((float) $num / $den, 2);
            }
        }

        return (float) $rFrameRate ?: null;
    }

    /**
     * Get quality label from video height.
     */
    protected function getQualityFromHeight(int $height): string
    {
        if ($height >= 2160) return '4K';
        if ($height >= 1080) return 'FHD (1080p)';
        if ($height >= 720) return 'HD (720p)';
        if ($height >= 480) return 'SD (480p)';
        if ($height >= 360) return 'Low (360p)';
        return 'Unknown';
    }

    /**
     * Get a human-readable error message from curl error.
     */
    protected function getCurlErrorMessage(int $errno, string $error): string
    {
        $messages = [
            CURLE_COULDNT_RESOLVE_HOST => 'Could not resolve host (DNS failure)',
            CURLE_COULDNT_CONNECT => 'Could not connect to server (connection refused)',
            CURLE_OPERATION_TIMEOUTED => 'Connection timed out',
            CURLE_URL_MALFORMAT => 'Malformed URL',
            CURLE_SSL_CERTPROBLEM => 'SSL certificate problem',
            CURLE_SSL_CONNECT_ERROR => 'SSL connection error',
            CURLE_TOO_MANY_REDIRECTS => 'Too many redirects',
            CURLE_RECV_ERROR => 'Failed to receive data',
            CURLE_SEND_ERROR => 'Failed to send data',
        ];

        if (isset($messages[$errno])) {
            return $messages[$errno];
        }

        return $error ?: 'Connection failed (error code: ' . $errno . ')';
    }

    /**
     * Detect stream quality from URL patterns and content analysis.
     */
    protected function detectStreamQuality(string $url, ?string $contentType, int $contentLength): string
    {
        // Check URL patterns for quality indicators
        $urlLower = strtolower($url);

        // 4K / UHD patterns
        if (preg_match('/4k|2160p|uhd|ultra[_-]?hd/i', $urlLower)) {
            return '4K';
        }

        // FHD / 1080p patterns
        if (preg_match('/1080p|fhd|full[_-]?hd|1920x1080|1080/i', $urlLower)) {
            return 'FHD (1080p)';
        }

        // HD / 720p patterns
        if (preg_match('/720p|hd|1280x720|720/i', $urlLower)) {
            return 'HD (720p)';
        }

        // SD / 480p patterns
        if (preg_match('/480p|sd|854x480|640x480|480/i', $urlLower)) {
            return 'SD (480p)';
        }

        // Check for bitrate indicators in URL (e.g., 5000kbps = high quality)
        if (preg_match('/[&?]bitrate=(\d+)/i', $url, $matches)) {
            $bitrate = (int) $matches[1];
            if ($bitrate >= 15000) return '4K';
            if ($bitrate >= 5000) return 'FHD (1080p)';
            if ($bitrate >= 2000) return 'HD (720p)';
            return 'SD (480p)';
        }

        // Check for resolution in URL path
        if (preg_match('/(\d{3,4})x(\d{3,4})/', $url, $matches)) {
            $width = (int) $matches[1];
            $height = (int) $matches[2];
            if ($width >= 3840 || $height >= 2160) return '4K';
            if ($width >= 1920 || $height >= 1080) return 'FHD (1080p)';
            if ($width >= 1280 || $height >= 720) return 'HD (720p)';
            return 'SD (480p)';
        }

        // Check for quality in path segments
        if (preg_match('/(\d+p)/i', $url, $matches)) {
            $p = (int) $matches[1];
            if ($p >= 2160) return '4K';
            if ($p >= 1080) return 'FHD (1080p)';
            if ($p >= 720) return 'HD (720p)';
            return 'SD (480p)';
        }

        // If HLS playlist, try to infer from content type
        if ($contentType) {
            $ct = strtolower($contentType);
            if (str_contains($ct, 'mpegurl') || str_contains($ct, 'm3u8')) {
                return 'Detected (HLS)';
            }
            if (str_contains($ct, 'mpd')) {
                return 'Detected (DASH)';
            }
        }

        return 'Unknown';
    }

    /**
     * Detect stream type from URL.
     */
    protected function detectStreamType(string $url): string
    {
        $urlLower = strtolower($url);

        if (str_contains($urlLower, '.m3u8') || str_contains($urlLower, 'm3u8')) return 'hls';
        if (str_contains($urlLower, '.mpd') || str_contains($urlLower, 'mpd')) return 'dash';
        if (str_contains($urlLower, 'rtmp://')) return 'rtmp';
        if (str_contains($urlLower, 'rtsp://')) return 'rtsp';
        if (str_contains($urlLower, 'udp://') || str_contains($urlLower, 'rtp://')) return 'udp';

        return 'hls';
    }

    /**
     * Return source status for all channels on the current page (lightweight JSON for polling).
     */
    public function sourceStatuses(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return response()->json(['data' => []]);
        }

        $channels = Channel::whereIn('id', $ids)
            ->select('id', 'source_status', 'source_last_checked_at', 'source_check_attempts', 'source_last_online_at', 'source_last_error', 'active_source_index', 'active_stream_url')
            ->get()
            ->keyBy('id');

        return response()->json([
            'data' => $channels->map(fn ($ch) => [
                'id' => $ch->id,
                'source_status' => $ch->source_status,
                'source_last_checked_at' => $ch->source_last_checked_at,
                'source_check_attempts' => $ch->source_check_attempts,
                'source_last_online_at' => $ch->source_last_online_at,
                'source_last_error' => $ch->source_last_error,
                'active_source_index' => $ch->active_source_index,
                'active_source_label' => $ch->active_source_label,
            ])->values(),
        ]);
    }

    /**
     * Check the health of a channel's source URL and auto-restart if offline.
     */
    public function checkSource(Channel $channel, SourceHealthCheckService $healthCheck): JsonResponse
    {
        $result = $healthCheck->checkAndRefresh($channel);

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $channel->fresh()->source_status,
                'message' => $result['message'] ?? 'Health check completed',
                'details' => $result['details'] ?? [],
                'restart' => $result['restart'] ?? null,
                'last_checked_at' => $channel->fresh()->source_last_checked_at,
                'check_attempts' => $channel->fresh()->source_check_attempts,
            ],
        ]);
    }

    /**
     * Manually refresh (restart) a channel's ingest.
     * Non-blocking — returns immediately, ingest restarts in background.
     */
    public function refreshSource(Channel $channel, SourceHealthCheckService $healthCheck): JsonResponse
    {
        $result = $healthCheck->manualRefresh($channel);

        $fresh = $channel->fresh();

        return response()->json([
            'success' => $result['success'] ?? true,
            'data' => [
                'status' => $fresh->source_status,
                'message' => $result['message'] ?? 'Refresh initiated',
                'last_checked_at' => $fresh->source_last_checked_at,
                'check_attempts' => $fresh->source_check_attempts,
            ],
        ]);
    }

    /**
     * Stop a channel's ingest process.
     */
    public function stopSource(Channel $channel, SourceHealthCheckService $healthCheck): JsonResponse
    {
        $stopped = $healthCheck->stopChannel($channel);

        return response()->json([
            'success' => true,
            'data' => [
                'stopped' => $stopped,
                'message' => $stopped ? 'Channel stopped successfully' : 'No active ingest found',
            ],
        ]);
    }

    /**
     * Manually switch a channel's active source to a specific index.
     * 0 = Primary, 1 = Backup 1, 2 = Backup 2
     */
    public function switchSource(Request $request, Channel $channel, SourceHealthCheckService $healthCheck): JsonResponse
    {
        $validated = $request->validate([
            'source_index' => 'required|integer|min:0|max:2',
        ]);

        $result = $healthCheck->switchSource($channel, $validated['source_index']);

        return response()->json([
            'success' => $result['success'],
            'data' => $result,
        ]);
    }
}
