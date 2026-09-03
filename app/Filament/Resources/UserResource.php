<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $modelLabel = 'User';

    protected static ?string $pluralModelLabel = 'Users';

    /**
     * Admin only. These overrides are the enforcement
     * (navigation hiding is UX only).
     */
    public static function canViewAny(): bool
    {
        return static::panelUserIsAdmin();
    }

    public static function canCreate(): bool
    {
        return static::panelUserIsAdmin();
    }

    public static function canEdit(Model $record): bool
    {
        return static::panelUserIsAdmin();
    }

    public static function canDelete(Model $record): bool
    {
        return static::panelUserIsAdmin()
            && $record instanceof User
            && ! static::isProtectedFromDeletion(auth()->user(), $record);
    }

    public static function canDeleteAny(): bool
    {
        return static::panelUserIsAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::panelUserIsAdmin();
    }

    protected static function panelUserIsAdmin(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    /**
     * Resolve the role to assign for a create/update request.
     * Only an admin actor can assign; anything else (non-admin actor,
     * guest, unknown value) falls back to `operator` — never escalates.
     */
    public static function resolveAssignedRole(mixed $actor, mixed $requested): string
    {
        if ($actor instanceof User && $actor->isAdmin() && in_array($requested, User::ROLES, true)) {
            return $requested;
        }

        return User::ROLE_OPERATOR;
    }

    public static function isProtectedFromDeletion(mixed $actor, User $target): bool
    {
        return static::deletionRejectionReason($actor, $target) !== null;
    }

    public static function deletionRejectionReason(mixed $actor, User $target): ?string
    {
        if ($actor instanceof Authenticatable && (int) $actor->getKey() === (int) $target->getKey()) {
            return 'You cannot delete your own account.';
        }

        if ($target->isAdmin() && ! User::where('role', User::ROLE_ADMIN)->whereKeyNot($target->getKey())->exists()) {
            return 'You cannot delete the last admin account.';
        }

        return null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->required()
                    ->email()
                    ->maxLength(255)
                    ->unique(User::class, 'email', ignoreRecord: true),

                TextInput::make('password')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->minLength(8)
                    ->rule(Password::defaults())
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state)),

                // Visible to admins only; assigned explicitly in the
                // Create/Edit page handlers (never mass-assigned).
                Select::make('role')
                    ->options([
                        User::ROLE_ADMIN => 'Admin',
                        User::ROLE_OPERATOR => 'Operator',
                    ])
                    ->default(User::ROLE_OPERATOR)
                    ->required()
                    ->visible(fn (): bool => static::panelUserIsAdmin())
                    ->dehydrated(fn (): bool => static::panelUserIsAdmin()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),

                TextColumn::make('email')
                    ->searchable(),

                TextColumn::make('role')
                    ->badge()
                    ->colors([
                        'success' => User::ROLE_ADMIN,
                        'gray' => User::ROLE_OPERATOR,
                    ]),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->disabled(fn (User $record): bool => static::isProtectedFromDeletion(auth()->user(), $record))
                    ->before(function (DeleteAction $action, User $record): void {
                        if ($reason = static::deletionRejectionReason(auth()->user(), $record)) {
                            Notification::make()->title($reason)->danger()->send();
                            $action->halt();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Per-record deletes fire the User `deleting` guard, so
                    // self / last-admin records survive bulk deletes.
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
