<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class KewirDevLicenseService
{
    protected string $baseUrl;
    protected string $secret;
    protected int $timeout;
    protected int $retries;

    public function __construct()
    {
        $this->baseUrl = config('license.api.base_url', 'https://kewirdev.com/api/license');
        $this->secret  = config('license.api.secret', config('license.jwt_secret'));
        $this->timeout = config('license.api.timeout', 30);
        $this->retries = config('license.api.retry_attempts', 3);
    }

    /**
     * Validate a license key against the remote kewirdev.com server.
     *
     * @return array{success: bool, message?: string, license?: array, device_id?: int, token?: string, features?: array}
     */
    public function validateLicense(string $licenseKey, array $deviceInfo): array
    {
        $payload = json_encode([
            'license_key' => $licenseKey,
            'device_id'   => $deviceInfo['device_id'] ?? '',
            'device_type' => $deviceInfo['device_type'] ?? 'unknown',
            'device_name' => $deviceInfo['device_name'] ?? '',
            'device_model'=> $deviceInfo['device_model'] ?? '',
            'device_os'   => $deviceInfo['device_os'] ?? '',
            'device_os_version' => $deviceInfo['device_os_version'] ?? '',
            'app_version' => $deviceInfo['app_version'] ?? '',
        ]);

        $signature = hash_hmac('sha256', $payload, $this->secret);

        try {
            $response = Http::withHeaders([
                'Content-Type'        => 'application/json',
                'Accept'              => 'application/json',
                'User-Agent'          => config('license.api.user_agent', 'HMS-IPTV/1.0'),
                'X-License-Signature' => $signature,
            ])
            ->timeout($this->timeout)
            ->withBody($payload, 'application/json')
            ->post($this->baseUrl . '/validate');

            $json = $response->json();

            if ($response->successful()) {
                return $json;
            }

            Log::warning('kewirdev.com validation rejected', [
                'status' => $response->status(),
                'body'   => $json,
            ]);

            return [
                'success' => false,
                'message' => $json['error'] ?? $json['message'] ?? 'Remote validation failed',
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('kewirdev.com API connection failed, falling back to local', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'License server unreachable. Falling back to local validation.',
            ];
        } catch (\Exception $e) {
            Log::error('kewirdev.com API error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'License server error. Falling back to local validation.',
            ];
        }
    }
}
