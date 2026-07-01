<?php

namespace App\Filament\Widgets;

use App\Models\StreamTelemetry;
use Filament\Widgets\ChartWidget;

class AverageBitrateChart extends ChartWidget
{
    protected static ?string $heading = 'Rata-rata Bitrate per Kamera (Hari Ini)';
    protected static ?string $pollingInterval = '10s';

    protected function getData(): array
    {
        $data = StreamTelemetry::whereDate('created_at', today())
            ->whereNotNull('bitrate_kbps')
            ->selectRaw('camera_name, avg(bitrate_kbps) as avg_bitrate')
            ->groupBy('camera_name')
            ->orderByDesc('avg_bitrate')
            ->limit(10)
            ->pluck('avg_bitrate', 'camera_name')
            ->toArray();

        $formatted = [];
        foreach ($data as $kbps) {
            $formatted[] = round($kbps, 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Bitrate (kbps)',
                    'data' => $formatted,
                    'backgroundColor' => '#36a2eb',
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
