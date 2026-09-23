<?php

namespace App\Filament\Widgets;

use App\Models\Camera;
use App\Models\Category;
use App\Models\PatrolLog;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PatrolStatsWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Patrol Overview';

    protected ?string $description = 'Real-time patrol camera status';

    protected int|string|array $columnSpan = 'full';

    public function getStats(): array
    {
        $patrolCategoryId = Category::where('slug', 'patroli')->value('id');
        $total = $patrolCategoryId ? Camera::where('category_id', $patrolCategoryId)->count() : 0;
        $online = $patrolCategoryId ? Camera::where('category_id', $patrolCategoryId)->where('status', 'online')->count() : 0;
        $offline = $patrolCategoryId ? Camera::where('category_id', $patrolCategoryId)->where('status', 'offline')->count() : 0;
        $lastPatrol = PatrolLog::latest('checked_at')->first();
        $lastCheck = $lastPatrol ? $lastPatrol->checked_at->diffForHumans() : 'Never';

        return [
            Stat::make('Total Patrol Cameras', $total)
                ->description('Patrol category')
                ->descriptionIcon('heroicon-m-video-camera')
                ->icon('heroicon-o-video-camera')
                ->color('info'),
            Stat::make('Patrol Online', $online)
                ->description('Streaming normally')
                ->descriptionIcon('heroicon-m-signal')
                ->icon('heroicon-o-signal')
                ->color('success'),
            Stat::make('Patrol Offline', $offline)
                ->description('Needs attention')
                ->descriptionIcon('heroicon-m-signal-slash')
                ->icon('heroicon-o-signal-slash')
                ->color($offline > 0 ? 'danger' : 'success'),
            Stat::make('Last Check', $lastCheck)
                ->description('Patrol log timestamp')
                ->descriptionIcon('heroicon-m-clock')
                ->icon('heroicon-o-clock')
                ->color('secondary'),
        ];
    }
}
