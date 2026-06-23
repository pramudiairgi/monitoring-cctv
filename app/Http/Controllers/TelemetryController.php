<?php

namespace App\Http\Controllers;

use App\Models\StreamTelemetry;
use Illuminate\Http\Request;

class TelemetryController extends Controller
{
    public function store(Request $request)
    {
        $input = $request->json()->all();
        $events = array_is_list($input) ? $input : [$input];

        $fields = [
            'camera_id', 'camera_name', 'bitrate_kbps', 'resolution',
            'buffer_health', 'latency_ms', 'event_type', 'error_message', 'user_agent',
        ];

        $records = [];
        foreach ($events as $event) {
            $data = validator($event, [
                'camera_id' => 'nullable|integer',
                'camera_name' => 'nullable|string|max:255',
                'bitrate_kbps' => 'nullable|integer',
                'resolution' => 'nullable|string|max:50',
                'buffer_health' => 'nullable|numeric',
                'latency_ms' => 'nullable|integer',
                'event_type' => 'required|string|max:50',
                'error_message' => 'nullable|string',
            ])->validate();

            $data['user_agent'] = $request->userAgent();

            $row = [];
            foreach ($fields as $f) {
                $row[$f] = $data[$f] ?? null;
            }
            $records[] = $row;
        }

        if (!empty($records)) {
            StreamTelemetry::insert($records);
        }

        return response()->noContent();
    }
}
