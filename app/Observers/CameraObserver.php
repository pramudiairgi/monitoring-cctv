<?php

namespace App\Observers;

use App\Models\Camera;
use App\Services\CameraExport;

class CameraObserver
{
    public function saved(Camera $camera): void
    {
        app(CameraExport::class)->handle();
    }

    public function deleted(Camera $camera): void
    {
        app(CameraExport::class)->handle();
    }
}
