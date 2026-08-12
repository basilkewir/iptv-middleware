<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VODCrew extends Model
{
    protected $table = 'vod_crews';

    public $timestamps = false;

    protected $fillable = [
        'vod_content_id', 'person_id', 'job', 'department',
    ];

    const CREATED_AT = null;

    public function vodContent(): BelongsTo
    {
        return $this->belongsTo(VODContent::class, 'vod_content_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(VODPerson::class, 'person_id');
    }
}
