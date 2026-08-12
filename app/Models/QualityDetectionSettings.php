<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QualityDetectionSettings extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'detection_method', 'resolution_4k_min', 'resolution_fhd_min', 'resolution_hd_min', 'resolution_sd_min',
        'bitrate_4k_min', 'bitrate_fhd_min', 'bitrate_hd_min', 'bitrate_sd_min',
        'auto_scan_enabled', 'scan_interval', 'max_concurrent_scans', 'scan_timeout', 'notify_on_change',
        'show_badge_channels', 'show_badge_epg', 'show_badge_player', 'show_badge_channel_list', 'badge_style',
        'auto_update_new', 'auto_update_existing', 'update_interval',
        'vod_detection_enabled', 'detect_file_metadata', 'detect_stream_analysis', 'detect_ffprobe', 'detect_ai_based',
        'detect_new_uploads', 'detect_existing_files', 'detect_series', 'detect_imported',
        'detect_multi_quality', 'auto_select_best', 'allow_manual_override', 'transcode_lower_qualities',
        'show_vod_badge_thumbnail', 'show_vod_badge_details', 'show_vod_badge_player', 'show_vod_quality_options',
        'auto_select_best_device', 'vod_badge_position',
    ];

    protected $casts = [

            'auto_scan_enabled' => 'boolean',
            'notify_on_change' => 'boolean',
            'show_badge_channels' => 'boolean',
            'show_badge_epg' => 'boolean',
            'show_badge_player' => 'boolean',
            'show_badge_channel_list' => 'boolean',
            'auto_update_new' => 'boolean',
            'auto_update_existing' => 'boolean',
            'vod_detection_enabled' => 'boolean',
            'detect_file_metadata' => 'boolean',
            'detect_stream_analysis' => 'boolean',
            'detect_ffprobe' => 'boolean',
            'detect_ai_based' => 'boolean',
            'detect_new_uploads' => 'boolean',
            'detect_existing_files' => 'boolean',
            'detect_series' => 'boolean',
            'detect_imported' => 'boolean',
            'detect_multi_quality' => 'boolean',
            'auto_select_best' => 'boolean',
            'allow_manual_override' => 'boolean',
            'transcode_lower_qualities' => 'boolean',
            'show_vod_badge_thumbnail' => 'boolean',
            'show_vod_badge_details' => 'boolean',
            'show_vod_badge_player' => 'boolean',
            'show_vod_quality_options' => 'boolean',
            'auto_select_best_device' => 'boolean',
            'resolution_4k_min' => 'integer',
            'resolution_fhd_min' => 'integer',
            'resolution_hd_min' => 'integer',
            'resolution_sd_min' => 'integer',
            'bitrate_4k_min' => 'integer',
            'bitrate_fhd_min' => 'integer',
            'bitrate_hd_min' => 'integer',
            'bitrate_sd_min' => 'integer',
            'scan_interval' => 'integer',
            'max_concurrent_scans' => 'integer',
            'scan_timeout' => 'integer',
        
    ];

    public static function instance(): static
    {
        return static::firstOrCreate([]);
    }
}
