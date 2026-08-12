<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EPGChannelMapping extends Model
{
    use HasFactory;

    protected $table = 'epg_channel_mappings';

    protected $fillable = [
        'epg_source_id',
        'channel_id',
        'epg_channel_id',
        'epg_channel_name',
        'is_auto_matched',
    ];

    protected $casts = [

            'is_auto_matched' => 'boolean',
        
    ];

    public function epgSource(): BelongsTo
    {
        return $this->belongsTo(EPGSource::class, 'epg_source_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
