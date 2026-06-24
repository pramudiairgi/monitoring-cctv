<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StreamTelemetry extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'stream_telemetry';

    protected $fillable = [
        'camera_id',
        'camera_name',
        'bitrate_kbps',
        'resolution',
        'buffer_health',
        'latency_ms',
        'event_type',
        'error_message',
        'user_agent',
    ];
}
