<?php

namespace App\Filament\Widgets;

use App\Models\Camera;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CameraOverviewWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $total = Camera::count();
        $online = Camera::where('status', 'online')->count();
        $offline = Camera::where('status', 'offline')->count();

        return [
            Stat::make('Total Cameras', $total)
                ->description('Registered cameras')
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
                ->descriptionIcon('heroicon-m-signal-slash')
                ->icon('heroicon-o-signal-slash')
                ->color('danger'),
        ];
    }
}
