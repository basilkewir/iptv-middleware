<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Channel;
use App\Models\VOD;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    public function channels(Request $request)
    {
        $query = Channel::where('is_active', true);

        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        if ($request->filled('country')) {
            $query->where('country', $request->country);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('description', 'like', "%{$request->search}%");
            });
        }

        $channels = $query->orderBy('sort_order')->paginate(24);
        $categories = Category::where('type', 'channel')->get();

        return view('public.content.channels', compact('channels', 'categories'));
    }

    public function channelDetail(string $slug)
    {
        $channel = Channel::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $relatedChannels = Channel::where('category_id', $channel->category_id)
            ->where('id', '!=', $channel->id)
            ->where('is_active', true)
            ->limit(6)
            ->get();

        return view('public.content.channel-detail', compact('channel', 'relatedChannels'));
    }

    public function vods(Request $request)
    {
        $query = VOD::where('is_active', true);

        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', "%{$request->search}%")
                    ->orWhere('description', 'like', "%{$request->search}%")
                    ->orWhere('actors', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('sort')) {
            $query->orderBy(match ($request->sort) {
                'newest' => 'created_at',
                'oldest' => 'created_at',
                'rating' => 'rating',
                'title' => 'title',
                default => 'created_at',
            }, $request->sort === 'oldest' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc('created_at');
        }

        $vods = $query->paginate(24);
        $categories = Category::where('type', 'vod')->get();

        return view('public.content.vods', compact('vods', 'categories'));
    }

    public function vodDetail(string $slug)
    {
        $vod = VOD::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $relatedVods = VOD::where('category_id', $vod->category_id)
            ->where('id', '!=', $vod->id)
            ->where('is_active', true)
            ->limit(6)
            ->get();

        return view('public.content.vod-detail', compact('vod', 'relatedVods'));
    }

    public function category(string $slug)
    {
        $category = Category::where('slug', $slug)->firstOrFail();

        $channels = Channel::where('category_id', $category->id)
            ->where('is_active', true)
            ->paginate(24);

        return view('public.content.category', compact('category', 'channels'));
    }

    public function search(Request $request)
    {
        $query = $request->input('q', '');

        $channels = Channel::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->limit(12)
            ->get();

        $vods = VOD::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->orWhere('actors', 'like', "%{$query}%");
            })
            ->limit(12)
            ->get();

        return view('public.content.search', compact('channels', 'vods', 'query'));
    }
}
