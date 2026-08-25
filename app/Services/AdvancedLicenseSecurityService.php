<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\License;
use App\Models\LicenseDevice;
use App\Models\LicenseValidationLog;

class AdvancedLicenseSecurityService extends LicenseSecurityService
{
    /**
     * Perform comprehensive security checks with advanced features
     */
    public function performAdvancedSecurityChecks(Request $request): array
    {
        $basicChecks = parent::performSecurityChecks($request);
        
        $advancedChecks = [
            'root_detection' => $this->detectRootedDevice($request),
            'emulator_detection' => $this->detectEmulator($request),
            'debugger_detection' => $this->detectDebugger($request),
            'vm_detection' => $this->detectVirtualMachine($request),
            'tamper_detection' => $this->detectTampering($request),
            'time_manipulation' => $this->detectTimeManipulation($request),
            'network_analysis' => $this->analyzeNetworkBehavior($request),
            'behavioral_analysis' => $this->analyzeBehavioralPatterns($request)
        ];

        $allChecks = array_merge($basicChecks['checks'], $advancedChecks);
        $passed = collect($allChecks)->every(fn($check) => $check['passed']);
        $failedChecks = collect($allChecks)->filter(fn($check) => !$check['passed'])->keys();

        return [
            'passed' => $passed,
            'checks' => $allChecks,
            'failed_checks' => $failedChecks->toArray(),
            'risk_score' => $this->calculateRiskScore($allChecks),
            'reason' => $failedChecks->isEmpty() ? null : 'Failed advanced security checks: ' . $failedChecks->implode(', ')
        ];
    }

    /**
     * Detect rooted/jailbroken devices
     */
    private function detectRootedDevice(Request $request): array
    {
        if (!config('license.security.detect_root', true)) {
            return ['passed' => true, 'reason' => null];
        }

        $deviceInfo = $request->input('device_info', []);
        $suspiciousIndicators = [];

        $rootIndicators = [
            'su_binary' => $deviceInfo['has_su_binary'] ?? false,
            'root_apps' => $deviceInfo['root_apps_detected'] ?? false,
            'system_modified' => $deviceInfo['system_partition_modified'] ?? false,
            'bootloader_unlocked' => $deviceInfo['bootloader_unlocked'] ?? false,
            'custom_rom' => $deviceInfo['custom_rom_detected'] ?? false
        ];

        foreach ($rootIndicators as $indicator => $detected) {
            if ($detected) {
                $suspiciousIndicators[] = $indicator;
            }
        }

        $systemProps = $deviceInfo['system_properties'] ?? [];
        $suspiciousProps = [
            'ro.debuggable' => '1',
            'ro.secure' => '0',
            'service.adb.root' => '1'
        ];

        foreach ($suspiciousProps as $prop => $suspiciousValue) {
            if (isset($systemProps[$prop]) && $systemProps[$prop] === $suspiciousValue) {
                $suspiciousIndicators[] = "suspicious_prop_{$prop}";
            }
        }

        if (!empty($suspiciousIndicators)) {
            Log::warning('Root detection triggered', [
                'ip' => $request->ip(),
                'device_id' => $request->input('device_id'),
                'indicators' => $suspiciousIndicators
            ]);

            return [
                'passed' => false, 
                'reason' => 'Rooted device detected',
                'indicators' => $suspiciousIndicators
            ];
        }

        return ['passed' => true, 'reason' => null];
    }

