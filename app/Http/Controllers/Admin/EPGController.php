<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\EPGChannelMapping;
use App\Models\EPGProgram;
use App\Models\EPGSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class EpgController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $sources = EPGSource::withCount('programs')->latest()->get();

        if ($request->expectsJson()) {
            return response()->json(['data' => $sources]);
        }

        return Inertia::render('Admin/EPG/Index', ['sources' => $sources]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/EPG/Create', [
            'channels' => Channel::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url',
            'type' => 'required|in:xmltv,json,custom',
            'language' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:50',
            'update_interval' => 'nullable|integer|min:3600',
            'auto_mapping' => 'nullable|boolean',
            'mapping_strategy' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'channel_mappings' => 'nullable|array',
            'channel_mappings.*.channel_id' => 'required_with:channel_mappings|exists:channels,id',
            'channel_mappings.*.epg_channel_id' => 'required_with:channel_mappings|string',
            'channel_mappings.*.epg_channel_name' => 'nullable|string',
        ]);

        $source = EPGSource::create([
            'name' => $validated['name'],
            'url' => $validated['url'],
            'type' => $validated['type'],
            'language' => $validated['language'] ?? null,
            'timezone' => $validated['timezone'] ?? 'UTC',
            'update_interval' => $validated['update_interval'] ?? 21600,
            'auto_mapping' => $validated['auto_mapping'] ?? false,
            'mapping_strategy' => $validated['mapping_strategy'] ?? 'name',
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (!empty($validated['channel_mappings'])) {
            foreach ($validated['channel_mappings'] as $mapping) {
                EPGChannelMapping::create([
                    'epg_source_id' => $source->id,
                    'channel_id' => $mapping['channel_id'],
                    'epg_channel_id' => $mapping['epg_channel_id'],
                    'epg_channel_name' => $mapping['epg_channel_name'] ?? null,
                    'is_auto_matched' => false,
                ]);
            }
        }

        return redirect()->route('admin.epg.index')
            ->with('success', 'EPG source created successfully.');
    }

    public function show(Request $request, EPGSource $epgSource): Response|JsonResponse
    {
        $epgSource->load(['programs', 'channelMappings.channel', 'channelMappings.epgSource']);

        if ($request->expectsJson()) {
            return response()->json(['data' => $epgSource]);
        }

        return Inertia::render('Admin/EPG/Edit', [
            'source' => $epgSource,
            'channels' => Channel::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, EPGSource $epgSource): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'url' => 'sometimes|url',
            'type' => 'sometimes|in:xmltv,json,custom',
            'language' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:50',
            'update_interval' => 'sometimes|integer|min:3600',
            'auto_mapping' => 'nullable|boolean',
            'mapping_strategy' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'channel_mappings' => 'nullable|array',
            'channel_mappings.*.channel_id' => 'required_with:channel_mappings|exists:channels,id',
            'channel_mappings.*.epg_channel_id' => 'required_with:channel_mappings|string',
            'channel_mappings.*.epg_channel_name' => 'nullable|string',
        ]);

        if (isset($validated['channel_mappings'])) {
            EPGChannelMapping::where('epg_source_id', $epgSource->id)->delete();
            foreach ($validated['channel_mappings'] as $mapping) {
                EPGChannelMapping::create([
                    'epg_source_id' => $epgSource->id,
                    'channel_id' => $mapping['channel_id'],
                    'epg_channel_id' => $mapping['epg_channel_id'],
                    'epg_channel_name' => $mapping['epg_channel_name'] ?? null,
                    'is_auto_matched' => false,
                ]);
            }
            unset($validated['channel_mappings']);
        }

        $epgSource->update($validated);

        return redirect()->route('admin.epg.index')
            ->with('success', 'EPG source updated successfully.');
    }

    public function destroy(Request $request, EPGSource $epgSource): RedirectResponse
    {
        $epgSource->delete();

        return redirect()->route('admin.epg.index')
            ->with('success', 'EPG source deleted successfully.');
    }

    public function updateNow(Request $request, EPGSource $epgSource): JsonResponse
    {
        $epgSource->update(['last_fetched_at' => now()]);

        return response()->json([
            'message' => 'EPG update triggered successfully.',
            'data' => ['last_fetched_at' => $epgSource->fresh()->last_fetched_at],
        ]);
    }

    public function preview(Request $request, EPGSource $epgSource): JsonResponse
    {
        try {
            $response = Http::timeout(10)->get($epgSource->url);

            if ($response->successful()) {
                $content = $response->body();

                if ($epgSource->type === 'xmltv') {
                    $xml = simplexml_load_string($content);
                    $data = json_decode(json_encode($xml), true);
                } else {
                    $data = json_decode($content, true);
                }

                return response()->json([
                    'message' => 'EPG preview fetched successfully.',
                    'data' => ['preview' => $data],
                ]);
            }

            return response()->json([
                'message' => 'Failed to fetch EPG data.',
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error fetching EPG data: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function programs(Request $request): Response
    {
        $query = EPGProgram::with(['channel', 'epgSource']);

        if ($channelId = $request->input('channel_id')) {
            $query->where('channel_id', $channelId);
        }

        if ($date = $request->input('date')) {
            $query->whereDate('start_time', $date);
        } elseif ($startDate = $request->input('start_date')) {
            $query->where('start_time', '>=', $startDate);
            if ($endDate = $request->input('end_date')) {
                $query->where('end_time', '<=', $endDate);
            }
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $programs = $query->orderBy('start_time', 'desc')->paginate($request->input('per_page', 50));

        $stats = [
            'total_programs' => EPGProgram::count(),
            'missing_data' => EPGProgram::whereNull('description')->orWhere('description', '')->count(),
            'last_updated' => EPGProgram::latest('updated_at')->value('updated_at'),
        ];

        return Inertia::render('Admin/EPG/Programs', [
            'programs' => $programs,
            'channels' => Channel::where('is_active', true)->orderBy('name')->get(),
            'stats' => $stats,
        ]);
    }

    public function storeProgram(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel_id' => 'required|exists:channels,id',
            'epg_source_id' => 'nullable|exists:epg_sources,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'program_id' => 'nullable|string',
            'language' => 'nullable|string|max:10',
            'rating' => 'nullable|string|max:10',
            'category' => 'nullable|string|max:255',
            'season' => 'nullable|integer',
            'episode' => 'nullable|integer',
            'episode_title' => 'nullable|string|max:255',
        ]);

        $program = EPGProgram::create($validated);

        return response()->json([
            'message' => 'Program created successfully.',
            'data' => $program,
        ]);
    }

    public function updateProgram(Request $request, EPGProgram $program): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date',
            'program_id' => 'nullable|string',
            'language' => 'nullable|string|max:10',
            'rating' => 'nullable|string|max:10',
            'category' => 'nullable|string|max:255',
            'season' => 'nullable|integer',
            'episode' => 'nullable|integer',
            'episode_title' => 'nullable|string|max:255',
        ]);

        $program->update($validated);

        return response()->json([
            'message' => 'Program updated successfully.',
            'data' => $program,
        ]);
    }

    public function destroyProgram(Request $request, EPGProgram $program): JsonResponse
    {
        $program->delete();

        return response()->json([
            'message' => 'Program deleted successfully.',
        ]);
    }

    public function clearExpired(Request $request): RedirectResponse
    {
        $deleted = EPGProgram::where('end_time', '<', now())->delete();

        return back()->with('success', "Cleared {$deleted} expired EPG programs.");
    }

    public function exportPrograms(Request $request): Response
    {
        $programs = EPGProgram::with('epgChannel')
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->when($request->channel_id, fn ($q, $id) => $q->where('epg_channel_id', $id))
            ->when($request->date, fn ($q, $d) => $q->whereDate('start_time', $d))
            ->get();

        $csv = "Title,Channel,Start,End,Description\n";
        foreach ($programs as $p) {
            $csv .= implode(',', [
                '"' . str_replace('"', '""', $p->title) . '"',
                '"' . ($p->epgChannel->name ?? 'N/A') . '"',
                $p->start_time,
                $p->end_time,
                '"' . str_replace('"', '""', $p->description ?? '') . '"',
            ]) . "\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="epg-programs.csv"',
        ]);
    }

    public function triggerUpdate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_id' => 'required|exists:epg_sources,id',
        ]);

        $source = EPGSource::findOrFail($validated['source_id']);

        return back()->with('success', "EPG update triggered for {$source->name}.");
    }
}
