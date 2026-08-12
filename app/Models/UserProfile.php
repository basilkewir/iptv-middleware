<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'avatar',
        'date_of_birth',
        'country',
        'language',
        'timezone',
        'preferences',
    ];

    protected $casts = [

            'preferences' => 'array',
            'date_of_birth' => 'date',
        
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
