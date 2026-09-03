<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Categories';

    protected static ?string $modelLabel = 'Category';

    protected static ?string $pluralModelLabel = 'Categories';

    /**
     * Operators may view/create/edit categories. Delete stays admin-only:
     * deleting a category cascades to its cameras — too destructive
     * for operators. These overrides are the enforcement
     * (navigation hiding is UX only).
     */
    public static function canViewAny(): bool
    {
        return static::panelUserCanManageCategories();
    }

    public static function canCreate(): bool
    {
        return static::panelUserCanManageCategories();
    }

    public static function canEdit(Model $record): bool
    {
        return static::panelUserCanManageCategories();
    }

    public static function canDelete(Model $record): bool
    {
        return static::panelUserIsAdmin();
    }

    public static function canDeleteAny(): bool
    {
        return static::panelUserIsAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::panelUserCanManageCategories();
    }

    protected static function panelUserCanManageCategories(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ($user->isAdmin() || $user->isOperator());
    }

    protected static function panelUserIsAdmin(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('slug')
                    ->maxLength(255)
                    ->dehydrated()
                    ->unique(Category::class, 'slug', ignoreRecord: true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),

                TextColumn::make('slug')
                    ->searchable(),

                TextColumn::make('cameras_count')
                    ->counts('cameras')
                    ->label('Cameras'),

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
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
