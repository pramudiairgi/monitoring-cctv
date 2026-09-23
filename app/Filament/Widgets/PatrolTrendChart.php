<?php

namespace App\Filament\Widgets;

use App\Models\PatrolLog;
use Filament\Widgets\ChartWidget;

class PatrolTrendChart extends ChartWidget
{
    protected ?string $heading = 'Patrol Trend';

    protected ?string $description = 'Online vs offline patrol cameras over the last 14 checks';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $logs = PatrolLog::query()
            ->latest('checked_at')
            ->limit(14)
            ->get()
            ->reverse()
            ->values();

        return [
            'datasets' => [
                [
                    'label' => 'Online',
                    'data' => $logs->map(fn (PatrolLog $log) => $log->patrol_online_count ?? $log->online_count ?? 0)->all(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                ],
                [
                    'label' => 'Offline',
                    'data' => $logs->map(fn (PatrolLog $log) => $log->patrol_offline_count ?? $log->offline_count ?? 0)->all(),
                    'borderColor' => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                ],
            ],
            'labels' => $logs->map(fn (PatrolLog $log) => $log->checked_at?->format('d M H:i'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}