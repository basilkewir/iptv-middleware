<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class License extends Model
{
    use HasFactory;

    protected $fillable = [
        'license_key',
        'hotel_id',
        'hotel_name',
        'license_type',
        'status',
        'max_devices',
        'current_devices',
        'expires_at',
        'last_validated_at',
        'validation_count',
        'features',
        'metadata',
    ];

    protected $casts = [
        'features' => 'array',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'last_validated_at' => 'datetime',
    ];

    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_REVOKED = 'revoked';

    const LICENSE_TYPE_TRIAL = 'trial';
    const LICENSE_TYPE_BASIC = 'basic';
    const LICENSE_TYPE_PREMIUM = 'premium';
    const LICENSE_TYPE_ENTERPRISE = 'enterprise';
    const TYPE_PERPETUAL = 'perpetual';

    const DEVICE_TYPE_ANDROID_TV = 'android_tv';
    const DEVICE_TYPE_SMART_TV = 'smart_tv';
    const DEVICE_TYPE_MANAGEMENT_BACKEND = 'management_backend';
    const DEVICE_TYPE_ADMIN_PANEL = 'admin_panel';

    /**
     * Check if license is valid
     */
    public function isValid(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        // Perpetual licenses never expire
        if ($this->license_type === self::TYPE_PERPETUAL) {
            return true;
        }

        // Check expiration
        if ($this->expires_at !== null) {
            return $this->expires_at->isFuture();
        }

        return true;
    }

    /**
     * Check if license is expired
     */
    public function isExpired(): bool
    {
        // Perpetual licenses never expire
        if ($this->license_type === self::TYPE_PERPETUAL) {
            return false;
        }

        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Check if license is perpetual
     */
    public function isPerpetual(): bool
    {
        return $this->license_type === self::TYPE_PERPETUAL;
    }

    /**
     * Check if device limit is reached
     */
    public function isDeviceLimitReached(): bool
    {
        return $this->licenseDevices()->where('status', LicenseDevice::STATUS_ACTIVE)->count() >= $this->max_devices;
    }

    /**
     * Check if device limit is reached for specific device type
     */
    public function isDeviceTypeLimitReached(string $deviceType): bool
    {
        $normalized = $this->normalizeDeviceType($deviceType);
        $currentCount = $this->licenseDevices()->where('device_type', $normalized)->where('status', LicenseDevice::STATUS_ACTIVE)->count();

        return $currentCount >= $this->max_devices;
    }

    /**
     * Check if device can be added
     */
    public function canAddDevice(?string $deviceType = null): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        return !$this->isDeviceLimitReached();
    }

    /**
     * Normalize device type for database field names
     */
    private function normalizeDeviceType(string $deviceType): string
    {
        $mapping = [
            'android_tv' => 'android_tv',
            'smart_tv' => 'smart_tv',
            'management_backend' => 'backend',
            'admin_panel' => 'admin_panel'
        ];

return $mapping[$deviceType] ?? $deviceType;
    }

    public function licenseDevices()
    {
        return $this->hasMany(LicenseDevice::class, 'license_id');
    }

    public function validationLogs()
    {
        return $this->hasMany(LicenseValidationLog::class, 'license_id');
    }

    public function getAvailableFeatures(): array
    {
        if ($this->features !== null) {
            return is_array($this->features) ? $this->features : json_decode($this->features, true) ?? [];
        }

        return match ($this->license_type) {
            self::LICENSE_TYPE_TRIAL => ['live_tv', 'vod'],
            self::LICENSE_TYPE_BASIC => ['live_tv', 'vod', 'epg'],
            self::LICENSE_TYPE_PREMIUM => ['live_tv', 'vod', 'epg', 'favorites', 'watch_history'],
            self::LICENSE_TYPE_ENTERPRISE, self::TYPE_PERPETUAL => ['*'],
            default => [],
        };
    }

    public function incrementDeviceCount(?string $deviceType = null): void
    {
        $this->increment('current_devices');

        $normalized = $deviceType ? 'current_' . $this->normalizeDeviceType($deviceType) : null;
        if ($normalized && \Schema::hasColumn($this->getTable(), $normalized)) {
            $this->increment($normalized);
        }
    }

    public function updateValidation(): void
    {
        $this->increment('validation_count');
        $this->update(['last_validated_at' => now()]);
    }
}