    /**
     * Detect emulators
     */
    private function detectEmulator(Request $request): array
    {
        if (!config('license.security.block_emulators', true)) {
            return ['passed' => true, 'reason' => null];
        }

        $deviceInfo = $request->input('device_info', []);
        $emulatorIndicators = [];

        $deviceModel = strtolower($deviceInfo['device_model'] ?? '');
        $manufacturer = strtolower($deviceInfo['manufacturer'] ?? '');

        $emulatorPatterns = [
            'android sdk built for x86',
            'sdk_gphone',
            'emulator',
            'simulator',
            'vbox',
            'virtualbox',
            'vmware',
            'bluestacks',
            'nox',
            'memu',
            'ldplayer',
            'genymotion'
        ];

        foreach ($emulatorPatterns as $pattern) {
            if (strpos($deviceModel, $pattern) !== false || strpos($manufacturer, $pattern) !== false) {
                $emulatorIndicators[] = "model_pattern_{$pattern}";
            }
        }

        $hardware = $deviceInfo['hardware'] ?? [];
        if (isset($hardware['cpu_abi']) && strpos($hardware['cpu_abi'], 'x86') !== false) {
            $emulatorIndicators[] = 'x86_architecture';
        }

        $emulatorFiles = $deviceInfo['emulator_files'] ?? [];
        if (!empty($emulatorFiles)) {
            $emulatorIndicators = array_merge($emulatorIndicators, $emulatorFiles);
        }

        if (!empty($emulatorIndicators)) {
            Log::warning('Emulator detected', [
                'ip' => $request->ip(),
                'device_id' => $request->input('device_id'),
                'indicators' => $emulatorIndicators
            ]);

            return [
                'passed' => false, 
                'reason' => 'Emulator detected',
                'indicators' => $emulatorIndicators
            ];
        }

        return ['passed' => true, 'reason' => null];
    }

    /**
     * Detect debuggers
     */
    private function detectDebugger(Request $request): array
    {
        if (!config('license.security.detect_debugger', true)) {
            return ['passed' => true, 'reason' => null];
        }

        $deviceInfo = $request->input('device_info', []);
        $debuggerIndicators = [];

        $debugFlags = $deviceInfo['debug_flags'] ?? [];
        $suspiciousFlags = [
            'debugger_attached',
            'debug_mode_enabled',
            'developer_options_enabled',
            'usb_debugging_enabled',
            'adb_enabled'
        ];

        foreach ($suspiciousFlags as $flag) {
            if (!empty($debugFlags[$flag])) {
                $debuggerIndicators[] = $flag;
            }
        }

        $installedApps = $deviceInfo['installed_apps'] ?? [];
        $debuggingTools = [
            'com.android.development',
            'com.android.ddms',
            'com.android.hierarchyviewer',
            'com.android.traceview'
        ];

        foreach ($debuggingTools as $tool) {
            if (in_array($tool, $installedApps)) {
                $debuggerIndicators[] = "debug_tool_{$tool}";
            }
        }

        if (!empty($debuggerIndicators)) {
            Log::warning('Debugger detected', [
                'ip' => $request->ip(),
                'device_id' => $request->input('device_id'),
                'indicators' => $debuggerIndicators
            ]);

            return [
                'passed' => false, 
                'reason' => 'Debugger detected',
                'indicators' => $debuggerIndicators
            ];
        }

        return ['passed' => true, 'reason' => null];
    }

    /**
     * Detect virtual machines
     */
    private function detectVirtualMachine(Request $request): array
    {
        if (!config('license.security.detect_vm', true)) {
            return ['passed' => true, 'reason' => null];
        }

        $deviceInfo = $request->input('device_info', []);
        $vmIndicators = [];

        $hardware = $deviceInfo['hardware'] ?? [];

        $vmPatterns = [
            'manufacturer' => ['vmware', 'virtualbox', 'qemu', 'bochs', 'parallels'],
            'model' => ['virtual', 'vm', 'emulated'],
            'board' => ['unknown', 'virtual'],
            'brand' => ['generic']
        ];

        foreach ($vmPatterns as $field => $patterns) {
            $value = strtolower($hardware[$field] ?? '');
            foreach ($patterns as $pattern) {
                if (strpos($value, $pattern) !== false) {
                    $vmIndicators[] = "{$field}_{$pattern}";
                }
            }
        }

        $systemProps = $deviceInfo['system_properties'] ?? [];
        $vmProps = [
            'ro.product.model' => ['sdk', 'emulator', 'virtual'],
            'ro.kernel.qemu' => ['1'],
            'ro.hardware' => ['goldfish', 'ranchu']
        ];

        foreach ($vmProps as $prop => $suspiciousValues) {
            $value = strtolower($systemProps[$prop] ?? '');
            foreach ($suspiciousValues as $suspicious) {
                if (strpos($value, $suspicious) !== false) {
                    $vmIndicators[] = "prop_{$prop}_{$suspicious}";
                }
            }
        }

        if (!empty($vmIndicators)) {
            Log::warning('Virtual machine detected', [
                'ip' => $request->ip(),
                'device_id' => $request->input('device_id'),
                'indicators' => $vmIndicators
            ]);

            return [
                'passed' => false, 
                'reason' => 'Virtual machine detected',
                'indicators' => $vmIndicators
            ];
        }

        return ['passed' => true, 'reason' => null];
    }

