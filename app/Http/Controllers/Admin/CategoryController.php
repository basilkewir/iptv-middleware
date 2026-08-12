<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ContentCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $query = ContentCategory::with('parent');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('is_active', $request->boolean('status'));
        }

        if ($type = $request->input('type')) {
            $query->where('category_type', $type);
        }

        if ($request->boolean('top_level')) {
            $query->whereNull('parent_id');
        }

        $categories = $query->orderBy('sort_order')->orderBy('name')->paginate($request->input('per_page', 20));

        if ($request->expectsJson()) {
            return response()->json(['data' => $categories]);
        }

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Categories/Create', [
            'parentCategories' => ContentCategory::whereNull('parent_id')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:content_categories,name',
            'slug' => 'nullable|string|max:255|unique:content_categories,slug',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'banner_image' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:7',
            'parent_id' => 'nullable|exists:content_categories,id',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'category_type' => 'required|in:live,vod,series',
            'auto_assign_channels' => 'nullable|boolean',
            'auto_assign_vod' => 'nullable|boolean',
            'include_in_m3u' => 'nullable|boolean',
            'include_in_xmltv' => 'nullable|boolean',
        ]);

        $validated['slug'] = $validated['slug'] ?: Str::slug($validated['name']);

        $category = ContentCategory::create($validated);

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function show(Request $request, ContentCategory $category): Response|JsonResponse
    {
        $category->load(['parent', 'children', 'channels', 'vodContent']);

        if ($request->expectsJson()) {
            return response()->json(['data' => $category]);
        }

        return Inertia::render('Admin/Categories/Edit', [
            'category' => $category,
            'parentCategories' => ContentCategory::whereNull('parent_id')
                ->where('id', '!=', $category->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function update(Request $request, ContentCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:content_categories,name,' . $category->id,
            'slug' => 'nullable|string|max:255|unique:content_categories,slug,' . $category->id,
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'banner_image' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:7',
            'parent_id' => 'nullable|exists:content_categories,id',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'category_type' => 'sometimes|in:live,vod,series',
            'auto_assign_channels' => 'nullable|boolean',
            'auto_assign_vod' => 'nullable|boolean',
            'include_in_m3u' => 'nullable|boolean',
            'include_in_xmltv' => 'nullable|boolean',
        ]);

        if (isset($validated['name']) && empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category->update($validated);

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(Request $request, ContentCategory $category): RedirectResponse
    {
        $category->channels()->detach();
        $category->vodContent()->detach();
        $category->delete();

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category deleted successfully.');
    }

    public function toggleStatus(Request $request, ContentCategory $category): JsonResponse
    {
        $category->is_active = !$category->is_active;
        $category->save();

        return response()->json([
            'message' => 'Category status updated successfully.',
            'data' => ['is_active' => $category->is_active],
        ]);
    }

    public function assignChannels(Request $request, ContentCategory $category): JsonResponse
    {
        $validated = $request->validate([
            'channel_ids' => 'required|array',
            'channel_ids.*' => 'exists:channels,id',
        ]);

        $category->channels()->syncWithoutDetaching($validated['channel_ids']);

        return response()->json([
            'message' => 'Channels assigned to category successfully.',
        ]);
    }

    public function removeChannels(Request $request, ContentCategory $category): JsonResponse
    {
        $validated = $request->validate([
            'channel_ids' => 'required|array',
            'channel_ids.*' => 'exists:channels,id',
        ]);

        $category->channels()->detach($validated['channel_ids']);

        return response()->json([
            'message' => 'Channels removed from category successfully.',
        ]);
    }

    public function channelAssignment(Request $request, ContentCategory $category): Response
    {
        $category->load('channels');

        $query = Channel::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('stream_url', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('is_active', $request->boolean('status'));
        }

        $channels = $query->orderBy('name')->paginate($request->input('per_page', 20));

        return Inertia::render('Admin/Categories/ChannelAssignment', [
            'category' => $category,
            'channels' => $channels,
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'required|exists:content_categories,id',
        ]);

        foreach ($validated['order'] as $index => $id) {
            ContentCategory::where('id', $id)->update(['sort_order' => $index]);
        }

        return response()->json([
            'message' => 'Categories reordered successfully.',
        ]);
    }
}
