<?php

namespace App\Jobs;

use App\Models\StreamTelemetry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessTelemetryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        private array $records
    ) {}

    public function handle(): void
    {
        if (!empty($this->records)) {
            StreamTelemetry::insert($this->records);
        }
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('ProcessTelemetryJob failed', [
            'records_count' => count($this->records),
            'exception' => $exception?->getMessage(),
        ]);
    }
}
