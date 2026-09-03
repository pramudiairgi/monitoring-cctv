<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Setting;
use App\Services\CameraExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class MonitoringController extends Controller
{
    public function index(CameraExport $export)
    {
        $data = Cache::remember('cameras_json', 60, function () use ($export) {
            $path = storage_path('app/public/cameras.json');

            if (!File::exists($path)) {
                try {
                    $export->handle();
                } catch (Exception $e) {
                    report($e);
                    Log::warning('Camera export failed, serving empty monitoring view', ['exception' => $e->getMessage()]);
                }
            }

            if (!File::exists($path)) {
                return null;
            }

            return json_decode(File::get($path), true);
        });

        return view('monitoring', [
            'cameras' => $data['cameras'] ?? [],
            'categories' => $data['categories'] ?? [],
            'playbackSettings' => [
                'playback_max_desktop' => (int) Setting::get('playback_max_desktop', 9),
                'playback_max_mobile_landscape' => (int) Setting::get('playback_max_mobile_landscape', 6),
                'playback_max_mobile_portrait' => (int) Setting::get('playback_max_mobile_portrait', 4),
                'playback_stagger_ms' => (int) Setting::get('playback_stagger_ms', 350),
                'playback_priority_category' => (string) Setting::get('playback_priority_category', 'patroli'),
            ],
        ]);
    }
}
