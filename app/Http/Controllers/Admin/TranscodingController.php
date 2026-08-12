<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\TranscodingFilter;
use App\Models\TranscodingJob;
use App\Models\TranscodingProfile;
use App\Models\VODContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TranscodingController extends Controller
{
    public function index(Request $request): Response
    {
        $profiles = TranscodingProfile::withCount('jobs')->orderBy('sort_order')->get();

        return Inertia::render('Admin/Transcoding/Index', [
            'profiles' => $profiles,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Transcoding/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'profile_type' => 'nullable|string',
            'resolution' => 'nullable|string',
            'video_codec' => 'nullable|string',
            'bitrate' => 'nullable|integer',
            'frame_rate' => 'nullable|integer',
            'pixel_format' => 'nullable|string',
            'profile' => 'nullable|string',
            'level' => 'nullable|string',
            'preset' => 'nullable|string',
            'tune' => 'nullable|string',
            'crf' => 'nullable|integer|min:18|max:28',
            'audio_codec' => 'nullable|string',
            'audio_bitrate' => 'nullable|integer',
            'sample_rate' => 'nullable|integer',
            'channels' => 'nullable|integer',
            'gpu_acceleration' => 'nullable|boolean',
            'gpu_type' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        TranscodingProfile::create($validated);

        return redirect()->route('admin.transcoding.index')
            ->with('success', 'Transcoding profile created successfully.');
    }

    public function show(TranscodingProfile $profile): Response
    {
        $profile->load('jobs');

        return Inertia::render('Admin/Transcoding/Edit', [
            'profile' => $profile,
        ]);
    }

    public function update(Request $request, TranscodingProfile $profile): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'profile_type' => 'nullable|string',
            'resolution' => 'nullable|string',
            'video_codec' => 'nullable|string',
            'bitrate' => 'nullable|integer',
            'frame_rate' => 'nullable|integer',
            'pixel_format' => 'nullable|string',
            'profile' => 'nullable|string',
            'level' => 'nullable|string',
            'preset' => 'nullable|string',
            'tune' => 'nullable|string',
            'crf' => 'nullable|integer|min:18|max:28',
            'audio_codec' => 'nullable|string',
            'audio_bitrate' => 'nullable|integer',
            'sample_rate' => 'nullable|integer',
            'channels' => 'nullable|integer',
            'gpu_acceleration' => 'nullable|boolean',
            'gpu_type' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $profile->update($validated);

        return redirect()->route('admin.transcoding.index')
            ->with('success', 'Transcoding profile updated successfully.');
    }

    public function destroy(TranscodingProfile $profile): RedirectResponse
    {
        $profile->delete();

        return redirect()->route('admin.transcoding.index')
            ->with('success', 'Transcoding profile deleted successfully.');
    }

    public function jobs(Request $request): Response
    {
        $query = TranscodingJob::with(['profile', 'channel', 'vodContent']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($channelId = $request->input('channel_id')) {
            $query->where('channel_id', $channelId);
        }

        $jobs = $query->latest()->paginate($request->input('per_page', 20));

        $stats = [
            'running' => TranscodingJob::where('status', 'processing')->count(),
            'waiting' => TranscodingJob::where('status', 'pending')->count(),
            'completed' => TranscodingJob::where('status', 'completed')->count(),
            'failed' => TranscodingJob::where('status', 'failed')->count(),
        ];

        return Inertia::render('Admin/Transcoding/Jobs', [
            'jobs' => $jobs,
            'channels' => Channel::where('is_active', true)->orderBy('name')->get(),
            'stats' => $stats,
        ]);
    }

    public function createJob(): Response
    {
        return Inertia::render('Admin/Transcoding/CreateJob', [
            'profiles' => TranscodingProfile::where('is_active', true)->get(),
            'channels' => Channel::where('is_active', true)->orderBy('name')->get(),
            'vodItems' => VODContent::where('is_active', true)->orderBy('title')->get(),
        ]);
    }

    public function storeJob(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'profile_id' => 'required|exists:transcoding_profiles,id',
            'channel_id' => 'nullable|exists:channels,id',
            'vod_content_id' => 'nullable|exists:vod_content,id',
            'job_type' => 'nullable|in:live,vod,series',
            'input_url' => 'required|url',
            'priority' => 'nullable|integer',
        ]);

        TranscodingJob::create($validated);

        return redirect()->route('admin.transcoding.jobs')
            ->with('success', 'Transcoding job created successfully.');
    }

    public function pauseJob(TranscodingJob $job): JsonResponse
    {
        $job->update(['status' => 'paused']);
        return response()->json(['message' => 'Job paused.']);
    }

    public function resumeJob(TranscodingJob $job): JsonResponse
    {
        $job->update(['status' => 'pending']);
        return response()->json(['message' => 'Job resumed.']);
    }

    public function cancelJob(TranscodingJob $job): JsonResponse
    {
        $job->update(['status' => 'cancelled']);
        return response()->json(['message' => 'Job cancelled.']);
    }

    public function clearCompleted(): JsonResponse
    {
        TranscodingJob::where('status', 'completed')->delete();
        return response()->json(['message' => 'Completed jobs cleared.']);
    }
}
