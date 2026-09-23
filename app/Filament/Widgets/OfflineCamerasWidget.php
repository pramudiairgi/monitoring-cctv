<?php

namespace App\Filament\Widgets;

use App\Models\Camera;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class OfflineCamerasWidget extends TableWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Camera::query()
                    ->where(fn ($q) => $q->where('status', 'offline')->orWhere('maintenance', true))
                    ->orderBy('category_id')
                    ->orderBy('name')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Camera')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'online' => 'success',
                        'offline' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                IconColumn::make('maintenance')
                    ->label('Maintenance')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('category_id')
            ->paginated([5, 10, 25]);
    }
}