<?php

namespace App\Http\Controllers;

use App\Services\CameraExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class MonitoringController extends Controller
{
    public function index(CameraExport $export)
    {
        $path = storage_path('app/public/cameras.json');

        if (!File::exists($path)) {
            try {
                $export->handle();
            } catch (\Exception $e) {
                /* DB not ready yet — return empty view */
            }
        }

        if (!File::exists($path)) {
            return view('monitoring', ['cameras' => [], 'categories' => []]);
        }

        $data = json_decode(File::get($path), true);

        return view('monitoring', [
            'cameras' => $data['cameras'] ?? [],
            'categories' => $data['categories'] ?? [],
        ]);
    }
}
