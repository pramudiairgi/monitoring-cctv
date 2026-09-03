<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\CameraResource\Pages\ListCameras;
use App\Filament\Resources\CameraResource\Pages\CreateCamera;
use App\Filament\Resources\CameraResource\Pages\EditCamera;
use App\Filament\Resources\CameraResource\Pages;
use App\Models\Camera;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CameraResource extends Resource
{
    protected static ?string $model = Camera::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-video-camera';

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
                    ->maxLength(255),

                TextInput::make('adaptive_url')
                    ->url()
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

                TextColumn::make('category.name')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge(fn(string $state): string => match ($state) {
                        'online' => 'success',
                        'offline' => 'danger',
                    }),

                TextColumn::make('order')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                Action::make('toggleStatus')
                    ->icon(fn(Camera $record): string => $record->status === 'online' ? 'heroicon-m-x-mark' : 'heroicon-m-check')
                    ->label(fn(Camera $record): string => $record->status === 'online' ? 'Set Offline' : 'Set Online')
                    ->action(fn(Camera $record) => $record->update([
                        'status' => $record->status === 'online' ? 'offline' : 'online',
                    ]))
                    ->color(fn(Camera $record): string => $record->status === 'online' ? 'danger' : 'success'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
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
