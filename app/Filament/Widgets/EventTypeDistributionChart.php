<?php

namespace App\Filament\Widgets;

use App\Models\StreamTelemetry;
use Filament\Widgets\ChartWidget;

class EventTypeDistributionChart extends ChartWidget
{
    protected static ?string $heading = 'Distribusi Event Type';
    protected static ?string $pollingInterval = '10s';

    protected function getData(): array
    {
        $data = StreamTelemetry::whereDate('created_at', today())
            ->selectRaw('event_type, count(*) as count')
            ->groupBy('event_type')
            ->pluck('count', 'event_type')
            ->toArray();

        return [
            'datasets' => [
                [
                    'data' => array_values($data),
                    'backgroundColor' => ['#36a2eb', '#ff6384', '#ffcd56', '#4bc0c0', '#9966ff', '#ff9f40'],
                ],
            ],
            'labels' => array_keys($data),
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
