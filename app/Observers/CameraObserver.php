<?php

namespace App\Observers;

use App\Models\Camera;
use App\Services\CameraExport;

class CameraObserver
{
    public function saving(Camera $camera): void
    {
        if ($camera->exists && $camera->isDirty(['stream_url', 'adaptive_url'])) {
            $camera->target_url = null;
        }
    }

    public function saved(Camera $camera): void
    {
        app(CameraExport::class)->handle();
    }

    public function deleted(Camera $camera): void
    {
        app(CameraExport::class)->handle();
    }
}
