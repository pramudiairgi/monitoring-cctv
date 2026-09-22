<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CameraJsonController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\TelemetryController;
use Illuminate\Support\Facades\Route;

Route::post('/telemetry', [TelemetryController::class, 'store'])->middleware('throttle:60,1');

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('auth:sanctum');
Route::post('/ingest', [TelemetryController::class, 'ingest'])->middleware(['throttle:60,1', 'auth:sanctum', 'ability:telemetry:submit']);

Route::get('/cameras.json', CameraJsonController::class);
Route::get('/up', HealthCheckController::class);
