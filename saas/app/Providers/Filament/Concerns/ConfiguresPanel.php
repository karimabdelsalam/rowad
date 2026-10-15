<?php

namespace App\Providers\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

trait ConfiguresPanel
{
    protected function basePanel(Panel $panel, string $id, string $path, bool $tenant = true): Panel
    {
        $panel = $panel
            ->id($id)
            ->path($path)
            ->login()
            ->font('Tajawal')
            ->colors([
                'primary' => Color::Teal,
                'gray' => Color::Slate,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->userMenuItems([
                Action::make('switchLocale')
                    ->label(fn () => app()->getLocale() === 'ar' ? 'English' : 'العربية')
                    ->icon(Heroicon::OutlinedLanguage)
                    ->url(fn () => route('locale.switch', app()->getLocale() === 'ar' ? 'en' : 'ar')),
            ])
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn () => view('filament.locale-switch'))
            ->databaseNotifications($tenant)
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                \App\Http\Middleware\SetLocale::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);

        if ($tenant) {
            // Persistent so Livewire update requests are also resolved to the office's database.
            $panel->middleware([
                InitializeTenancyByDomain::class,
                PreventAccessFromCentralDomains::class,
                \App\Http\Middleware\EnsureOfficeIsAccessible::class,
            ], isPersistent: true);
        }

        return $panel;
    }

    protected function discoverIn(Panel $panel, string $namespace): Panel
    {
        $dir = app_path('Filament/'.str_replace('\\', '/', $namespace));

        return $panel
            ->discoverResources(in: "{$dir}/Resources", for: "App\\Filament\\{$namespace}\\Resources")
            ->discoverPages(in: "{$dir}/Pages", for: "App\\Filament\\{$namespace}\\Pages")
            ->discoverWidgets(in: "{$dir}/Widgets", for: "App\\Filament\\{$namespace}\\Widgets")
            ->discoverClusters(in: "{$dir}/Clusters", for: "App\\Filament\\{$namespace}\\Clusters");
    }
}
