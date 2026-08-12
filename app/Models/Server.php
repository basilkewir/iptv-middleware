<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    use HasFactory;

    protected $table = 'streaming_servers';

    protected $fillable = [
        'name',
        'host',
        'port',
        'protocol',
        'is_active',
        'max_connections',
        'current_connections',
        'bandwidth',
        'location',
        'provider',
        'settings',
    ];

    protected $casts = [

            'is_active' => 'boolean',
            'max_connections' => 'integer',
            'current_connections' => 'integer',
            'bandwidth' => 'integer',
            'settings' => 'array',
        
    ];

    public function streamAssignments(): HasMany
    {
        return $this->hasMany(StreamAssignment::class, 'server_id');
    }
}