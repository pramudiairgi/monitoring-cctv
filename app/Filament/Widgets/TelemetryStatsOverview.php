<?php

namespace App\Filament\Widgets;

use App\Models\StreamTelemetry;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TelemetryStatsOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        $today = today();

        $totalToday = StreamTelemetry::whereDate('created_at', $today)->count();
        $errorsToday = StreamTelemetry::whereDate('created_at', $today)
            ->where('event_type', 'error')
            ->count();
        $avgBuffer = StreamTelemetry::whereDate('created_at', $today)
            ->avg('buffer_health');
        $avgLatency = StreamTelemetry::whereDate('created_at', $today)
            ->avg('latency_ms');
        $activeCameras = StreamTelemetry::whereDate('created_at', $today)
            ->distinct('camera_name')
            ->count('camera_name');

        return [
            Stat::make('Total Events Hari Ini', $totalToday),
            Stat::make('Error Events', $errorsToday)
                ->color($errorsToday > 0 ? 'danger' : 'success')
                ->description($errorsToday > 0 ? 'Perlu dicek' : 'Tidak ada error'),
            Stat::make('Rata-rata Buffer Health', number_format($avgBuffer ?? 0, 2))
                ->color(
                    $avgBuffer > 0.5 ? 'success' :
                    ($avgBuffer > 0.2 ? 'warning' : 'danger')
                ),
            Stat::make('Rata-rata Latency', number_format($avgLatency ?? 0, 0) . ' ms'),
            Stat::make('Kamera Aktif Streaming', $activeCameras)
                ->color($activeCameras > 0 ? 'success' : 'warning'),
        ];
    }
}
