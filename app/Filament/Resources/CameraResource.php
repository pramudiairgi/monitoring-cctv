<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CameraResource\Pages;
use App\Models\Camera;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CameraResource extends Resource
{
    protected static ?string $model = Camera::class;

    protected static ?string $navigationIcon = 'heroicon-o-video-camera';

    protected static ?string $navigationLabel = 'Cameras';

    protected static ?string $modelLabel = 'Camera';

    protected static ?string $pluralModelLabel = 'Cameras';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
                    ->helperText('URL _adaptive.m3u8 untuk ABR support (Wowza)'),

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
            ->columns([
                TextColumn::make('name')
                    ->searchable(),

                TextColumn::make('category.name')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge(fn (string $state): string => match ($state) {
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
            ->groups([
                'category.name',
                'status',
            ])
            ->defaultGroup('category.name')
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
                Action::make('toggleStatus')
                    ->icon(fn (Camera $record): string => $record->status === 'online' ? 'heroicon-m-x-mark' : 'heroicon-m-check')
                    ->label(fn (Camera $record): string => $record->status === 'online' ? 'Set Offline' : 'Set Online')
                    ->action(fn (Camera $record) => $record->update([
                        'status' => $record->status === 'online' ? 'offline' : 'online',
                    ]))
                    ->color(fn (Camera $record): string => $record->status === 'online' ? 'danger' : 'success'),
            ])
            ->bulkActions([
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
            'index' => Pages\ListCameras::route('/'),
            'create' => Pages\CreateCamera::route('/create'),
            'edit' => Pages\EditCamera::route('/{record}/edit'),
        ];
    }
}
