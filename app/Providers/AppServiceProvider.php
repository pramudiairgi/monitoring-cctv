<?php

namespace App\Providers;

use App\Models\Camera;
use App\Models\Category;
use App\Observers\CameraObserver;
use App\Observers\CategoryObserver;
use Illuminate\Support\Facades\URL;
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

        // Force HTTPS URLs whenever APP_URL itself is https. Proxied
        // platforms (Railway edge, CDN, load balancers) terminate TLS
        // before the container, so request-scheme detection alone cannot
        // be trusted to produce https asset/route URLs.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
