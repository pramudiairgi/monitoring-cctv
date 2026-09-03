<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\CameraOverviewWidget;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->spa()
            ->unsavedChangesAlerts()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile()
            ->brandName('PATROLI')
            ->brandLogo(new HtmlString('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" fill="none" class="h-8 w-8"><path d="M16 3 28 9v7c0 7-5.5 11.5-12 13C9.5 27.5 4 23 4 16V9l12-6Z" fill="#064e3b" stroke="#10b981" stroke-width="1.5" stroke-linejoin="round"/><path d="M6.5 16s4-5.5 9.5-5.5S25.5 16 25.5 16s-4 5.5-9.5 5.5S6.5 16 6.5 16Z" stroke="#22d3ee" stroke-width="1.5"/><circle cx="16" cy="16" r="2.6" fill="#10b981"/><circle cx="16" cy="5.6" r="1.6" fill="#f43f5e"><animate attributeName="opacity" values="1;.2;1" dur="1.6s" repeatCount="indefinite"/></circle></svg>'))
            ->brandLogoHeight('2rem')
            ->favicon('data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 32 32\'%3E%3Crect width=\'32\' height=\'32\' rx=\'7\' fill=\'%23052e2b\'/%3E%3Cpath d=\'M6.5 16s4-5.5 9.5-5.5S25.5 16 25.5 16s-4 5.5-9.5 5.5S6.5 16 6.5 16Z\' stroke=\'%2322d3ee\' stroke-width=\'1.8\' fill=\'none\'/%3E%3Ccircle cx=\'16\' cy=\'16\' r=\'3\' fill=\'%2310b981\'/%3E%3C/svg%3E')
            ->colors([
                'primary' => Color::Emerald,
                'info' => Color::Cyan,
                'gray' => Color::Slate,
            ])
            ->font('Instrument Sans')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->darkMode()
            ->defaultThemeMode(ThemeMode::Dark)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->resourceCreatePageRedirect('index')
            ->resourceEditPageRedirect('index')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                CameraOverviewWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
