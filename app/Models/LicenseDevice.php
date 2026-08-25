<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LicenseDevice extends Model
{
    use HasFactory;

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_BLOCKED = 'blocked';

    const TYPE_ANDROID_TV = 'android_tv';
    const TYPE_SMART_TV = 'smart_tv';
    const TYPE_MANAGEMENT_BACKEND = 'management_backend';
    const TYPE_ADMIN_PANEL = 'admin_panel';

    const DEVICE_ID_ANDROID_TV = 'android_tv';
    const DEVICE_ID_SMART_TV = 'smart_tv';
    const DEVICE_ID_MANAGEMENT_BACKEND = 'management_backend';
    const DEVICE_ID_ADMIN_PANEL = 'admin_panel';

    protected $fillable = [
        'license_id',
        'device_id',
        'device_fingerprint',
        'device_name',
        'device_type',
        'device_model',
        'device_os',
        'device_os_version',
        'app_version',
        'ip_address',
        'mac_address',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function license()
    {
        return $this->belongsTo(License::class);
    }

    public static function generateFingerprint(array $deviceInfo): string
    {
        $components = [
            $deviceInfo['device_type'] ?? '',
            $deviceInfo['device_name'] ?? '',
            $deviceInfo['device_model'] ?? '',
            $deviceInfo['device_os'] ?? '',
            $deviceInfo['device_os_version'] ?? '',
            $deviceInfo['app_version'] ?? '',
            $deviceInfo['device_id'] ?? '',
        ];

        return md5(implode('|', $components));
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function activate(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'activation_count' => $this->activation_count + 1,
            'last_seen_at' => now(),
        ]);
    }

    public function updateLastSeen(): void
    {
        $this->update(['last_seen_at' => now()]);
    }
}
