<?php

namespace App\Console\Commands;

use App\Models\StreamTelemetry;
use Illuminate\Console\Command;

class TelemetryPruneCommand extends Command
{
    protected $signature = 'telemetry:prune {--days= : Delete older than N days} {--hours= : Delete older than N hours}';

    protected $description = 'Delete old telemetry records to control database growth';

    public function handle(): int
    {
        $days = $this->option('days');
        $hours = $this->option('hours');

        if ($hours) {
            $cutoff = now()->subHours((int) $hours);
            $label = "{$hours} hours";
        } elseif ($days) {
            $cutoff = now()->subDays((int) $days);
            $label = "{$days} days";
        } else {
            $cutoff = now()->subDays(7);
            $label = "7 days";
        }

        $deleted = StreamTelemetry::where('created_at', '<', $cutoff)->delete();

        $this->info("Deleted {$deleted} telemetry records older than {$label}.");

        return Command::SUCCESS;
    }
}
