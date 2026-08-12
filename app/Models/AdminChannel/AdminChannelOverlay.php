<?php

namespace App\Models\AdminChannel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminChannelOverlay extends Model
{
    use HasFactory;

    protected $table = 'admin_channel_overlays';

    protected $fillable = [
        'admin_channel_id',
        'overlay_name',
        'overlay_type',
        'overlay_url',
        'overlay_text',
        'position',
        'size',
        'opacity',
        'color',
        'background_color',
        'z_index',
        'is_enabled',
        'start_time',
        'end_time',
        'animation',
    ];

    protected $casts = [
        'opacity' => 'decimal:2',
        'z_index' => 'integer',
        'is_enabled' => 'boolean',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'animation' => 'json',
    ];

    public function adminChannel(): BelongsTo
    {
        return $this->belongsTo(AdminChannel::class, 'admin_channel_id');
    }
}