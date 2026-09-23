<?php

namespace App\Filament\Widgets;

use App\Models\Camera;
use App\Models\Category;
use App\Models\PatrolLog;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;

class PatrolAlertWidget extends Widget
{
    protected ?string $heading = 'Patrol Alerts';

    protected ?string $description = 'Offline patrol cameras requiring attention';

    protected int|string|array $columnSpan = 'full';

    protected ?int $sorting = 3;

    public function render(): View
    {
        $patrolCategoryId = Category::where('slug', 'patroli')->value('id');
        $offlinePatrolCameras = $patrolCategoryId
            ? Camera::where('category_id', $patrolCategoryId)
                ->where('status', 'offline')
                ->where('maintenance', false)
                ->get()
            : collect();
        $lastPatrol = PatrolLog::latest('checked_at')->first();
        $totalPatrol = $patrolCategoryId ? Camera::where('category_id', $patrolCategoryId)->count() : 0;
        $patrolOnline = $patrolCategoryId ? Camera::where('category_id', $patrolCategoryId)->where('status', 'online')->count() : 0;
        $patrolOffline = $patrolCategoryId ? Camera::where('category_id', $patrolCategoryId)->where('status', 'offline')->count() : 0;

        return view('patrol-alert', [
            'offlineCameras' => $offlinePatrolCameras,
            'lastPatrol' => $lastPatrol,
            'totalPatrol' => $totalPatrol,
            'patrolOnline' => $patrolOnline,
            'patrolOffline' => $patrolOffline,
        ]);
    }
}
