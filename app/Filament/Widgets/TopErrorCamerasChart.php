<?php

namespace App\Filament\Widgets;

use App\Models\StreamTelemetry;
use Filament\Widgets\ChartWidget;

class TopErrorCamerasChart extends ChartWidget
{
    protected static ?string $heading = 'Top 5 Kamera dengan Error Terbanyak';
    protected static ?string $pollingInterval = '10s';

    protected function getData(): array
    {
        $data = StreamTelemetry::whereDate('created_at', today())
            ->where('event_type', 'error')
            ->selectRaw('camera_name, count(*) as count')
            ->groupBy('camera_name')
            ->orderByDesc('count')
            ->limit(5)
            ->pluck('count', 'camera_name')
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Total Error',
                    'data' => array_values($data),
                    'backgroundColor' => '#ff6384',
                ],
            ],
            'labels' => array_keys($data),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
