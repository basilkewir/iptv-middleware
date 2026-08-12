<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranscodingFilter extends Model
{
    use HasFactory;

    protected $table = 'transcoding_filters';

    protected $fillable = [
        'profile_id', 'filter_type', 'filter_name', 'parameters', 'sort_order',
    ];

    protected $casts = [

            'parameters' => 'array', 'sort_order' => 'integer',
        
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(TranscodingProfile::class, 'profile_id');
    }
}
