<?php

namespace App\Console\Commands;

use App\Models\StreamTelemetry;
use Illuminate\Console\Command;

class TelemetryPruneCommand extends Command
{
    protected $signature = 'telemetry:prune {--days=7 : Delete telemetry older than N days}';

    protected $description = 'Delete old telemetry records to control database growth';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $deleted = StreamTelemetry::where('created_at', '<', $cutoff)->delete();

        $this->info("Deleted {$deleted} telemetry records older than {$days} days.");

        return Command::SUCCESS;
    }
}
