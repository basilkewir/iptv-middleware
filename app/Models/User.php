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
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
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
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->exists();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id')
            ->withTimestamps();
    }

    public function managedChannels(): BelongsToMany
    {
        return $this->belongsToMany(AdminChannel\AdminChannel::class, 'admin_channel_user', 'user_id', 'admin_channel_id')
            ->withTimestamps();
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()
            ->whereIn('name', [$role, 'admin', 'super_admin'])
            ->exists();
    }

    public function roleName(): string
    {
        $role = $this->roles()->value('name');
        return $role ?: ($this->role ?? 'client');
    }

    public function roleLabel(): string
    {
        $role = $this->roles()->first();
        return $role?->label ?: ucfirst($this->roleName());
    }

    public function hasPermission(string $permission): bool
    {
        foreach ($this->roles as $role) {
            $perms = $role->permissions ?? [];
            if (in_array('full_access', $perms, true)) {
                return true;
            }
            if (in_array($permission, $perms, true)) {
                return true;
            }
        }

        return false;
    }

    public function canManageAllMyChannels(): bool
    {
        return $this->is_admin || $this->hasPermission('full_access');
    }

    public function permissionsList(): array
    {
        if ($this->is_admin) {
            return ['full_access'];
        }

        return $this->roles
            ->pluck('permissions')
            ->flatten()
            ->unique()
            ->values()
            ->all();
    }

    public function scopeAdminUsers($query)
    {
        return $query->where(function ($q) {
            $q->where('is_admin', true)
                ->orWhere('is_reseller', true)
                ->orWhereHas('roles', fn ($r) => $r->where('name', '!=', 'client'));
        });
    }

    public function hasAdminPanelAccess(): bool
    {
        if ($this->is_admin || $this->is_reseller) {
            return true;
        }

        return $this->roles()->where('name', '!=', 'client')->exists();
    }

    public function updateFlagsFromRoles(): void
    {
        $names = $this->roles()->pluck('roles.name')->toArray();

        if (array_intersect(['super_admin', 'admin'], $names)) {
            $this->is_admin = true;
            $this->is_reseller = false;
        } elseif (in_array('reseller', $names, true)) {
            $this->is_admin = false;
            $this->is_reseller = true;
        } else {
            // Restricted roles (moderator, support, channel_manager, ...) are
            // NOT admins: no legacy admin flag, panel access is role-based via
            // hasAdminPanelAccess().
            $this->is_admin = false;
            $this->is_reseller = false;
        }

        $this->save();
    }
}
