<?php

namespace App\Filament\Widgets;

use App\Models\StreamTelemetry;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseTableWidget;

class RecentTelemetryTable extends BaseTableWidget
{
    protected static ?string $heading = 'Log Telemetry Terbaru';
    protected static ?string $pollingInterval = '5s';
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StreamTelemetry::latest('created_at')->limit(20)
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('camera_name')
                    ->label('Kamera')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Event')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'error' => 'danger',
                        'play' => 'success',
                        'buffering_start' => 'warning',
                        'buffering_end' => 'info',
                        'reconnect' => 'warning',
                        'level_switch' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('bitrate_kbps')
                    ->label('Bitrate')
                    ->suffix(' kbps')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('buffer_health')
                    ->label('Buffer')
                    ->formatStateUsing(fn ($state) => number_format($state ?? 0, 2))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('latency_ms')
                    ->label('Latency')
                    ->suffix(' ms')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
