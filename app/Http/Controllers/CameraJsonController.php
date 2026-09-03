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
        $data = Cache::remember('cameras_json', 5, function () use ($export) {
            $path = storage_path('app/public/cameras.json');
            if (! File::exists($path)) {
                try {
                    $export->handle();
                } catch (Exception $e) {
                    /* DB not ready yet */
                }
            }
            if (! File::exists($path)) {
                return null;
            }

            return json_decode(File::get($path), true);
        });

        if ($data === null) {
            return response()->json(['cameras' => [], 'categories' => []], 404);
        }

        return response()->json($data, 200, [
            'Cache-Control' => 'public, max-age=5',
        ]);
    }
}
