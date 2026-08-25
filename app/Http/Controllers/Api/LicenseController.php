<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LicenseController extends Controller
{
    private $licenseService;

    public function __construct()
    {
        $this->licenseService = new LicenseService();
    }

    /**
     * Validate license key and get token
     */
    public function validate(Request $request, array $rules = [], array $messages = [], array $attributes = []): JsonResponse
    {
        $input = $request->all();

        $validator = Validator::make($input, [
            'license_key' => 'required|string',
            'device_type' => 'required|string',
            'device_name' => 'sometimes|string',
            'device_model' => 'sometimes|string',
            'device_os' => 'sometimes|string',
            'device_os_version' => 'sometimes|string',
            'app_version' => 'sometimes|string',
            'device_id' => 'sometimes|string',
        ], $messages, $attributes);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        $licenseKey = $validated['license_key'] ?? '';
        $deviceType = $validated['device_type'] ?? '';
        $deviceName = $validated['device_name'] ?? 'Android TV';
        $deviceModel = $validated['device_model'] ?? '';
        $deviceOs = $validated['device_os'] ?? '';
        $deviceOsVersion = $validated['device_os_version'] ?? '';
        $appVersion = $validated['app_version'] ?? '';
        $deviceId = $validated['device_id'] ?? '';

        $deviceInfo = [
            'device_type' => $deviceType,
            'device_name' => $deviceName,
            'device_model' => $deviceModel,
            'device_os' => $deviceOs,
            'device_os_version' => $deviceOsVersion,
            'app_version' => $appVersion,
            'device_id' => $deviceId,
            'ip_address' => request()->ip(),
            'mac_address' => '',
            'metadata' => [],
        ];

        $result = $this->licenseService->validateLicense($licenseKey, $deviceInfo);

        return response()->json($result);
    }

    /**
     * Validate JWT token (for frontend token verification)
     */
    public function validateToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        $result = $this->licenseService->validateToken($validated['token']);

        return response()->json($result);
    }

    /**
     * Refresh JWT token
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        // Decode the token to get license and device info
        $decoded = JWT::decode($validated['token'], new Key(config('license.jwt_secret'), 'HS256'));

        $license = \App\Models\License::find($decoded->license_id);
        $device = \App\Models\LicenseDevice::find($decoded->device_id);

        if (!$license || !$device || !$device->isActive()) {
            return response()->json([
                'success' => false,
                'error' => 'License or device not found or inactive',
            ], 401);
        }

        $token = $this->licenseService->generateToken($license, $device);

        return response()->json([
            'success' => true,
            'token' => $token,
            'expires_at' => now()->addSeconds(config('license.token_expiration'))->toISOString(),
        ]);
    }

    /**
     * Get license info by key
     */
    public function info(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_key' => 'required|string',
        ]);

        $stats = $this->licenseService->getLicenseStats($validated['license_key']);

        return response()->json($stats);
    }

    /**
     * Sync room count
     */
    public function syncRooms(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_key' => 'required|string',
            'device_id' => 'required|string',
            'room_count' => 'required|integer',
        ]);

        $result = $this->licenseService->syncRooms(
            $validated['license_key'],
            $validated['device_id'],
            $validated['room_count']
        );

        return response()->json($result);
    }
}