<?php

namespace App\Models\Channel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelOverlay extends Model
{
    use HasFactory;

    protected $table = 'channel_overlays';

    protected $fillable = [
        'channel_id',
        'overlay_type',
        'overlay_name',
        'ticker_text',
        'ticker_speed',
        'ticker_direction',
        'ticker_font_size',
        'ticker_font_color',
        'ticker_background_color',
        'ticker_opacity',
        'logo_url',
        'logo_position',
        'logo_size',
        'logo_opacity',
        'logo_margin_x',
        'logo_margin_y',
        'clock_format',
        'clock_timezone',
        'clock_font_size',
        'clock_font_color',
        'clock_background_color',
        'clock_position',
        'display_duration',
        'start_delay',
        'end_advance',
        'z_index',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'ticker_speed' => 'integer',
        'ticker_font_size' => 'integer',
        'ticker_opacity' => 'decimal:2',
        'logo_size' => 'integer',
        'logo_opacity' => 'decimal:2',
        'logo_margin_x' => 'integer',
        'logo_margin_y' => 'integer',
        'clock_font_size' => 'integer',
        'display_duration' => 'integer',
        'start_delay' => 'integer',
        'end_advance' => 'integer',
        'z_index' => 'integer',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(\App\Models\UserChannel::class, 'channel_id');
    }
}