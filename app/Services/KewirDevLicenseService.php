<?php

namespace App\Services;

use App\Models\License;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KewirDevLicenseService
{
    public const STATUS_OK = 'ok';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_UNREACHABLE = 'unreachable';
    public const STATUS_ERROR = 'error';
    public const STATUS_CONFIG = 'config';

    protected string $baseUrl;
    protected ?string $secret;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) (config('license.api.base_url') ?: 'https://kewirdev.com/api/license'), '/');

        // Only the dedicated shared key (KEWIRDEV_API_SECRET / LICENSE_JWT_SECRET
        // as set in .env) signs requests to kewirdev.com. Deliberately NOT
        // falling back to config('license.jwt_secret'): that is the *local*
        // session key (APP_KEY by default) and a signature made with it is
        // rejected by the server — worse than sending the request unsigned.
        $this->secret = config('license.api.secret') ?: null;
        $this->timeout = max(1, (int) (config('license.api.timeout') ?: 30));
    }

    /**
     * Validate a license key against the remote kewirdev.com server.
     *
     * There is no offline mode: the remote server is the only authority. Every
     * non-success response carries a `status` so callers can tell apart a bad key
     * (rejected) from an outage (unreachable/error) or a misconfigured server.
     *
     * @return array{success: bool, status: string, message?: string}
     */
    public function validateLicense(string $licenseKey, array $deviceInfo): array
    {
        $payload = json_encode([
            'license_key'      => $licenseKey,
            'device_id'        => $deviceInfo['device_id'] ?? '',
            'device_type'      => $deviceInfo['device_type'] ?? 'unknown',
            'device_name'      => $deviceInfo['device_name'] ?? '',
            'device_model'     => $deviceInfo['device_model'] ?? '',
            'device_os'        => $deviceInfo['device_os'] ?? '',
            'device_os_version' => $deviceInfo['device_os_version'] ?? '',
            'app_version'      => $deviceInfo['app_version'] ?? '',
        ]);

        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
            'User-Agent'   => config('license.api.user_agent', 'HMS-IPTV/1.0'),
        ];

        // Signing is optional on the server side: it accepts unsigned requests
        // unless signature validation is explicitly turned on there. A signature
        // that does not match the server's key is rejected outright, so never
        // invent one — only sign when a shared key is actually configured.
        if ($this->secret !== null && $this->secret !== '') {
            $headers['X-License-Signature'] = hash_hmac('sha256', $payload, $this->secret);
        }

        try {
            $response = Http::withHeaders($headers)
            ->timeout($this->timeout)
            ->withBody($payload, 'application/json')
            ->post($this->baseUrl . '/validate');

            $json = $response->json();

            if ($response->successful() && is_array($json)) {
                if (! empty($json['success'])) {
                    return array_merge($json, ['status' => self::STATUS_OK]);
                }

                return [
                    'success' => false,
                    'status'  => self::STATUS_REJECTED,
                    'message' => self::extractMessage($json),
                ];
            }

            Log::warning('kewirdev.com validation rejected', [
                'status' => $response->status(),
                'body'   => $json,
            ]);

            return [
                'success' => false,
                'status'  => self::STATUS_REJECTED,
                'message' => is_array($json)
                    ? self::extractMessage($json)
                    : 'Remote validation failed (HTTP '.$response->status().').',
            ];
        } catch (ConnectionException $e) {
            Log::warning('kewirdev.com API connection failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'status'  => self::STATUS_UNREACHABLE,
                'message' => 'License server (kewirdev.com) is unreachable. An internet connection is required.',
            ];
        } catch (\Throwable $e) {
            Log::error('kewirdev.com API error', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'status'  => self::STATUS_ERROR,
                'message' => 'Could not talk to the license server (kewirdev.com). Please try again later.',
            ];
        }
    }

    /**
     * Fetch the authoritative license record for an already-validated token.
     *
     * The validate response only carries a short-lived JWT (its `expires_at` is
     * the *token* expiry, not the licence expiry), so the licence details come
     * from /info. Returns null when the details cannot be fetched.
     */
    public function fetchLicenseInfo(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Accept'        => 'application/json',
                'User-Agent'    => config('license.api.user_agent', 'HMS-IPTV/1.0'),
            ])
            ->timeout($this->timeout)
            ->get($this->baseUrl.'/info');

            $json = $response->json();

            if ($response->successful() && is_array($json) && ! empty($json['success']) && is_array($json['data'] ?? null)) {
                return $json['data'];
            }

            Log::warning('kewirdev.com license info fetch failed', [
                'status' => $response->status(),
                'body'   => $json,
            ]);
        } catch (\Throwable $e) {
            Log::warning('kewirdev.com license info fetch error', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Activate a license online: validate the key at kewirdev.com, pull the
     * license details and mirror them into the local `licenses` table (a cache
     * the middleware gate reads). Never trusts the local DB for validity.
     *
     * @return array{success: bool, message: string}
     */
    public function activate(string $licenseKey, array $deviceInfo = []): array
    {
        $deviceInfo = array_merge([
            'device_id'   => gethostname() ?: 'web-'.php_uname('n'),
            'device_type' => 'admin_panel',
            'device_name' => 'IPTV Middleware Admin',
        ], $deviceInfo);

        $result = $this->validateLicense($licenseKey, $deviceInfo);

        if (empty($result['success'])) {
            return ['success' => false, 'message' => self::failureMessage($result)];
        }

        $info = $this->fetchLicenseInfo((string) ($result['token'] ?? ''));

        $values = [
            'hotel_name'   => $info['hotel_name'] ?? 'Licensed',
            'license_type' => self::normalizeLicenseType($info['license_type'] ?? null),
            'status'       => License::STATUS_ACTIVE,
            'max_devices'  => self::normalizeMaxDevices($info),
            'expires_at'   => $info['expires_at'] ?? null,
            'features'     => self::normalizeFeatures($info['features'] ?? $result['features'] ?? null),
        ];

        $license = License::where('license_key', $licenseKey)->first();

        if ($license) {
            $license->update($values);
        } else {
            License::create(array_merge($values, [
                'license_key'     => $licenseKey,
                'hotel_id'        => $info['hotel_id'] ?? $licenseKey,
                'current_devices' => 0,
            ]));
        }

        return [
            'success' => true,
            'message' => 'License validated at kewirdev.com. The system is licensed — you can now sign in.',
        ];
    }

    /**
     * Human-readable message for a failed activation attempt.
     */
    public static function failureMessage(array $result): string
    {
        return match ($result['status'] ?? self::STATUS_ERROR) {
            self::STATUS_REJECTED => $result['message'] ?? 'This license key is invalid, expired, or inactive.',
            self::STATUS_UNREACHABLE,
            self::STATUS_ERROR => $result['message']
                ?? 'Could not reach the license server (kewirdev.com). Online activation is required.',
            default => $result['message'] ?? 'License activation failed.',
        };
    }

    /**
     * kewirdev.com accepts only these licence types; map anything else to enterprise.
     */
    public static function normalizeLicenseType(?string $type): string
    {
        $allowed = ['trial', 'basic', 'premium', 'enterprise', 'perpetual'];

        return $type !== null && in_array($type, $allowed, true) ? $type : License::LICENSE_TYPE_ENTERPRISE;
    }

    public static function normalizeFeatures($features): array
    {
        if (is_array($features)) {
            return $features;
        }

        if (is_string($features) && $features !== '') {
            $decoded = json_decode($features, true);

            return is_array($decoded) ? $decoded : ['*'];
        }

        return ['*'];
    }

    protected static function normalizeMaxDevices(?array $info): int
    {
        $max = $info['device_usage']['maximum'] ?? $info['max_devices'] ?? null;

        return is_numeric($max) && (int) $max > 0 ? (int) $max : 50;
    }

    /**
     * Flatten the kewirdev error payload (error/message/details) into one line.
     */
    protected static function extractMessage(array $json): string
    {
        $message = $json['error'] ?? $json['message'] ?? null;

        if (is_array($message)) {
            $message = collect($message)->flatten()->implode(' ');
        }

        if (is_string($message) && $message !== '') {
            return $message;
        }

        if (is_array($json['details'] ?? null)) {
            $details = collect($json['details'])->flatten()->implode(' ');
            if ($details !== '') {
                return $details;
            }
        }

        return 'This license key is invalid, expired, or inactive.';
    }
}
