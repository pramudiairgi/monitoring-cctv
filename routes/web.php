<?php

use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\TelemetryController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/', [MonitoringController::class, 'index']);

Route::post('/api/telemetry', [TelemetryController::class, 'store']);

Route::get('/up', function () {
    try {
        DB::connection()->getPdo();
        return response()->json(['status' => 'ok', 'database' => 'connected']);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error', 'database' => 'disconnected'], 503);
    }
});
