<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\VODMedia;
use App\Services\QualityDetectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class QualityDetectionController extends Controller
{
    public function __construct(
        protected QualityDetectionService $qualityDetectionService
    ) {}

    public function index(): Response
    {
        $settings = DB::table('quality_detection_settings')->first();

        $channelCount = Channel::where('is_active', true)->count();
        $vodCount = VODMedia::where('is_available', true)->count();

        $channelsWithQuality = DB::table('channel_quality_cache')->count();
        $vodWithQuality = DB::table('vod_quality_cache')->count();

        return Inertia::render('Admin/Settings/QualityDetection', [
            'settings' => $settings,
            'stats' => [
                'total_channels' => $channelCount,
                'total_vod' => $vodCount,
                'channels_with_quality' => $channelsWithQuality,
                'vod_with_quality' => $vodWithQuality,
            ],
            'qualityLevels' => $this->qualityDetectionService->qualityLevels,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.detection_method' => 'nullable|in:resolution,bitrate,combined,ai',
            'settings.resolution_4k_min' => 'nullable|integer|min:0',
            'settings.resolution_fhd_min' => 'nullable|integer|min:0',
            'settings.resolution_hd_min' => 'nullable|integer|min:0',
            'settings.resolution_sd_min' => 'nullable|integer|min:0',
            'settings.bitrate_4k_min' => 'nullable|integer|min:0',
            'settings.bitrate_fhd_min' => 'nullable|integer|min:0',
            'settings.bitrate_hd_min' => 'nullable|integer|min:0',
            'settings.bitrate_sd_min' => 'nullable|integer|min:0',
            'settings.auto_scan_enabled' => 'nullable|boolean',
            'settings.scan_interval' => 'nullable|integer|min:0',
            'settings.max_concurrent_scans' => 'nullable|integer|min:1',
            'settings.scan_timeout' => 'nullable|integer|min:1',
            'settings.notify_on_change' => 'nullable|boolean',
            'settings.show_badge_channels' => 'nullable|boolean',
            'settings.show_badge_epg' => 'nullable|boolean',
            'settings.show_badge_player' => 'nullable|boolean',
            'settings.show_badge_channel_list' => 'nullable|boolean',
            'settings.badge_style' => 'nullable|in:classic,modern,minimal,fluent',
            'settings.auto_update_new' => 'nullable|boolean',
            'settings.auto_update_existing' => 'nullable|boolean',
            'settings.update_interval' => 'nullable|in:daily,weekly,monthly',
            'settings.vod_detection_enabled' => 'nullable|boolean',
            'settings.detect_file_metadata' => 'nullable|boolean',
            'settings.detect_stream_analysis' => 'nullable|boolean',
            'settings.detect_ffprobe' => 'nullable|boolean',
            'settings.detect_ai_based' => 'nullable|boolean',
            'settings.detect_new_uploads' => 'nullable|boolean',
            'settings.detect_existing_files' => 'nullable|boolean',
            'settings.detect_series' => 'nullable|boolean',
            'settings.detect_imported' => 'nullable|boolean',
            'settings.detect_multi_quality' => 'nullable|boolean',
            'settings.auto_select_best' => 'nullable|boolean',
            'settings.allow_manual_override' => 'nullable|boolean',
            'settings.transcode_lower_qualities' => 'nullable|boolean',
            'settings.show_vod_badge_thumbnail' => 'nullable|boolean',
            'settings.show_vod_badge_details' => 'nullable|boolean',
            'settings.show_vod_badge_player' => 'nullable|boolean',
            'settings.show_vod_quality_options' => 'nullable|boolean',
            'settings.auto_select_best_device' => 'nullable|boolean',
            'settings.vod_badge_position' => 'nullable|in:top-left,top-right,bottom-left,bottom-right',
        ]);

        DB::table('quality_detection_settings')
            ->where('id', 1)
            ->update(array_map(
                fn ($value) => is_bool($value) ? $value : $value,
                $validated['settings']
            ));

        return back()->with('success', 'Quality detection settings updated successfully.');
    }

    public function scanChannel(Channel $channel): JsonResponse
    {
        $result = $this->qualityDetectionService->detectChannelQuality($channel);

        return response()->json($result);
    }

    public function scanVOD(VODMedia $vodMedia): JsonResponse
    {
        $result = $this->qualityDetectionService->detectVODQuality($vodMedia);

        return response()->json($result);
    }

    public function scanAllChannels(): JsonResponse
    {
        $result = $this->qualityDetectionService->scanAllChannels();

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function scanAllVOD(): JsonResponse
    {
        $result = $this->qualityDetectionService->scanAllVOD();

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function getStats(): JsonResponse
    {
        $stats = $this->qualityDetectionService->getStats();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
