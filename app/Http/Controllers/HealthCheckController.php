<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->getPdo();

            return response()->json(['status' => 'ok', 'database' => 'connected']);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'database' => 'disconnected'], 503);
        }
    }
}
