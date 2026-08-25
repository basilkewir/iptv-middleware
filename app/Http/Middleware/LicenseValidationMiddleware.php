<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\LicenseService;
use App\Services\AdvancedLicenseSecurityService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class LicenseValidationMiddleware
{
    private $licenseService;
    private $securityService;

    public function __construct(LicenseService $licenseService, AdvancedLicenseSecurityService $securityService)
    {
        $this->licenseService = $licenseService;
        $this->securityService = $securityService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): \Symfony\Component\HttpFoundation\Response
    {
        // Skip validation in development if bypass is enabled
        if (config('license.development.bypass_validation', false) && app()->environment('local')) {
            return $next($request);
        }

        // Extract token from Authorization header
        $token = $this->extractToken($request);
        
        if (!$token) {
            return $this->unauthorizedResponse('Missing authorization token');
        }

        // Validate token
        $tokenValidation = $this->licenseService->validateToken($token);
        
        if (!$tokenValidation['valid']) {
            return $this->unauthorizedResponse($tokenValidation['error']);
        }

        $license = $tokenValidation['license'];
        $device = $tokenValidation['device'];

        // Merge device info into request so security checks (fingerprint validation)
        // work even for GET requests that carry no body/params
        $request->merge([
            'device_id' => $device->device_id ?: (string) $device->id,
            'device_type' => $device->device_type,
            'device_model' => $device->device_model,
        ]);

        // Perform security checks
        $securityCheck = $this->securityService->performSecurityChecks($request);
        if (!$securityCheck['passed']) {
            Log::warning('License middleware security check failed', [
                'license_id' => $license->id,
                'device_id' => $device->id,
                'ip' => $request->ip(),
                'reason' => $securityCheck['reason']
            ]);
            
            return $this->unauthorizedResponse('Security validation failed');
        }

        // Check for suspicious activity
        $suspiciousActivities = $this->licenseService->detectSuspiciousActivity($license);
        if (!empty($suspiciousActivities)) {
            Log::warning('Suspicious license activity detected', [
                'license_id' => $license->id,
                'activities' => $suspiciousActivities,
                'ip' => $request->ip()
            ]);
            
            // For now, just log but don't block - you might want to block in production
        }

        // Add license and device info to request
        $request->merge([
            'license' => $license,
            'device' => $device,
            'license_features' => $tokenValidation['features']
        ]);

        // Update device last seen
        $device->updateLastSeen();

        return $next($request);
    }

    /**
     * Extract token from request
     */
    private function extractToken(Request $request): ?string
    {
        $authHeader = $request->header('Authorization');
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        return substr($authHeader, 7);
    }

    /**
     * Return unauthorized response
     */
    private function unauthorizedResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => $message,
            'code' => 'UNAUTHORIZED',
            'timestamp' => now()->toISOString()
        ], 401);
    }
}