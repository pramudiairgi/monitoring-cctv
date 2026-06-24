<?php

use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\TelemetryController;
use App\Services\CameraExport;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

Route::get('/', [MonitoringController::class, 'index']);

Route::post('/api/telemetry', [TelemetryController::class, 'store']);

Route::get('/cameras.json', function (CameraExport $export) {
    $path = storage_path('app/public/cameras.json');
    if (!File::exists($path)) {
        try {
            $export->handle();
        } catch (\Exception $e) {
            /* DB not ready yet */
        }
    }
    if (!File::exists($path)) {
        return response()->json(['cameras' => [], 'categories' => []], 404);
    }
    return response()->json(json_decode(File::get($path), true), 200, [
        'Cache-Control' => 'no-cache',
    ]);
});

Route::get('/up', function () {
    try {
        DB::connection()->getPdo();
        return response()->json(['status' => 'ok', 'database' => 'connected']);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error', 'database' => 'disconnected'], 503);
    }
});
