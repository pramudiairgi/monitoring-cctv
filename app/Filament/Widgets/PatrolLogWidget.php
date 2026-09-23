<?php

namespace App\Filament\Widgets;

use App\Models\PatrolLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class PatrolLogWidget extends TableWidget
{
    protected static ?string $heading = 'Patrol Log';

    protected ?int $sorting = 2;

    public function table(Table $table): Table
    {
        return $table
            ->poll('30s')
            ->query(PatrolLog::query()->latest())
            ->columns([
                TextColumn::make('checked_at')
                    ->label('Time')
                    ->dateTime('H:i:s')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'partial' => 'warning',
                        'failed' => 'danger',
                        'no_change' => 'info',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'success' => 'Success',
                        'partial' => 'Partial',
                        'failed' => 'Failed',
                        'no_change' => 'No Change',
                    })
                    ->sortable(),
                TextColumn::make('status_changed_count')
                    ->label('Changed')
                    ->numeric()
                    ->sortable()
                    ->placeholder('-'),
            ])
            ->defaultSort('checked_at', 'desc')
            ->paginationPageOptions([5, 10, 25, 50]);
    }
}
