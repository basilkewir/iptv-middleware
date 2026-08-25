<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LicenseDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        try {
            $license = $request->input('license');
            $device = $request->input('device');

            return response()->json([
                'success' => true,
                'data' => [
                    'hotel' => [
                        'hotel_id' => $license->hotel_id,
                        'hotel_name' => $license->hotel_name,
                        'license_type' => $license->license_type,
                        'features' => $request->input('license_features'),
                    ],
                    'device' => [
                        'id' => $device->id,
                        'name' => $device->device_name,
                        'type' => $device->device_type,
                        'model' => $device->device_model,
                        'os' => trim($device->device_os . ' ' . $device->device_os_version),
                        'app_version' => $device->app_version,
                        'last_seen_at' => $device->last_seen_at?->toISOString(),
                    ],
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching profile.',
            ], 500);
        }
    }

    public function update(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'device_name' => 'sometimes|string|max:255',
                'device_model' => 'sometimes|string|max:255',
                'app_version' => 'sometimes|string|max:50',
                'mac_address' => 'sometimes|string|max:50',
            ]);

            /** @var LicenseDevice $device */
            $device = $request->input('device');

            $device->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully.',
                'data' => [
                    'device' => [
                        'id' => $device->id,
                        'name' => $device->device_name,
                        'type' => $device->device_type,
                        'model' => $device->device_model,
                        'app_version' => $device->app_version,
                    ],
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating profile.',
            ], 500);
        }
    }
}
