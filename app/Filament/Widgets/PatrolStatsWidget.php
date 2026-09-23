<?php

namespace App\Filament\Widgets;

use App\Models\Camera;
use App\Models\Category;
use App\Models\PatrolLog;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PatrolStatsWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Camera Overview';

    protected ?string $description = 'Real-time camera status across all categories';

    protected int|string|array $columnSpan = 'full';

    public function getStats(): array
    {
        $total = Camera::count();
        $online = Camera::where('status', 'online')->count();
        $offline = Camera::where('status', 'offline')->count();
        $maintenance = Camera::where('maintenance', true)->count();
        $patrolCategoryId = Category::where('slug', 'patroli')->value('id');
        $patrolOnline = $patrolCategoryId
            ? Camera::where('category_id', $patrolCategoryId)->where('status', 'online')->count()
            : 0;
        $lastPatrol = PatrolLog::latest('checked_at')->first();
        $lastCheck = $lastPatrol ? $lastPatrol->checked_at->diffForHumans() : 'Never';

        return [
            Stat::make('Total Cameras', $total)
                ->description('All categories')
                ->descriptionIcon('heroicon-m-video-camera')
                ->icon('heroicon-o-video-camera')
                ->color('info'),
            Stat::make('Online', $online)
                ->description('Streaming normally')
                ->descriptionIcon('heroicon-m-signal')
                ->icon('heroicon-o-signal')
                ->color('success'),
            Stat::make('Offline', $offline)
                ->description('Needs attention')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),
            Stat::make('Maintenance', $maintenance)
                ->description('Under maintenance')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('warning'),
            Stat::make('Patrol Online', $patrolOnline)
                ->description('Patrol category')
                ->descriptionIcon('heroicon-m-shield-check')
                ->icon('heroicon-o-shield-check')
                ->color('success'),
            Stat::make('Last Check', $lastCheck)
                ->description('Patrol log timestamp')
                ->descriptionIcon('heroicon-m-clock')
                ->icon('heroicon-o-clock')
                ->color('gray'),
        ];
    }
}
