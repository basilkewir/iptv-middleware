<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ContentCategory;
use App\Services\StreamingService\MulticastScanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MulticastScanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Channels/MulticastScan', [
            'categories' => ContentCategory::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function scan(Request $request, MulticastScanner $scanner): JsonResponse
    {
        $validated = $request->validate([
            'url'        => 'required|string',
            'local_addr' => 'nullable|string|max:45',
            'timeout'    => 'nullable|integer|min:5|max:60',
        ]);

        $url = trim($validated['url']);

        if (! str_starts_with($url, 'udp://') && ! str_starts_with($url, 'rtp://')) {
            return response()->json(['error' => 'Only udp:// or rtp:// URLs are supported.'], 422);
        }

        $programs = $scanner->scan($url, $validated['local_addr'] ?? null, (int) ($validated['timeout'] ?? 15));

        $result = array_map(fn ($p) => [
            'program_id'      => $p['program_id'],
            'name'            => $p['tags']['service_name'] ?? $p['tags']['service_provider'] ?? 'Program ' . $p['program_id'],
            'provider'        => $p['tags']['service_provider'] ?? null,
            'video_codec'     => $p['video']['codec'] ?? null,
            'video_height'    => $p['video']['height'] ?? null,
            'audio'           => $p['audio'] ?? null,
            'already_exists'  => Channel::where('stream_url', $url)->where('program_number', $p['program_id'])->exists(),
        ], $programs);

        return response()->json(['programs' => $result]);
    }

    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url'          => 'required|string',
            'local_addr'   => 'nullable|string|max:45',
            'category_id'  => 'nullable|exists:content_categories,id',
            'programs'     => 'required|array|min:1',
            'programs.*.program_id' => 'required|integer',
            'programs.*.name'       => 'required|string|max:255',
        ]);

        $url       = trim($validated['url']);
        $localAddr = $validated['local_addr'] ?? null;
        $nextNum   = (int) Channel::max('channel_number') + 1;
        $imported  = [];
        $skipped   = [];

        foreach ($validated['programs'] as $prog) {
            $programId = (int) $prog['program_id'];

            if (Channel::where('stream_url', $url)->where('program_number', $programId)->exists()) {
                $skipped[] = $prog['name'];
                continue;
            }

            $name    = trim($prog['name']) ?: 'Multicast Program ' . $programId;
            $slug    = $this->uniqueSlug($name);
            $channel = Channel::create([
                'name'           => $name,
                'slug'           => $slug,
                'channel_number' => $nextNum++,
                'stream_url'     => $url,
                'active_stream_url' => $url,
                'stream_type'    => 'udp',
                'program_number' => $programId,
                'local_address'  => $localAddr,
                'is_active'      => true,
            ]);

            if (! empty($validated['category_id'])) {
                $channel->categories()->sync([$validated['category_id']]);
            }

            $imported[] = $name;
        }

        return response()->json([
            'imported' => count($imported),
            'skipped'  => count($skipped),
            'names'    => $imported,
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i    = 2;
        while (Channel::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