    /**
     * Detect tampering attempts
     */
    private function detectTampering(Request $request): array
    {
        $deviceInfo = $request->input('device_info', []);
        $tamperIndicators = [];

        $appSignature = $deviceInfo['app_signature'] ?? '';
        $expectedSignature = config('license.security.expected_app_signature');
        
        if ($expectedSignature && $appSignature !== $expectedSignature) {
            $tamperIndicators[] = 'invalid_app_signature';
        }

        $integrityCheck = $deviceInfo['integrity_check'] ?? [];
        if (!empty($integrityCheck['modified_files'])) {
            $tamperIndicators[] = 'modified_app_files';
        }

        $hookingFrameworks = $deviceInfo['hooking_frameworks'] ?? [];
        $knownFrameworks = ['xposed', 'frida', 'substrate', 'cydia'];
        
        foreach ($knownFrameworks as $framework) {
            if (in_array($framework, $hookingFrameworks)) {
                $tamperIndicators[] = "hooking_framework_{$framework}";
            }
        }

        if (!empty($tamperIndicators)) {
            Log::warning('Tampering detected', [
                'ip' => $request->ip(),
                'device_id' => $request->input('device_id'),
                'indicators' => $tamperIndicators
            ]);

            return [
                'passed' => false, 
                'reason' => 'App tampering detected',
                'indicators' => $tamperIndicators
            ];
        }

        return ['passed' => true, 'reason' => null];
    }

    /**
     * Detect time manipulation
     */
    private function detectTimeManipulation(Request $request): array
    {
        $deviceInfo = $request->input('device_info', []);
        $deviceTime = $deviceInfo['device_time'] ?? null;
        
        if (!$deviceTime) {
            return ['passed' => true, 'reason' => null];
        }

        $serverTime = time();
        $deviceTimestamp = strtotime($deviceTime);
        $timeDifference = abs($serverTime - $deviceTimestamp);

        // Allow 5 minutes difference for network latency and clock drift
        $maxAllowedDifference = 300;

        if ($timeDifference > $maxAllowedDifference) {
            Log::warning('Time manipulation detected', [
                'ip' => $request->ip(),
                'device_id' => $request->input('device_id'),
                'server_time' => $serverTime,
                'device_time' => $deviceTimestamp,
                'difference' => $timeDifference
            ]);

            return [
                'passed' => false, 
                'reason' => 'Time manipulation detected',
                'time_difference' => $timeDifference
            ];
        }

        return ['passed' => true, 'reason' => null];
    }

    /**
     * Analyze network behavior
     */
    private function analyzeNetworkBehavior(Request $request): array
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        
        $suspiciousPatterns = [];

        // Check for VPN/Proxy indicators
        if ($this->isVPNOrProxy($ip)) {
            $suspiciousPatterns[] = 'vpn_or_proxy_detected';
        }

        // Check for TOR exit nodes
        if ($this->isTorExitNode($ip)) {
            $suspiciousPatterns[] = 'tor_exit_node';
        }

        // Check request frequency
        $requestCount = Cache::get("request_count:{$ip}", 0);
        if ($requestCount > 100) { // More than 100 requests per hour
            $suspiciousPatterns[] = 'high_request_frequency';
        }

        if (!empty($suspiciousPatterns)) {
            Log::warning('Suspicious network behavior', [
                'ip' => $ip,
                'patterns' => $suspiciousPatterns,
                'user_agent' => $userAgent
            ]);

            return [
                'passed' => false, 
                'reason' => 'Suspicious network behavior',
                'patterns' => $suspiciousPatterns
            ];
        }

