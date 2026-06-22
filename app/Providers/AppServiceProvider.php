<?php

namespace App\Providers;

use App\Models\Camera;
use App\Models\Category;
use App\Observers\CameraObserver;
use App\Observers\CategoryObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Camera::observe(CameraObserver::class);
        Category::observe(CategoryObserver::class);
    }
}
