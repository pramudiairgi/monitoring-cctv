<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessTelemetryJob;
use Illuminate\Http\Request;

class TelemetryController extends Controller
{
    public function store(Request $request)
    {
        $input = $request->json()->all();
        $events = array_is_list($input) ? $input : [$input];

        if (count($events) > 100) {
            abort(422, 'Too many telemetry events in a single request (maximum 100).');
        }

        $fields = [
            'camera_id', 'camera_name', 'bitrate_kbps', 'resolution',
            'buffer_health', 'latency_ms', 'event_type', 'error_message', 'user_agent',
        ];

        $now = now();
        $records = [];
        foreach ($events as $event) {
            $data = validator($event, [
                'camera_id' => 'nullable|integer|exists:cameras,id',
                'camera_name' => 'nullable|string|max:255',
                'bitrate_kbps' => 'nullable|integer',
                'resolution' => 'nullable|string|max:50',
                'buffer_health' => 'nullable|numeric',
                'latency_ms' => 'nullable|integer',
                'event_type' => 'required|string|max:50',
                'error_message' => 'nullable|string|max:2000',
            ])->validate();

            $userAgent = substr((string) $request->userAgent(), 0, 255);
            $data['user_agent'] = $userAgent !== '' ? $userAgent : null;

            $row = [];
            foreach ($fields as $f) {
                $row[$f] = $data[$f] ?? null;
            }
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
            $records[] = $row;
        }

        if (!empty($records)) {
            ProcessTelemetryJob::dispatch($records);
        }

        return response()->noContent();
    }
}
