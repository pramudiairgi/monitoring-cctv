<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected ?string $resolvedRole = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // `role` is NOT in $fillable — resolve it here, strip it from the
        // mass-assignment payload, and apply it explicitly in afterCreate().
        $this->resolvedRole = UserResource::resolveAssignedRole(auth()->user(), $data['role'] ?? null);
        unset($data['role']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->forceFill([
            'role' => $this->resolvedRole ?? User::ROLE_OPERATOR,
        ])->save();
    }
}
