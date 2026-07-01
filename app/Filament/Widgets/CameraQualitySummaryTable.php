<?php

namespace App\Filament\Widgets;

use App\Models\StreamTelemetry;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseTableWidget;

class CameraQualitySummaryTable extends BaseTableWidget
{
    protected static ?string $heading = 'Ringkasan Kualitas per Kamera (Hari Ini)';
    protected static ?string $pollingInterval = '10s';
    protected int | string | array $columnSpan = 'full';

    public function getTableRecordKey(\Illuminate\Database\Eloquent\Model $record): string
    {
        return $record->camera_name ?? spl_object_id($record);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StreamTelemetry::whereDate('created_at', today())
                    ->selectRaw('camera_name')
                    ->selectRaw('avg(bitrate_kbps) as avg_bitrate')
                    ->selectRaw('avg(buffer_health) as avg_buffer')
                    ->selectRaw('avg(latency_ms) as avg_latency')
                    ->selectRaw('count(*) as total_events')
                    ->selectRaw("sum(case when event_type = 'error' then 1 else 0 end) as error_count")
                    ->selectRaw("sum(case when event_type = 'buffering_start' then 1 else 0 end) as buffering_count")
                    ->groupBy('camera_name')
            )
            ->columns([
                Tables\Columns\TextColumn::make('camera_name')
                    ->label('Kamera')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('avg_bitrate')
                    ->label('Rata bitrate')
                    ->suffix(' kbps')
                    ->formatStateUsing(fn ($state) => number_format(round($state ?? 0)))
                    ->sortable(),
                Tables\Columns\TextColumn::make('avg_buffer')
                    ->label('Rata buffer')
                    ->formatStateUsing(fn ($state) => number_format($state ?? 0, 2))
                    ->sortable(),
                Tables\Columns\TextColumn::make('avg_latency')
                    ->label('Rata latency')
                    ->suffix(' ms')
                    ->formatStateUsing(fn ($state) => number_format(round($state ?? 0)))
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_events')
                    ->label('Total events')
                    ->sortable(),
                Tables\Columns\TextColumn::make('error_count')
                    ->label('Errors')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('buffering_count')
                    ->label('Buffering')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success')
                    ->sortable(),
            ])
            ->defaultSort('error_count', 'desc');
    }
}
