<?php

namespace App\Jobs;

use App\Models\StreamTelemetry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessTelemetryJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private array $records
    ) {}

    public function handle(): void
    {
        if (!empty($this->records)) {
            StreamTelemetry::insert($this->records);
        }
    }
}
