<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EPGProgram extends Model
{
    use HasFactory;

    protected $table = 'epg_programs';

    protected $fillable = [
        'epg_source_id',
        'channel_id',
        'title',
        'description',
        'start_time',
        'end_time',
        'program_id',
        'language',
        'rating',
        'category',
        'episode',
        'season',
    ];

    protected $casts = [

            'start_time' => 'datetime',
            'end_time' => 'datetime',
        
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
