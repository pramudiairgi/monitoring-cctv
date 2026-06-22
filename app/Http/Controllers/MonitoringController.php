<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class MonitoringController extends Controller
{
    public function index()
    {
        $path = storage_path('app/public/cameras.json');

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
