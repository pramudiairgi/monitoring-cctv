<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CameraResource\Pages\CreateCamera;
use App\Filament\Resources\CameraResource\Pages\EditCamera;
use App\Filament\Resources\CameraResource\Pages\ListCameras;
use App\Models\Camera;
use App\Models\User;
use App\Rules\PublicHttpUrl;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CameraResource extends Resource
{
    protected static ?string $model = Camera::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-video-camera';

    protected static string|\UnitEnum|null $navigationGroup = 'Monitoring';

    protected static ?string $navigationLabel = 'Cameras';

    protected static ?string $modelLabel = 'Camera';

    protected static ?string $pluralModelLabel = 'Cameras';

    /**
     * Operators get full camera management, including delete.
     * These overrides are the enforcement (navigation hiding is UX only).
     */
    public static function canViewAny(): bool
    {
        return static::panelUserCanManageCameras();
    }

    public static function canCreate(): bool
    {
        return static::panelUserCanManageCameras();
    }

    public static function canEdit(Model $record): bool
    {
        return static::panelUserCanManageCameras();
    }

    public static function canDelete(Model $record): bool
    {
        return static::panelUserCanManageCameras();
    }

    public static function canDeleteAny(): bool
    {
        return static::panelUserCanManageCameras();
    }

    protected static function panelUserCanManageCameras(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ($user->isAdmin() || $user->isOperator());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('stream_url')
                    ->required()
                    ->url()
                    ->rule(new PublicHttpUrl)
                    ->maxLength(255),

                TextInput::make('adaptive_url')
                    ->url()
                    ->rule(new PublicHttpUrl)
                    ->maxLength(255)
                    ->helperText('URL _adaptive.m3u8 (optional)'),

                Select::make('category_id')
                    ->relationship('category', 'name')
                    ->required(),

                Select::make('status')
                    ->options([
                        'online' => 'Online',
                        'offline' => 'Offline',
                    ])
                    ->required()
                    ->default('online'),

                Toggle::make('maintenance')
                    ->label('Maintenance Mode')
                    ->helperText('When enabled, the camera will be skipped by the status checker and not automatically updated.')
                    ->default(false),

                TextInput::make('order')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('30s')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),

                TextColumn::make('category.name'),

                TextColumn::make('status')
                    ->badge(fn (string $state): string => match ($state) {
                        'online' => 'success',
                        'offline' => 'danger',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'online' => 'heroicon-m-check-circle',
                        'offline' => 'heroicon-m-x-circle',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'online' => 'Online',
                        'offline' => 'Offline',
                    })
                    ->sortable(),

                IconColumn::make('maintenance')
                    ->boolean()
                    ->color(fn (bool $state): string => $state ? 'warning' : 'success')
                    ->label('Maintenance')
                    ->sortable(),

                TextColumn::make('order'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'online' => 'Online',
                        'offline' => 'Offline',
                    ]),
                TernaryFilter::make('maintenance')
                    ->label('Maintenance Mode'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                Action::make('reorder')
                    ->icon('heroicon-m-arrows-up-down')
                    ->label('Reorder')
                    ->modalHeading('Reorder Camera')
                    ->modalDescription('Move this camera up or down in the list order.')
                    ->form([
                        TextInput::make('order')
                            ->label('Order')
                            ->numeric()
                            ->required(),
                    ])
                    ->action(fn (Camera $record, array $data) => $record->update(['order' => $data['order']])),
                Action::make('toggleStatus')
                    ->icon(fn (Camera $record): string => $record->status === 'online' ? 'heroicon-m-x-mark' : 'heroicon-m-check')
                    ->label(fn (Camera $record): string => $record->status === 'online' ? 'Set Offline' : 'Set Online')
                    ->action(fn (Camera $record) => $record->update([
                        'status' => $record->status === 'online' ? 'offline' : 'online',
                        'maintenance' => $record->status === 'online',
                    ]))
                    ->color(fn (Camera $record): string => $record->status === 'online' ? 'danger' : 'success'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('setOnline')
                        ->label('Set Online')
                        ->icon('heroicon-m-check-circle')
                        ->color('success')
                        ->action(fn (array $records) => collect($records)->each(fn ($record) => $record->update([
                            'status' => 'online',
                            'maintenance' => false,
                        ])))
                        ->requiresConfirmation(),
                    BulkAction::make('setOffline')
                        ->label('Set Offline')
                        ->icon('heroicon-m-x-circle')
                        ->color('danger')
                        ->action(fn (array $records) => collect($records)->each(fn ($record) => $record->update([
                            'status' => 'offline',
                        ])))
                        ->requiresConfirmation(),
                    BulkAction::make('setMaintenance')
                        ->label('Set Maintenance')
                        ->icon('heroicon-m-wrench')
                        ->color('warning')
                        ->action(fn (array $records) => collect($records)->each(fn ($record) => $record->update([
                            'maintenance' => true,
                        ])))
                        ->requiresConfirmation(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCameras::route('/'),
            'create' => CreateCamera::route('/create'),
            'edit' => EditCamera::route('/{record}/edit'),
        ];
    }
}
