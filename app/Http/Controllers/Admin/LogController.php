<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StreamingLog;
use App\Models\SystemLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LogController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $type = $request->input('type', 'all');
        $level = $request->input('level');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $systemLogs = SystemLog::query();
        $streamingLogs = StreamingLog::query();

        if ($type === 'system' || $type === 'all') {
            if ($level) {
                $systemLogs->where('level', $level);
            }
            if ($dateFrom) {
                $systemLogs->where('created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $systemLogs->where('created_at', '<=', $dateTo);
            }
        }

        if ($type === 'streaming' || $type === 'all') {
            if ($dateFrom) {
                $streamingLogs->where('created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $streamingLogs->where('created_at', '<=', $dateTo);
            }
        }

        $logs = match ($type) {
            'system' => $systemLogs->latest()->paginate($request->input('per_page', 15)),
            'streaming' => $streamingLogs->latest()->paginate($request->input('per_page', 15)),
            default => collect(),
        };

        $stats = [
            'total_system_logs' => SystemLog::count(),
            'total_streaming_logs' => StreamingLog::count(),
            'error_count' => SystemLog::where('level', 'error')->count(),
            'warning_count' => SystemLog::where('level', 'warning')->count(),
        ];

        if ($type !== 'all') {
            if ($request->expectsJson()) {
                return response()->json(['data' => $logs, 'stats' => $stats]);
            }

            return Inertia::render('Admin/Reports/Logs', [
                'logs' => $logs,
                'stats' => $stats,
                'filters' => $request->only(['type', 'level', 'date_from', 'date_to']),
            ]);
        }

        $systemLogsData = $systemLogs->latest()->paginate($request->input('per_page', 15));
        $streamingLogsData = $streamingLogs->latest()->paginate($request->input('per_page', 15));

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'system' => $systemLogsData,
                    'streaming' => $streamingLogsData,
                ],
                'stats' => $stats,
            ]);
        }

        return Inertia::render('Admin/Reports/Logs', [
            'systemLogs' => $systemLogsData,
            'streamingLogs' => $streamingLogsData,
            'stats' => $stats,
            'filters' => $request->only(['type', 'level', 'date_from', 'date_to']),
        ]);
    }
}
