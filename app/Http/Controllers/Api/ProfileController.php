<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        try {
            $user = $request->user()->load('profile');

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
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
                'first_name' => 'sometimes|string|max:255',
                'last_name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $request->user()->id,
                'phone' => 'nullable|string|max:50',
                'password' => 'nullable|string|min:8|confirmed',
                'avatar' => 'nullable|string|max:500',
                'country' => 'nullable|string|max:100',
                'language' => 'nullable|string|max:10',
                'timezone' => 'nullable|string|max:50',
                'preferences' => 'nullable|array',
            ]);

            $user = $request->user();

            $userData = collect($validated)->only([
                'first_name', 'last_name', 'email', 'phone',
            ])->toArray();

            if (isset($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            }

            $user->update($userData);

            $profileData = collect($validated)->only([
                'avatar', 'country', 'language', 'timezone', 'preferences',
            ])->toArray();

            if ($user->profile) {
                $user->profile->update($profileData);
            } else {
                $user->profile()->create($profileData);
            }

            $user->load('profile');

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully.',
                'data' => [
                    'user' => $user,
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
