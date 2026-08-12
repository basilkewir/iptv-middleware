<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StreamingServer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'host',
        'port',
        'protocol',
        'is_active',
        'max_connections',
        'current_connections',
        'location',
        'provider',
    ];

    protected $casts = [

            'port' => 'integer',
            'is_active' => 'boolean',
            'max_connections' => 'integer',
            'current_connections' => 'integer',
        
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(StreamAssignment::class);
    }
}
