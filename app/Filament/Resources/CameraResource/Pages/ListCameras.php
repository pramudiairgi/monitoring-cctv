<?php

namespace App\Filament\Resources\CameraResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\CameraResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCameras extends ListRecords
{
    protected static string $resource = CameraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