        return ['passed' => true, 'reason' => null];
    }

    /**
     * Analyze behavioral patterns
     */
    private function analyzeBehavioralPatterns(Request $request): array
    {
        $deviceId = $request->input('device_id');
        $behaviorKey = "device_behavior:{$deviceId}";
        $behavior = Cache::get($behaviorKey, []);

        $suspiciousPatterns = [];

        // Check for rapid successive validations
        $recentValidations = array_filter($behavior['validations'] ?? [], function($timestamp) {
            return $timestamp > (time() - 300); // Last 5 minutes
        });

        if (count($recentValidations) > 10) {
            $suspiciousPatterns[] = 'rapid_validations';
        }

        // Check for validation from multiple IPs
        $recentIPs = array_unique(array_column($behavior['recent_ips'] ?? [], 'ip'));
        if (count($recentIPs) > 5) {
            $suspiciousPatterns[] = 'multiple_ip_addresses';
        }

        // Update behavior tracking
        $behavior['validations'][] = time();
        $behavior['recent_ips'][] = [
            'ip' => $request->ip(),
            'timestamp' => time()
        ];

        // Keep only recent data
        $behavior['validations'] = array_filter($behavior['validations'], function($timestamp) {
            return $timestamp > (time() - 3600); // Last hour
        });
        $behavior['recent_ips'] = array_filter($behavior['recent_ips'], function($entry) {
            return $entry['timestamp'] > (time() - 3600); // Last hour
        });

        Cache::put($behaviorKey, $behavior, now()->addHours(24));

        if (!empty($suspiciousPatterns)) {
            Log::warning('Suspicious behavioral patterns', [
                'device_id' => $deviceId,
                'patterns' => $suspiciousPatterns,
                'ip' => $request->ip()
            ]);

            return [
                'passed' => false, 
                'reason' => 'Suspicious behavioral patterns',
                'patterns' => $suspiciousPatterns
            ];
        }

        return ['passed' => true, 'reason' => null];
    }

    /**
     * Calculate risk score based on security checks
     */
    private function calculateRiskScore(array $checks): int
    {
        $riskScore = 0;
        $weights = [
            'root_detection' => 30,
            'emulator_detection' => 25,
            'debugger_detection' => 20,
            'vm_detection' => 20,
            'tamper_detection' => 35,
            'time_manipulation' => 15,
            'network_analysis' => 10,
            'behavioral_analysis' => 15,
            'ip_reputation' => 10,
            'user_agent' => 5
        ];

        foreach ($checks as $checkName => $result) {
            if (!$result['passed'] && isset($weights[$checkName])) {
                $riskScore += $weights[$checkName];
            }
        }

        return min($riskScore, 100); // Cap at 100
    }

    /**
     * Check if IP is VPN or Proxy
     */
    private function isVPNOrProxy(string $ip): bool
    {
        $vpnRanges = Cache::get('known_vpn_ranges', []);
        
        foreach ($vpnRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP is TOR exit node
     */
    private function isTorExitNode(string $ip): bool
    {
        $torExitNodes = Cache::get('tor_exit_nodes', []);
        return in_array($ip, $torExitNodes);
    }

    /**
     * Check if IP is in range
     */
    private function ipInRange(string $ip, string $range): bool
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        list($subnet, $bits) = explode('/', $range);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;
        
        return ($ip & $mask) === $subnet;
    }

    /**
     * Generate device challenge for additional verification
     */
    public function generateDeviceChallenge(string $deviceId): array
    {
        $challenge = [
            'challenge_id' => uniqid('challenge_', true),
            'timestamp' => time(),
            'device_id' => $deviceId,
            'nonce' => bin2hex(random_bytes(16)),
            'operations' => [
                'compute_hash' => [
                    'algorithm' => 'sha256',
                    'input' => bin2hex(random_bytes(32))
                ],
                'device_info' => [
                    'required_fields' => [
                        'device_model',
                        'android_version',
                        'build_fingerprint',
                        'hardware_serial'
                    ]
                ]
            ]
        ];

        // Store challenge for verification
        Cache::put("device_challenge:{$challenge['challenge_id']}", $challenge, now()->addMinutes(5));

        return $challenge;
    }

    /**
     * Verify device challenge response
     */
    public function verifyDeviceChallenge(string $challengeId, array $response): bool
    {
        $challenge = Cache::get("device_challenge:{$challengeId}");
        
        if (!$challenge) {
            return false;
        }

        // Verify hash computation
        $expectedHash = hash('sha256', $challenge['operations']['compute_hash']['input']);
        if ($response['computed_hash'] !== $expectedHash) {
            return false;
        }

        // Verify device info
        $requiredFields = $challenge['operations']['device_info']['required_fields'];
        foreach ($requiredFields as $field) {
            if (!isset($response['device_info'][$field])) {
                return false;
            }
        }

        // Clean up challenge
        Cache::forget("device_challenge:{$challengeId}");

        return true;
    }

    /**
     * Create security report
     */
    public function createSecurityReport(License $license): array
    {
        $devices = $license->devices()->with('validationLogs')->get();
        $validationLogs = $license->validationLogs()->recent(24)->get();

        $report = [
            'license_id' => $license->id,
            'license_key' => $license->license_key,
            'generated_at' => now()->toISOString(),
            'summary' => [
                'total_devices' => $devices->count(),
                'active_devices' => $devices->where('status', 'active')->count(),
                'blocked_devices' => $devices->where('status', 'blocked')->count(),
                'total_validations_24h' => $validationLogs->count(),
                'failed_validations_24h' => $validationLogs->where('status', 'failed')->count(),
                'risk_score' => $this->calculateLicenseRiskScore($license)
            ],
            'devices' => $devices->map(function ($device) {
                return [
                    'device_id' => $device->device_id,
                    'device_type' => $device->device_type,
                    'status' => $device->status,
                    'first_activated_at' => $device->first_activated_at,
                    'last_seen_at' => $device->last_seen_at,
                    'activation_count' => $device->activation_count,
                    'risk_indicators' => $this->getDeviceRiskIndicators($device)
                ];
            }),
            'security_events' => $validationLogs->where('status', '!=', 'success')->map(function ($log) {
                return [
                    'timestamp' => $log->validated_at,
                    'type' => $log->validation_type,
                    'status' => $log->status,
                    'ip_address' => $log->ip_address,
                    'error_message' => $log->error_message
                ];
            })
        ];

        return $report;
    }

    /**
     * Calculate license risk score
     */
    private function calculateLicenseRiskScore(License $license): int
    {
        $riskFactors = 0;

        // Device limit exceeded
        if ($license->current_devices > $license->max_devices) {
            $riskFactors += 30;
        }

        // Recent failed validations
        $failedValidations = $license->validationLogs()
            ->where('status', 'failed')
            ->where('validated_at', '>=', now()->subHours(24))
            ->count();

        if ($failedValidations > 10) {
            $riskFactors += 25;
        }

        // Multiple IP addresses
        $uniqueIPs = $license->validationLogs()
            ->where('validated_at', '>=', now()->subHours(24))
            ->distinct('ip_address')
            ->count();

        if ($uniqueIPs > 5) {
            $riskFactors += 20;
        }

        // Blocked devices
        $blockedDevices = $license->devices()->where('status', 'blocked')->count();
        if ($blockedDevices > 0) {
            $riskFactors += 15;
        }

        return min($riskFactors, 100);
    }

    /**
     * Get device risk indicators
     */
    private function getDeviceRiskIndicators(LicenseDevice $device): array
    {
        $indicators = [];

        // Check for suspicious activation patterns
        if ($device->activation_count > 100) {
            $indicators[] = 'high_activation_count';
        }

        // Check last seen time
        if ($device->last_seen_at && $device->last_seen_at->diffInHours(now()) > 24) {
            $indicators[] = 'not_seen_recently';
        }

        // Check metadata for security flags
        $metadata = $device->metadata ?? [];
        if (isset($metadata['security_flags'])) {
            $indicators = array_merge($indicators, $metadata['security_flags']);
        }

        return $indicators;
    }
}