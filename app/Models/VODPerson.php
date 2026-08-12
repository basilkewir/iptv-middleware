<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class VODPerson extends Model
{
    protected $table = 'vod_persons';

    protected $fillable = [
        'tmdb_id', 'name', 'profile_url', 'biography', 'birth_date',
        'death_date', 'place_of_birth', 'known_for_department', 'popularity',
    ];

    protected $casts = [
        'popularity' => 'decimal:2',
        'birth_date' => 'date',
        'death_date' => 'date',
    ];

    public function vodContent(): BelongsToMany
    {
        return $this->belongsToMany(VODContent::class, 'vod_casts', 'person_id', 'vod_content_id')
            ->withPivot(['character_name', 'role', 'order_index']);
    }
}
