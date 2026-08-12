<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EPGSource extends Model
{
    use HasFactory;

    protected $table = 'epg_sources';

    protected $fillable = [
        'name',
        'url',
        'type',
        'language',
        'timezone',
        'update_interval',
        'auto_mapping',
        'mapping_strategy',
        'is_active',
        'last_fetched_at',
        'next_fetch_at',
    ];

    protected $casts = [

            'is_active' => 'boolean',
            'auto_mapping' => 'boolean',
            'last_fetched_at' => 'datetime',
            'next_fetch_at' => 'datetime',
        
    ];

    public function programs(): HasMany
    {
        return $this->hasMany(EPGProgram::class, 'epg_source_id');
    }

    public function channelMappings(): HasMany
    {
        return $this->hasMany(EPGChannelMapping::class);
    }
}
