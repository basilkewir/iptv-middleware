<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StreamingServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminServerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $servers = StreamingServer::orderBy('name', 'asc')->get();

            return response()->json([
                'success' => true,
                'data' => $servers,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching servers.',
            ], 500);
        }
    }
}
