<?php

namespace App\Console\Commands;

use App\Models\Camera;
use App\Models\Category;
use App\Models\PatrolLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PatrolAlertCommand extends Command
{
    protected $signature = 'patrol:alert {--force : Force alert check regardless of schedule}';

    protected $description = 'Check patrol camera status and send alerts for offline cameras';

    public function handle(): int
    {
        $patrolCategoryId = Category::where('slug', 'patroli')->value('id');

        if (! $patrolCategoryId) {
            $this->warn('Patrol category not found.');

            return Command::SUCCESS;
        }

        $patrolCameras = Camera::where('category_id', $patrolCategoryId)->get();
        $offlineCameras = $patrolCameras->where('status', 'offline')->where('maintenance', false);
        $onlineCount = $patrolCameras->where('status', 'online')->count();
        $offlineCount = $offlineCameras->count();
        $totalPatrol = $patrolCameras->count();

        if ($offlineCount > 0) {
            $cameraNames = $offlineCameras->pluck('name')->implode(', ');
            Log::warning("Patrol Alert: {$offlineCount}/{$totalPatrol} patrol cameras offline", [
                'offline_cameras' => $cameraNames,
                'online_count' => $onlineCount,
                'offline_count' => $offlineCount,
            ]);

            $this->warn("Patrol Alert: {$offlineCount}/{$totalPatrol} patrol cameras offline");
            $this->warn("Offline: {$cameraNames}");
        } else {
            Log::info("Patrol Status: All {$totalPatrol} patrol cameras online", [
                'online_count' => $onlineCount,
            ]);

            $this->info("All {$totalPatrol} patrol cameras are online");
        }

        PatrolLog::create([
            'status' => $offlineCount > 0 ? 'partial' : 'success',
            'total_cameras' => $totalPatrol,
            'online_count' => $onlineCount,
            'offline_count' => $offlineCount,
            'patrol_online_count' => $onlineCount,
            'patrol_offline_count' => $offlineCount,
            'details' => [
                'alert_type' => 'patrol_alert',
                'offline_cameras' => $offlineCameras->pluck('name')->toArray(),
            ],
            'checked_at' => now(),
        ]);

        return Command::SUCCESS;
    }
}
