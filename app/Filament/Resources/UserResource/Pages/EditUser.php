<?php

namespace App\Filament\Resources\UserResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected ?string $resolvedRole = null;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->disabled(fn (): bool => UserResource::isProtectedFromDeletion(auth()->user(), $this->getRecord()))
                ->before(function (DeleteAction $action): void {
                    if ($reason = UserResource::deletionRejectionReason(auth()->user(), $this->getRecord())) {
                        Notification::make()->title($reason)->danger()->send();
                        $action->halt();
                    }
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $actor = auth()->user();

        if (! ($actor instanceof User && $actor->isAdmin())) {
            // Non-admins can never change roles — leave the stored role alone.
            $this->resolvedRole = null;
            unset($data['role']);

            return $data;
        }

        $requested = $data['role'] ?? $this->getRecord()->role;

        // Prevent demoting the last admin (lockout protection).
        if ($this->getRecord()->isAdmin()
            && $requested !== User::ROLE_ADMIN
            && ! User::where('role', User::ROLE_ADMIN)->whereKeyNot($this->getRecord()->getKey())->exists()) {
            $requested = User::ROLE_ADMIN;
        }

        $this->resolvedRole = UserResource::resolveAssignedRole($actor, $requested);
        unset($data['role']);

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->resolvedRole !== null) {
            $this->record->forceFill(['role' => $this->resolvedRole])->save();
        }
    }
}
