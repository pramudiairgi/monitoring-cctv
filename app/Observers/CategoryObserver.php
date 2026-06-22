<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\CameraExport;

class CategoryObserver
{
    public function saved(Category $category): void
    {
        app(CameraExport::class)->handle();
    }

    public function deleted(Category $category): void
    {
        app(CameraExport::class)->handle();
    }
}
