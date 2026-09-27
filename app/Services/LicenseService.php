<?php

namespace App\Services;

use App\Models\License;
use App\Models\LicenseDevice;
use App\Models\LicenseValidationLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class LicenseService
{
    private $jwtSecret;
    private $tokenExpiration;
    protected KewirDevLicenseService $kewirDev;

    public function __construct()
    {
        $this->jwtSecret = config('license.jwt_secret') ?: null;
        if (empty($this->jwtSecret)) {
            // Never fatal here: report a clear error instead of a bare 500.
            Log::error('LICENSE_JWT_SECRET is not set — license validation will fail until it is configured', [
                'env_keys' => 'LICENSE_JWT_SECRET / KEWIRDEV_API_SECRET',
            ]);
        }
        $this->tokenExpiration = (int) (config('license.token_expiration') ?: 3600);
        $this->kewirDev = new KewirDevLicenseService();
    }

    /**
     * Validate license key with device binding.
     *
     * kewirdev.com is the only authority — there is no offline fallback. A valid
     * remote answer is mirrored into the local DB (the middleware gate reads it)
     * and turned into a local JWT; anything else is rejected.
     */
    public function validateLicense(string $licenseKey, array $deviceInfo): array
    {
        $startTime = microtime(true);

        if (empty($this->jwtSecret)) {
            return $this->createValidationResponse(
                false,
                'License signing secret is not configured on this server (LICENSE_JWT_SECRET / KEWIRDEV_API_SECRET).',
                null,
                $licenseKey,
                $deviceInfo,
                $startTime,
                LicenseValidationLog::STATUS_FAILED
            );
        }

        try {
            // ── Remote validation against kewirdev.com ─────────────────────────
            $remoteResult = $this->kewirDev->validateLicense($licenseKey, $deviceInfo);
            $remoteStatus = $remoteResult['status'] ?? KewirDevLicenseService::STATUS_ERROR;

            if (! empty($remoteResult['success'])) {
                // Remote says valid — sync license + device to local DB
                $license = $this->syncLicenseFromRemote($remoteResult, $licenseKey, $deviceInfo);

                $deviceFingerprint = LicenseDevice::generateFingerprint($deviceInfo);
                $device = $this->handleDeviceBinding($license, $deviceInfo, $deviceFingerprint);

                if (! $device) {
                    return $this->createValidationResponse(
                        false,
                        'Device limit reached or device blocked',
                        $license,
                        $licenseKey,
                        $deviceInfo,
                        $startTime,
                        LicenseValidationLog::STATUS_BLOCKED
                    );
                }

                $license->updateValidation();
                $device->updateLastSeen();

                $token = $this->generateJWTToken($license, $device);
                $this->cacheValidationResult($licenseKey, $deviceFingerprint, true);

                return $this->createValidationResponse(
                    true,
                    'License validated successfully (remote)',
                    $license,
                    $licenseKey,
                    $deviceInfo,
                    $startTime,
                    LicenseValidationLog::STATUS_SUCCESS,
                    [
                        'token'      => $token,
                        'expires_at' => now()->addSeconds($this->tokenExpiration)->toISOString(),
                        'features'   => $license->getAvailableFeatures(),
                        'device_id'  => $device->id,
                    ]
                );
            }

            // Remote rejected the key — offline validation is not allowed.
            if ($remoteStatus !== KewirDevLicenseService::STATUS_REJECTED) {
                Log::warning('License validation unavailable, online validation is required', [
                    'license_key' => $licenseKey,
                    'status'      => $remoteStatus,
                ]);
            }

            return $this->createValidationResponse(
                false,
                KewirDevLicenseService::failureMessage($remoteResult),
                null,
                $licenseKey,
                $deviceInfo,
                $startTime,
                $remoteStatus === KewirDevLicenseService::STATUS_REJECTED
                    ? LicenseValidationLog::STATUS_INVALID
                    : LicenseValidationLog::STATUS_FAILED
            );
        } catch (\Throwable $e) {
            Log::error('License validation error', ['error' => $e->getMessage()]);

            return $this->createValidationResponse(
                false,
                'License validation failed: '.$e->getMessage(),
                null,
                $licenseKey,
                $deviceInfo,
                $startTime,
                LicenseValidationLog::STATUS_FAILED
            );
        }
    }

    /**
     * Sync a remotely-validated license into the local database.
     *
     * Details come from kewirdev.com /info (via the token from the validate
     * response); the validate payload itself only carries a short-lived JWT.
     */
    protected function syncLicenseFromRemote(array $remote, string $licenseKey, array $deviceInfo): License
    {
        $info = $this->kewirDev->fetchLicenseInfo((string) ($remote['token'] ?? '')) ?: [];
        $licenseData = array_merge($info, is_array($remote['license'] ?? null) ? $remote['license'] : []);

        $values = [
            'hotel_id'      => $licenseData['hotel_id']      ?? $licenseKey,
            'hotel_name'    => $licenseData['hotel_name']    ?? 'Licensed Hotel',
            'license_type'  => KewirDevLicenseService::normalizeLicenseType($licenseData['license_type'] ?? null),
            'status'        => License::STATUS_ACTIVE,
            'max_devices'   => $licenseData['device_usage']['maximum'] ?? $licenseData['max_devices'] ?? 5,
            'features'      => KewirDevLicenseService::normalizeFeatures($licenseData['features'] ?? null),
            'expires_at'    => $licenseData['expires_at']    ?? null,
        ];

        $license = License::firstOrCreate(['license_key' => $licenseKey], $values);

        // Keep the local record in sync with the authoritative remote data.
        $synced = array_intersect_key($values, array_flip([
            'hotel_name', 'license_type', 'status', 'max_devices', 'features', 'expires_at',
        ]));

        if ($synced) {
            $license->update($synced);
        }

        return $license->fresh();
    }

    /**
     * Validate JWT token
     */
    public function validateToken(string $token): array
    {
        if (empty($this->jwtSecret)) {
            return ['valid' => false, 'error' => 'License signing secret is not configured on this server.'];
        }

        try {
            $decoded = $this->manualJWTDecode($token, $this->jwtSecret);
            if (!$decoded) {
                return ['valid' => false, 'error' => 'Token is invalid or has expired. Please re-validate your license.'];
            }
            
            // Check if license still exists and is valid
            $license = License::find($decoded->license_id);
            if (!$license || !$license->isValid()) {
                return ['valid' => false, 'error' => 'License no longer valid'];
            }

            // Check if device still exists and is active
            $device = LicenseDevice::find($decoded->device_id);
            if (!$device || !$device->isActive()) {
                return ['valid' => false, 'error' => 'Device no longer active'];
            }

            return [
                'valid' => true,
                'license' => $license,
                'device' => $device,
                'features' => $license->getAvailableFeatures()
            ];

        } catch (\Exception $e) {
            Log::warning('JWT decode failed', ['error' => $e->getMessage()]);
            return ['valid' => false, 'error' => 'Token is invalid or has expired. Please re-validate your license.'];
        }
    }

    /**
     * Handle device binding logic — wrapped in a DB transaction with row-level lock
     * to prevent TOCTOU race conditions on device-limit enforcement.
     */
    private function handleDeviceBinding(License $license, array $deviceInfo, string $deviceFingerprint): ?LicenseDevice
    {
        return DB::transaction(function () use ($license, $deviceInfo, $deviceFingerprint) {
            // Re-fetch the license with an exclusive row lock so concurrent
            // activations cannot both pass the canAddDevice() check.
            $license = License::lockForUpdate()->find($license->id);

            // Check if device already exists
            $device = LicenseDevice::where('license_id', $license->id)
                ->where('device_fingerprint', $deviceFingerprint)
                ->first();

            if ($device) {
                if ($device->status === LicenseDevice::STATUS_BLOCKED) {
                    return null;
                }
                if (!$device->isActive()) {
                    $device->activate();
                }
                return $device;
            }

            // New device — check limit under the lock (atomic check+create)
            $deviceType = $deviceInfo['device_type'] ?? 'unknown';
            if (!$license->canAddDevice($deviceType)) {
                return null;
            }

            $device = LicenseDevice::create([
                'license_id'       => $license->id,
                'device_id'        => $deviceInfo['device_id'] ?? '',
                'device_fingerprint' => $deviceFingerprint,
                'device_name'      => $deviceInfo['device_name'] ?? 'Unknown Device',
                'device_type'      => $deviceType,
                'device_model'     => $deviceInfo['device_model'] ?? '',
                'device_os'        => $deviceInfo['device_os'] ?? '',
                'device_os_version' => $deviceInfo['device_os_version'] ?? '',
                'app_version'      => $deviceInfo['app_version'] ?? '',
                'ip_address'       => $deviceInfo['ip_address'] ?? request()->ip(),
                'mac_address'      => $deviceInfo['mac_address'] ?? '',
                'status'           => LicenseDevice::STATUS_ACTIVE,
                'first_activated_at' => now(),
                'last_seen_at'     => now(),
                'activation_count' => 1,
                'metadata'         => $deviceInfo['metadata'] ?? [],
            ]);

            $license->incrementDeviceCount($deviceType);

            return $device;
        });
    }

    /**
     * Generate JWT token — public so controllers can use it directly (e.g. refreshToken)
     */
    public function generateToken(License $license, LicenseDevice $device): string
    {
        return $this->generateJWTToken($license, $device);
    }

    /**
     * Generate JWT token (internal) — uses Firebase JWT if available, falls back to manual encoding
     */
    private function generateJWTToken(License $license, LicenseDevice $device): string
    {
        if (empty($this->jwtSecret)) {
            throw new \RuntimeException(
                'LICENSE_JWT_SECRET is not set. Run: php artisan tinker --execute="echo base64_encode(random_bytes(32));"'
            );
        }

        $payload = [
            'iss' => config('app.url'),
            'aud' => 'hotel-iptv-app',
            'iat' => time(),
            'exp' => time() + $this->tokenExpiration,
            'license_id' => $license->id,
            'license_key' => $license->license_key,
            'device_id' => $device->id,
            'device_fingerprint' => $device->device_fingerprint,
            'hotel_id' => $license->hotel_id,
            'license_type' => $license->license_type,
            'features' => $license->getAvailableFeatures()
        ];

        // Try using Firebase JWT if the class exists, otherwise use manual encoding
        if (class_exists(\Firebase\JWT\JWT::class)) {
            return JWT::encode($payload, $this->jwtSecret, 'HS256');
        }

        // Manual JWT encoding using base64
        return $this->manualJWTEncode($payload, $this->jwtSecret);
    }

    /**
     * Manual JWT encode function (fallback when Firebase JWT is not available)
     */
    private function manualJWTEncode(array $payload, string $key): string
    {
        // Header
        $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $b64Header = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
        
        // Payload
        $b64Payload = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        
        // Signature
        $signingInput = $b64Header . '.' . $b64Payload;
        $signature = base64_encode(hash_hmac('sha256', $signingInput, $key, true));
        $b64Signature = rtrim(strtr($signature, '+/', '-_'), '=');
        
        return $b64Header . '.' . $b64Payload . '.' . $b64Signature;
    }

    /**
     * Manual JWT decode function (fallback when Firebase JWT is not available)
     */
    private function manualJWTDecode(string $token, string $key): ?\stdClass
    {
        // Split token into parts
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        
        // Decode header and payload
        $header = json_decode($this->base64_url_decode($parts[0]), true);
        $payload = json_decode($this->base64_url_decode($parts[1]), true);
        
        // Verify signature
        $signingInput = $parts[0] . '.' . $parts[1];
        $expectedSignature = base64_encode(hash_hmac('sha256', $signingInput, $key, true));
        $expectedSignature = rtrim(strtr($expectedSignature, '+/', '-_'), '=');
        
        if (!hash_equals($expectedSignature, $parts[2])) {
            return null;
        }
        
        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }
        
        // Return as stdClass
        $result = new \stdClass();
        foreach ($payload as $key => $value) {
            $result->$key = $value;
        }
        
        return $result;
    }

    /**
     * Base64 URL decode
     */
    private function base64_url_decode($input): string
    {
        return base64_decode(str_pad(strtr($input, '-_', '+/'), strlen($input) % 4 ? strlen($input) + (4 - strlen($input) % 4) : strlen($input), '='));
    }

    /**
     * Create validation response
     */
    private function createValidationResponse(
        bool $success, 
        string $message, 
        ?License $license, 
        string $licenseKey, 
        array $deviceInfo, 
        float $startTime,
        string $status,
        array $additionalData = []
    ): array {
        $processingTime = microtime(true) - $startTime;

        // Log validation attempt
        LicenseValidationLog::create([
            'license_id' => $license?->id,
            'device_id' => $deviceInfo['device_id'] ?? null,
            'validation_type' => LicenseValidationLog::TYPE_INITIAL,
            'status' => $status,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'request_data' => [
                'license_key' => $licenseKey,
                'device_info' => $deviceInfo
            ],
            'response_data' => array_merge([
                'success' => $success,
                'message' => $message
            ], $additionalData),
            'error_message' => $success ? null : $message,
            'processing_time' => $processingTime,
            'validated_at' => now()
        ]);

        return array_merge([
            'success' => $success,
            'message' => $message,
            'timestamp' => now()->toISOString(),
            'processing_time' => round($processingTime * 1000, 2) . 'ms'
        ], $additionalData);
    }

    /**
     * Cache validation result
     */
    private function cacheValidationResult(string $licenseKey, string $deviceFingerprint, bool $isValid): void
    {
        $cacheKey = "license_validation:{$licenseKey}:{$deviceFingerprint}";
        Cache::put($cacheKey, $isValid, now()->addMinutes(5));
    }

    /**
     * Check cached validation result
     */
    public function getCachedValidationResult(string $licenseKey, string $deviceFingerprint): ?bool
    {
        $cacheKey = "license_validation:{$licenseKey}:{$deviceFingerprint}";
        return Cache::get($cacheKey);
    }

    /**
     * Create new license
     */
    public function createLicense(array $data): License
    {
        return License::create(array_merge($data, [
            'license_key' => License::generateLicenseKey(),
            'status' => License::STATUS_ACTIVE,
            'current_devices' => 0,
            'validation_count' => 0
        ]));
    }

    /**
     * Revoke license
     */
    public function revokeLicense(string $licenseKey): bool
    {
        $license = License::where('license_key', $licenseKey)->first();
        
        if (!$license) {
            return false;
        }

        $license->update(['status' => License::STATUS_REVOKED]);
        
        // Deactivate all devices
        $license->devices()->update(['status' => LicenseDevice::STATUS_INACTIVE]);
        
        // Clear cache
        $license->devices->each(function ($device) use ($licenseKey) {
            $cacheKey = "license_validation:{$licenseKey}:{$device->device_fingerprint}";
            Cache::forget($cacheKey);
        });

        return true;
    }

    /**
     * Get license statistics
     */
    public function getLicenseStats(string $licenseKey): array
    {
        $license = License::where('license_key', $licenseKey)
            ->with(['devices', 'validationLogs'])
            ->first();

        if (!$license) {
            return [];
        }

        return [
            'license' => $license,
            'total_devices' => $license->devices->count(),
            'active_devices' => $license->activeDevices->count(),
            'total_validations' => $license->validation_count,
            'recent_validations' => $license->validationLogs()
                ->where('validated_at', '>=', now()->subDays(7))
                ->count(),
            'last_validation' => $license->last_validated_at,
            'is_valid' => $license->isValid(),
            'expires_at' => $license->expires_at,
            'features' => $license->getAvailableFeatures()
        ];
    }

    /**
     * Detect suspicious activity
     */
    public function detectSuspiciousActivity(License $license): array
    {
        $suspiciousActivities = [];

        // Check for too many devices
        if ($license->current_devices > $license->max_devices) {
            $suspiciousActivities[] = 'Device limit exceeded';
        }

        // Check for rapid validation attempts
        $recentValidations = $license->validationLogs()
            ->where('validated_at', '>=', now()->subMinutes(5))
            ->count();

        if ($recentValidations > 50) {
            $suspiciousActivities[] = 'Too many validation attempts';
        }

        // Check for multiple IPs
        $recentIPs = $license->validationLogs()
            ->where('validated_at', '>=', now()->subHours(1))
            ->distinct('ip_address')
            ->count();

        if ($recentIPs > 10) {
            $suspiciousActivities[] = 'Multiple IP addresses detected';
        }

        return $suspiciousActivities;
    }
    /**
     * Sync room count from an HMS installation.
     * HMS reports how many rooms it currently has; we store it in assigned_rooms
     * and validate against the license limit (max_users = room limit, -1 = unlimited).
     */
    public function syncRooms(string $licenseKey, string $deviceId, int $roomCount): array
    {
        $license = License::where('license_key', $licenseKey)->first();

        if (!$license) {
            return ['success' => false, 'error' => 'License not found'];
        }

        if (!$license->isValid()) {
            return ['success' => false, 'error' => 'License is not active'];
        }

        // Room limit comes from features.max_users (-1 = unlimited)
        $features  = $license->getAvailableFeatures();
        $roomLimit = (int) ($features['max_users'] ?? -1);

        // Enforce limit
        if ($roomLimit !== -1 && $roomCount > $roomLimit) {
            return [
                'success'    => false,
                'error'      => "Room limit exceeded: license allows {$roomLimit} rooms, HMS reports {$roomCount}.",
                'room_count' => $roomCount,
                'room_limit' => $roomLimit,
                'allowed'    => false,
            ];
        }

        // Persist the live count
        $license->update(['assigned_rooms' => $roomCount]);

        return [
            'success'    => true,
            'room_count' => $roomCount,
            'room_limit' => $roomLimit,
            'allowed'    => true,
        ];
    }
}