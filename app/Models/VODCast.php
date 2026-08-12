<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VODCast extends Model
{
    protected $table = 'vod_casts';

    public $timestamps = false;

    protected $fillable = [
        'vod_content_id', 'person_id', 'character_name', 'role', 'order_index',
    ];

    protected $casts = [
        'vod_content_id' => 'integer',
        'person_id' => 'integer',
        'created_at' => 'datetime',
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
