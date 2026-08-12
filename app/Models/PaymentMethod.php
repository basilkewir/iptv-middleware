<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'gateway',
        'is_active',
        'config',
        'sort_order',
    ];

    protected $casts = [

            'config' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        
    ];

    protected $hidden = [
        'config',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
