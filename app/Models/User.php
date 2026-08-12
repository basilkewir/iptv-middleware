<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'email',
        'password',
        'first_name',
        'last_name',
        'phone',
        'is_active',
        'is_admin',
        'role',
        'is_reseller',
        'allow_sub_resellers',
        'white_label',
        'reseller_id',
        'credits',
        'credit_limit',
        'commission_rate',
        'max_connections',
        'company_name',
        'website',
        'permissions',
        'mac_address',
        'country',
        'm3u_token',
        'ip_restriction',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [

            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_admin' => 'boolean',
            'is_reseller' => 'boolean',
            'allow_sub_resellers' => 'boolean',
            'white_label' => 'boolean',
            'credits' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'max_connections' => 'integer',
            'permissions' => 'array',
        
    ];

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin' || $this->is_admin;
    }

    public function isReseller(): bool
    {
        return $this->role === 'reseller' || $this->is_reseller;
    }

    public function isClient(): bool
    {
        return $this->role === 'client' || (!$this->is_admin && !$this->is_reseller);
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->first();
    }

    public function bouquets(): BelongsToMany
    {
        return $this->belongsToMany(Bouquet::class, 'bouquet_user', 'user_id', 'bouquet_id')
            ->withTimestamps();
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(UserActivityLog::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(UserFavorite::class);
    }

    public function watchHistory(): HasMany
    {
        return $this->hasMany(UserWatchHistory::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(UserReview::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reseller_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'reseller_id');
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->exists();
    }
}
