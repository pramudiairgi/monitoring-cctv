<?php

namespace App\Filament\Resources\CameraResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\CameraResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCamera extends EditRecord
{
    protected static string $resource = CameraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
