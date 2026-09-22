<?php

namespace App\Http\Controllers;

use App\Services\CameraExport;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class CameraJsonController extends Controller
{
    public function __invoke(CameraExport $export): JsonResponse
    {
        $path = storage_path('app/public/cameras.json');

        // If file doesn't exist, try to export
        if (! File::exists($path)) {
            try {
                $export->handle();
            } catch (Exception $e) {
                report($e);
            }
        }

        // Still doesn't exist after export attempt
        if (! File::exists($path)) {
            return response()->json(['cameras' => [], 'categories' => []], 200);
        }

        $data = Cache::remember('cameras_json', 5, function () use ($path) {
            return json_decode(File::get($path), true);
        });

        return response()->json($data, 200, [
            'Cache-Control' => 'public, max-age=5',
        ]);
    }
}
