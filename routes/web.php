<?php

use App\Http\Controllers\CameraJsonController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\TelemetryController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MonitoringController::class, 'index']);

Route::post('/api/telemetry', [TelemetryController::class, 'store'])->middleware('throttle:60,1');

Route::get('/cameras.json', CameraJsonController::class);

Route::get('/up', HealthCheckController::class);
