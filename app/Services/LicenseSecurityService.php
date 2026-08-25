<?php

namespace App\Services;

use App\Models\LicenseValidationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LicenseSecurityService
{
    /**
     * Perform comprehensive security checks
     */
    public function performSecurityChecks(Request $request): array
    {
        $checks = [
            'ip_reputation' => $this->checkIPReputation($request->ip()),
            'user_agent' => $this->validateUserAgent($request->userAgent()),
            'request_signature' => $this->validateRequestSignature($request),
            'rate_limiting' => $this->checkRateLimit($request),
            'geo_location' => $this->checkGeoLocation($request->ip()),
            'device_fingerprint' => $this->validateDeviceFingerprint($request)
        ];

        $passed = collect($checks)->every(fn($check) => $check['passed']);
        $failedChecks = collect($checks)->filter(fn($check) => !$check['passed'])->keys();

        return [
            'passed' => $passed,
            'checks' => $checks,
            'failed_checks' => $failedChecks->toArray(),
            'reason' => $failedChecks->isEmpty() ? null : 'Failed security checks: ' . $failedChecks->implode(', ')
        ];
    }

    /**
     * Check IP reputation
     */
    private function checkIPReputation(string $ip): array
    {
        if (in_array($ip, $this->loadBlockedIPs(), true)) {
            return ['passed' => false, 'reason' => 'IP is blacklisted'];
        }

        $suspiciousPatterns = [
            '/^10\./',      // Private network
            '/^192\.168\./', // Private network
            '/^172\.(1[6-9]|2[0-9]|3[0-1])\./', // Private network
            '/^127\./',     // Localhost
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $ip)) {
                Log::info('Private network access detected', ['ip' => $ip]);
                break;
            }
        }

        return ['passed' => true, 'reason' => null];
    }

    /**
     * Validate User Agent
     */
    private function validateUserAgent(?string $userAgent): array
    {
        if (empty($userAgent)) {
            return ['passed' => false, 'reason' => 'Missing User-Agent'];
        }

        $suspiciousPatterns = [
            '/curl/i',
            '/wget/i',
            '/python/i',
            '/bot/i',
            '/crawler/i',
            '/spider/i'
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return ['passed' => false, 'reason' => 'Suspicious User-Agent detected'];
            }
        }

        return ['passed' => true, 'reason' => null];
    }

    /**
     * Validate request signature (HMAC)
     * Enforced when LICENSE_SIGNATURE_VALIDATION=true in .env
     */
    private function validateRequestSignature(Request $request): array
    {
        $signatureRequired = config('license.enable_signature_validation', false);
        $signature = $request->header('X-License-Signature');

        if (!$signature) {
            if ($signatureRequired) {
                return ['passed' => false, 'reason' => 'X-License-Signature header is required but missing'];
            }
            Log::info('Request without HMAC signature (validation optional)', ['ip' => $request->ip()]);
            return ['passed' => true, 'reason' => null];
        }

        $hmacKey = config('license.jwt_secret', config('app.key'));
        $payload = json_encode($request->except(['_token']));
        $expectedSignature = hash_hmac('sha256', $payload, $hmacKey);

        if (!hash_equals($expectedSignature, $signature)) {
            return ['passed' => false, 'reason' => 'Invalid request signature'];
        }

        return ['passed' => true, 'reason' => null];
    }

    /**
     * Check rate limiting
     */
    private function checkRateLimit(Request $request): array
    {
        $key = 'license_requests:' . $request->ip();
        $attempts = Cache::get($key, 0);
        $maxAttempts = 100; // per hour
        
        if ($attempts >= $maxAttempts) {
            return ['passed' => false, 'reason' => 'Rate limit exceeded'];
        }

        Cache::put($key, $attempts + 1, now()->addHour());
        
        return ['passed' => true, 'reason' => null];
    }

    /**
     * Check geo-location restrictions
     */
    private function checkGeoLocation(string $ip): array
    {
        $allowedCountries = config('license.allowed_countries', []);
        
        if (empty($allowedCountries)) {
            return ['passed' => true, 'reason' => null];
        }

        // For now, we'll skip actual geo-location checking
        // In production, integrate with MaxMind GeoIP2 or similar service
        
        return ['passed' => true, 'reason' => null];
    }

    /**
     * Validate device fingerprint consistency — mismatch is now a hard block.
     */
    private function validateDeviceFingerprint(Request $request): array
    {
        $deviceId   = $request->input('device_id');
        $deviceType = $request->input('device_type');
        $deviceModel = $request->input('device_model');

        if (!$deviceId || !$deviceType) {
            return ['passed' => false, 'reason' => 'Missing device information'];
        }

        $fingerprintKey = "device_fingerprint:{$deviceId}";
        $storedFingerprint = Cache::get($fingerprintKey);

        $currentFingerprint = [
            'device_type'  => $deviceType,
            'device_model' => $deviceModel,
            'user_agent'   => $request->userAgent(),
        ];

        if ($storedFingerprint && $storedFingerprint !== $currentFingerprint) {
            Log::warning('Device fingerprint mismatch — request blocked', [
                'device_id' => $deviceId,
                'stored'    => $storedFingerprint,
                'current'   => $currentFingerprint,
                'ip'        => $request->ip(),
            ]);
            return ['passed' => false, 'reason' => 'Device fingerprint mismatch detected'];
        }

        Cache::put($fingerprintKey, $currentFingerprint, now()->addDays(30));

        return ['passed' => true, 'reason' => null];
    }

    /**
     * Detect debugging/reverse engineering attempts
     */
    public function detectDebuggingAttempts(Request $request): array
    {
        $suspiciousHeaders = [
            'X-Forwarded-For',
            'X-Real-IP',
            'X-Debug',
            'X-Debugger',
            'X-Proxy'
        ];

        $detectedHeaders = [];
        foreach ($suspiciousHeaders as $header) {
            if ($request->hasHeader($header)) {
                $detectedHeaders[] = $header;
            }
        }

        if (!empty($detectedHeaders)) {
            Log::warning('Suspicious headers detected', [
                'ip' => $request->ip(),
                'headers' => $detectedHeaders,
                'user_agent' => $request->userAgent()
            ]);
        }

        return [
            'suspicious_headers' => $detectedHeaders,
            'risk_level' => count($detectedHeaders) > 2 ? 'high' : 'low'
        ];
    }

    // -------------------------------------------------------------------------
    // Persistent IP block list — stored in storage/app/blocked_ips.json
    // (survives cache clears and server restarts)
    // -------------------------------------------------------------------------

    private function blockedIpsPath(): string
    {
        return storage_path('app/blocked_ips.json');
    }

    private function loadBlockedIPs(): array
    {
        $path = $this->blockedIpsPath();
        if (!file_exists($path)) {
            return [];
        }
        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    private function saveBlockedIPs(array $ips): void
    {
        file_put_contents($this->blockedIpsPath(), json_encode(array_values(array_unique($ips)), JSON_PRETTY_PRINT));
    }

    /**
     * Block IP address — persisted to storage (not cache)
     */
    public function blockIP(string $ip, string $reason = 'Security violation'): void
    {
        $blocked = $this->loadBlockedIPs();
        $blocked[] = $ip;
        $this->saveBlockedIPs($blocked);

        Log::warning('IP blocked', [
            'ip'        => $ip,
            'reason'    => $reason,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Unblock IP address
     */
    public function unblockIP(string $ip): void
    {
        $blocked = $this->loadBlockedIPs();
        $blocked = array_values(array_diff($blocked, [$ip]));
        $this->saveBlockedIPs($blocked);

        Log::info('IP unblocked', ['ip' => $ip]);
    }

    /**
     * Get security metrics — sourced from the license_validation_logs table
     */
    public function getSecurityMetrics(): array
    {
        return [
            'blocked_ips'                => count($this->loadBlockedIPs()),
            'failed_validations_24h'     => $this->getFailedValidationsCount(24),
            'suspicious_activities_24h'  => $this->getSuspiciousActivitiesCount(24),
            'unique_ips_24h'             => $this->getUniqueIPsCount(24),
        ];
    }

    /**
     * Count failed/expired/blocked validations in the last N hours
     */
    private function getFailedValidationsCount(int $hours): int
    {
        return LicenseValidationLog::whereIn('status', [
                LicenseValidationLog::STATUS_INVALID,
                LicenseValidationLog::STATUS_EXPIRED,
                LicenseValidationLog::STATUS_BLOCKED,
                LicenseValidationLog::STATUS_FAILED,
            ])
            ->where('validated_at', '>=', now()->subHours($hours))
            ->count();
    }

    /**
     * Count validations that triggered a blocked-IP or fingerprint-mismatch in last N hours
     */
    private function getSuspiciousActivitiesCount(int $hours): int
    {
        return LicenseValidationLog::where('status', LicenseValidationLog::STATUS_BLOCKED)
            ->where('validated_at', '>=', now()->subHours($hours))
            ->count();
    }

    /**
     * Count distinct IPs that sent validation requests in the last N hours
     */
    private function getUniqueIPsCount(int $hours): int
    {
        return LicenseValidationLog::where('validated_at', '>=', now()->subHours($hours))
            ->distinct('ip_address')
            ->count('ip_address');
    }
}