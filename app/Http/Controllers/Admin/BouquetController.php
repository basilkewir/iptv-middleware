<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bouquet;
use App\Models\Channel;
use App\Models\ContentCategory;
use App\Models\SubscriptionPackage;
use App\Models\VODContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BouquetController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $query = Bouquet::with(['parent', 'category', 'package'])->withCount('channels');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('is_active', $request->boolean('status'));
        }

        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->input('parent_id'));
        }

        $bouquets = $query->orderBy('sort_order')->latest()->paginate($request->input('per_page', 15));

        if ($request->expectsJson()) {
            return response()->json(['data' => $bouquets]);
        }

        return Inertia::render('Admin/Bouquets/Index', [
            'bouquets' => $bouquets,
            'categories' => ContentCategory::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function create(): Response
    {
        $parentBouquets = Bouquet::whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $channels = Channel::with('categories')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $vodContent = VODContent::with('categories')
            ->where('is_active', true)
            ->orderBy('title')
            ->get();

        $packages = SubscriptionPackage::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Admin/Bouquets/Create', [
            'parentBouquets' => $parentBouquets,
            'channels' => $channels,
            'vodContent' => $vodContent,
            'packages' => $packages,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:bouquets,name',
            'description' => 'nullable|string',
            'icon' => 'nullable|image|mimes:png,jpg,jpeg,svg|max:5120',
            'icon_url' => 'nullable|string',
            'parent_id' => 'nullable|exists:bouquets,id',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'channel_ids' => 'nullable|array',
            'channel_ids.*' => 'exists:channels,id',
            'vod_ids' => 'nullable|array',
            'vod_ids.*' => 'exists:vod_content,id',
            'package_ids' => 'nullable|array',
            'package_ids.*' => 'exists:subscription_packages,id',
        ]);

        $iconUrl = null;
        if ($request->hasFile('icon')) {
            $path = $request->file('icon')->store('bouquets', 'public');
            $iconUrl = Storage::url($path);
        } elseif (!empty($validated['icon_url'])) {
            $iconUrl = $validated['icon_url'];
        }

        $bouquet = Bouquet::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'icon_url' => $iconUrl,
            'parent_id' => $validated['parent_id'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (!empty($validated['channel_ids'])) {
            $channelData = [];
            foreach ($validated['channel_ids'] as $index => $channelId) {
                $channelData[$channelId] = ['sort_order' => $index + 1];
            }
            $bouquet->channels()->sync($channelData);
        }

        if (!empty($validated['vod_ids'])) {
            $vodData = [];
            foreach ($validated['vod_ids'] as $index => $vodId) {
                $vodData[$vodId] = ['sort_order' => $index + 1];
            }
            $bouquet->vodContent()->sync($vodData);
        }

        if (!empty($validated['package_ids'])) {
            $bouquet->packages()->sync($validated['package_ids']);
        }

        return redirect()->route('admin.bouquets.index')
            ->with('success', 'Bouquet created successfully.');
    }

    public function show(Request $request, Bouquet $bouquet): Response|JsonResponse
    {
        $bouquet = Bouquet::with([
            'channels' => function ($q) {
                $q->orderBy('bouquet_channels.sort_order');
            },
            'channels.categories',
            'packages',
            'parent',
        ])->findOrFail($bouquet->id);

        $allChannels = Channel::with('categories')->orderBy('name')->get();
        $categories = ContentCategory::where('is_active', true)->orderBy('sort_order')->get();

        if ($request->expectsJson()) {
            return response()->json([
                'bouquet' => $bouquet,
                'allChannels' => $allChannels,
                'categories' => $categories,
            ]);
        }

        return Inertia::render('Admin/Bouquets/ManageChannels', [
            'bouquet' => $bouquet,
            'allChannels' => $allChannels,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, Bouquet $bouquet): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:bouquets,name,' . $bouquet->id,
            'description' => 'nullable|string',
            'icon' => 'nullable|image|mimes:png,jpg,jpeg,svg|max:5120',
            'icon_url' => 'nullable|string',
            'parent_id' => 'nullable|exists:bouquets,id',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'channel_ids' => 'nullable|array',
            'channel_ids.*' => 'exists:channels,id',
            'package_ids' => 'nullable|array',
            'package_ids.*' => 'exists:subscription_packages,id',
        ]);

        if ($request->hasFile('icon')) {
            if ($bouquet->icon_url && Storage::disk('public')->exists(str_replace('/storage/', '', $bouquet->icon_url))) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $bouquet->icon_url));
            }
            $path = $request->file('icon')->store('bouquets', 'public');
            $validated['icon_url'] = Storage::url($path);
        }

        if (isset($validated['name']) && empty($bouquet->slug)) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $bouquet->update($validated);

        if (isset($validated['channel_ids'])) {
            $channelData = [];
            foreach ($validated['channel_ids'] as $index => $channelId) {
                $channelData[$channelId] = ['sort_order' => $index + 1];
            }
            $bouquet->channels()->sync($channelData);
        }

        if (isset($validated['package_ids'])) {
            $bouquet->packages()->sync($validated['package_ids']);
        }

        return redirect()->route('admin.bouquets.index')
            ->with('success', 'Bouquet updated successfully.');
    }

    public function destroy(Request $request, Bouquet $bouquet): JsonResponse|RedirectResponse
    {
        if ($bouquet->children()->exists()) {
            return response()->json(['message' => 'Cannot delete bouquet with child bouquets.'], 422);
        }

        $bouquet->channels()->detach();
        $bouquet->packages()->detach();
        $bouquet->users()->detach();
        $bouquet->vodContent()->detach();
        $bouquet->delete();

        return redirect()->route('admin.bouquets.index')
            ->with('success', 'Bouquet deleted successfully.');
    }

    public function toggleStatus(Request $request, Bouquet $bouquet): JsonResponse
    {
        $bouquet->is_active = !$bouquet->is_active;
        $bouquet->save();

        return response()->json([
            'message' => 'Bouquet status updated successfully.',
            'data' => ['is_active' => $bouquet->is_active],
        ]);
    }

    public function addChannels(Request $request, Bouquet $bouquet): JsonResponse
    {
        $validated = $request->validate([
            'channel_ids' => 'required|array',
            'channel_ids.*' => 'exists:channels,id',
        ]);

        $maxOrder = $bouquet->channels()->max('sort_order') ?? 0;

        foreach ($validated['channel_ids'] as $index => $channelId) {
            $bouquet->channels()->syncWithoutDetaching([
                $channelId => ['sort_order' => $maxOrder + $index + 1],
            ]);
        }

        return response()->json([
            'message' => count($validated['channel_ids']) . ' channel(s) added to bouquet successfully.',
        ]);
    }

    public function removeChannel(Request $request, Bouquet $bouquet, Channel $channel): JsonResponse
    {
        $bouquet->channels()->detach($channel->id);

        $bouquet->channels()->each(function ($ch, $index) {
            $bouquet->channels()->updateExistingPivot($ch->id, ['sort_order' => $index + 1]);
        });

        return response()->json([
            'message' => 'Channel removed from bouquet successfully.',
        ]);
    }

    public function updateChannelOrder(Request $request, Bouquet $bouquet): JsonResponse
    {
        $validated = $request->validate([
            'channel_ids' => 'required|array',
            'channel_ids.*' => 'exists:channels,id',
        ]);

        foreach ($validated['channel_ids'] as $index => $channelId) {
            $bouquet->channels()->updateExistingPivot($channelId, ['sort_order' => $index + 1]);
        }

        return response()->json([
            'message' => 'Channel order updated successfully.',
        ]);
    }

    public function deleteAllChannels(Request $request, Bouquet $bouquet): JsonResponse
    {
        $bouquet->channels()->detach();

        return response()->json([
            'message' => 'All channels removed from bouquet successfully.',
        ]);
    }

    public function cloneBouquet(Request $request, Bouquet $bouquet): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:bouquets,name',
        ]);

        $clone = $bouquet->replicate(['slug', 'icon_url']);
        $clone->name = $validated['name'];
        $clone->slug = Str::slug($validated['name']);
        $clone->is_active = false;
        $clone->sort_order = ($bouquet->sort_order + 1);
        $clone->save();

        $channels = $bouquet->channels()->withPivot('sort_order')->get();
        $channelData = [];
        foreach ($channels as $channel) {
            $channelData[$channel->id] = ['sort_order' => $channel->pivot->sort_order];
        }
        $clone->channels()->sync($channelData);

        $packageIds = $bouquet->packages()->pluck('subscription_packages.id')->toArray();
        if (!empty($packageIds)) {
            $clone->packages()->sync($packageIds);
        }

        return response()->json([
            'message' => 'Bouquet cloned successfully.',
            'data' => ['bouquet' => $clone],
        ]);
    }

    public function export(Request $request, Bouquet $bouquet)
    {
        $format = $request->input('format', 'm3u');
        $bouquet->load(['channels' => function ($q) {
            $q->orderBy('bouquet_channels.sort_order');
        }, 'channels.categories']);

        $channels = $bouquet->channels;
        $filename = Str::slug($bouquet->name) . '-bouquet-' . now()->format('Y-m-d') . '.' . $format;

        switch ($format) {
            case 'm3u':
                return $this->exportM3U($channels, $filename);
            case 'csv':
                return $this->exportCSV($channels, $filename);
            case 'json':
                return $this->exportJSON($channels, $filename);
            default:
                return response()->json(['message' => 'Unsupported format.'], 400);
        }
    }

    protected function exportM3U($channels, $filename)
    {
        $content = "#EXTM3U\n";

        foreach ($channels as $channel) {
            $category = $channel->categories->first()?->name ?? 'Uncategorized';
            $logo = $channel->logo_url ?? '';
            $tvgId = $channel->epg_id ?? $channel->id;

            $content .= "#EXTINF:-1 tvg-id=\"{$tvgId}\" tvg-name=\"{$channel->name}\" tvg-logo=\"{$logo}\" group-title=\"{$category}\",{$channel->name}\n";
            $content .= ($channel->stream_url ?? '#') . "\n";
        }

        return response($content, 200, [
            'Content-Type' => 'audio/x-mpegurl',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    protected function exportCSV($channels, $filename)
    {
        $handle = fopen('php://memory', 'r+');
        fputcsv($handle, ['#', 'Channel Name', 'Category', 'Stream URL', 'Logo URL', 'EPG ID']);

        foreach ($channels as $index => $channel) {
            $category = $channel->categories->first()?->name ?? 'Uncategorized';
            fputcsv($handle, [
                $index + 1,
                $channel->name,
                $category,
                $channel->stream_url ?? '',
                $channel->logo_url ?? '',
                $channel->epg_id ?? '',
            ]);
        }

        fseek($handle, 0);
        $content = stream_get_contents($handle);
        fclose($handle);

        return response($content, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    protected function exportJSON($channels, $filename)
    {
        $data = [];

        foreach ($channels as $index => $channel) {
            $category = $channel->categories->first()?->name ?? 'Uncategorized';
            $data[] = [
                'sort_order' => $index + 1,
                'name' => $channel->name,
                'category' => $category,
                'stream_url' => $channel->stream_url ?? '',
                'logo_url' => $channel->logo_url ?? '',
                'epg_id' => $channel->epg_id ?? '',
                'description' => $channel->description ?? '',
            ];
        }

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function import(Request $request, Bouquet $bouquet): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:m3u,csv,json,txt|max:10240',
        ]);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $content = file_get_contents($file->getRealPath());

        $imported = 0;
        $maxOrder = $bouquet->channels()->max('sort_order') ?? 0;

        switch ($extension) {
            case 'm3u':
            case 'txt':
                $imported = $this->importM3U($content, $bouquet, $maxOrder);
                break;
            case 'csv':
                $imported = $this->importCSV($content, $bouquet, $maxOrder);
                break;
            case 'json':
                $imported = $this->importJSON($content, $bouquet, $maxOrder);
                break;
        }

        return response()->json([
            'message' => $imported . ' channel(s) imported into bouquet successfully.',
            'data' => ['imported' => $imported],
        ]);
    }

    protected function importM3U(string $content, Bouquet $bouquet, int &$maxOrder): int
    {
        $lines = explode("\n", $content);
        $imported = 0;
        $currentName = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if (str_starts_with($line, '#EXTINF:')) {
                $parts = explode(',', $line);
                if (count($parts) >= 2) {
                    $currentName = trim(end($parts));
                }
            } elseif (!str_starts_with($line, '#') && !empty($line) && $currentName) {
                $channel = Channel::where('stream_url', $line)
                    ->orWhere('name', $currentName)
                    ->first();

                if ($channel) {
                    $maxOrder++;
                    $bouquet->channels()->syncWithoutDetaching([
                        $channel->id => ['sort_order' => $maxOrder],
                    ]);
                    $imported++;
                }

                $currentName = null;
            }
        }

        return $imported;
    }

    protected function importCSV(string $content, Bouquet $bouquet, int &$maxOrder): int
    {
        $rows = str_getcsv($content, "\n");
        $imported = 0;
        $header = null;

        foreach ($rows as $row) {
            $row = str_getcsv($row);

            if (!$header) {
                $header = $row;
                continue;
            }

            if (count($row) < count($header)) {
                continue;
            }

            $data = array_combine($header, $row);
            $name = $data['Channel Name'] ?? $data['name'] ?? '';
            $streamUrl = $data['Stream URL'] ?? $data['stream_url'] ?? '';

            if (empty($name) && empty($streamUrl)) {
                continue;
            }

            $channel = Channel::where('name', $name)
                ->orWhere('stream_url', $streamUrl)
                ->first();

            if ($channel) {
                $maxOrder++;
                $bouquet->channels()->syncWithoutDetaching([
                    $channel->id => ['sort_order' => $maxOrder],
                ]);
                $imported++;
            }
        }

        return $imported;
    }

    protected function importJSON(string $content, Bouquet $bouquet, int &$maxOrder): int
    {
        $data = json_decode($content, true);

        if (!is_array($data)) {
            return 0;
        }

        $imported = 0;

        foreach ($data as $item) {
            $name = $item['name'] ?? $item['channel_name'] ?? '';
            $streamUrl = $item['stream_url'] ?? $item['url'] ?? '';

            if (empty($name) && empty($streamUrl)) {
                continue;
            }

            $channel = Channel::where('name', $name)
                ->orWhere('stream_url', $streamUrl)
                ->first();

            if ($channel) {
                $maxOrder++;
                $bouquet->channels()->syncWithoutDetaching([
                    $channel->id => ['sort_order' => $maxOrder],
                ]);
                $imported++;
            }
        }

        return $imported;
    }
}
