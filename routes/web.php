<?php

use App\Http\Controllers\CameraJsonController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\MonitoringController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MonitoringController::class, 'index']);

Route::get('/cameras.json', CameraJsonController::class);
Route::get('/up', HealthCheckController::class);
