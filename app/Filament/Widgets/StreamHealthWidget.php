<?php

namespace App\Filament\Widgets;

use App\Models\StreamTelemetry;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StreamHealthWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Stream Health';

    protected ?string $description = 'Telemetry from the last 24 hours';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $since = now()->subDay();

        $avgLatency = (int) StreamTelemetry::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('latency_ms')
            ->avg('latency_ms');

        $avgBitrate = (int) StreamTelemetry::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('bitrate_kbps')
            ->avg('bitrate_kbps');

        $errorCount = StreamTelemetry::query()
            ->where('created_at', '>=', $since)
            ->where('event_type', 'error')
            ->count();

        $hasData = StreamTelemetry::query()->where('created_at', '>=', $since)->exists();

        return [
            Stat::make('Avg Latency', $hasData ? "{$avgLatency} ms" : 'No data')
                ->description('Last 24 hours')
                ->descriptionIcon('heroicon-m-bolt')
                ->icon('heroicon-o-clock')
                ->color($hasData ? ($avgLatency > 2000 ? 'warning' : 'success') : 'gray'),
            Stat::make('Avg Bitrate', $hasData ? "{$avgBitrate} kbps" : 'No data')
                ->description('Last 24 hours')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->icon('heroicon-o-signal')
                ->color($hasData ? 'info' : 'gray'),
            Stat::make('Stream Errors', (string) $errorCount)
                ->description('Last 24 hours')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($errorCount > 0 ? 'danger' : 'success'),
        ];
    }
}