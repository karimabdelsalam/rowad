<?php

namespace App\Providers\Filament\Concerns;

use App\Auth\SmsAuthentication;
use App\Filament\Auth\Login;
use App\Filament\Auth\RequestPasswordReset;
use App\Filament\Support\CommandSearch;
use App\Filament\Support\InitialsAvatar;
use App\Filament\Widgets\LoginActivities;
use App\Http\Middleware\EnsureOfficeIsAccessible;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackDemoVisit;
use App\Support\Demo;
use App\Support\PlatformSettings;
use Filament\Actions\Action;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
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
            ->login($tenant ? Login::class : \Filament\Auth\Pages\Login::class)
            ->font('Cairo', url: '/fonts/cairo/cairo.css', provider: LocalFontProvider::class) // self-hosted
            ->defaultAvatarProvider(InitialsAvatar::class)
            ->spa(hasPrefetching: true) // page changes swap the content only; links prefetch on hover
            ->spaUrlExceptions(['*/print/*', '*/sign/*', '*/pay/*', '*/media/*', '*/locale/*', '*/demo/*', '*/impersonate/*',
                '*/platform-access/*', '*/reactivate*', '*/branding/*', '*/manifest/*', '*/logout'])
            ->darkMode(false) // charts and documents are designed for the light surface
            ->colors([
                'primary' => self::brandTeal(),
                'gray' => Color::Slate,
            ])
            ->maxContentWidth(Width::Full) // use the whole screen; wide tables shouldn't scroll beside empty margins
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('16.5rem')
            ->collapsedSidebarWidth('4.25rem')
            ->userMenuItems([
                Action::make('switchLocale')
                    ->label(fn () => app()->getLocale() === 'ar' ? 'English' : 'العربية')
                    ->icon(Heroicon::OutlinedLanguage)
                    ->url(fn () => route('locale.switch', app()->getLocale() === 'ar' ? 'en' : 'ar')),
            ])
            // the top search box is a command palette: Ctrl+K / ⌘K, pages and "new …" actions besides records
            ->globalSearch(CommandSearch::class)
            // records search sits on its own row under the top bar ("/"); Ctrl+K opens the screen launcher
            ->globalSearchFieldSuffix('/')
            // app shell: page title in the topbar, a menu filter, full screen, who is signed in beside the avatar
            ->renderHook(PanelsRenderHook::TOPBAR_LOGO_AFTER, fn () => view('filament.topbar-title'))
            ->renderHook(PanelsRenderHook::SIDEBAR_NAV_START, fn () => view('filament.sidebar-filter'))
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE, fn () => auth()->check() ? view('filament.screen-palette') : '')
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER, fn () => view('filament.topbar-tools'))
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn () => view('filament.topbar-user'))
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn () => view('filament.locale-switch'))
            ->favicon(asset('favicon.svg'))
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('filament.theme'))
            // developer credit at the bottom of every panel page, login pages included
            ->renderHook(PanelsRenderHook::FOOTER, fn () => view('filament.footer-credit'))
            ->databaseNotifications($tenant, isLazy: false) // rendered with the page instead of a second request
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                SetLocale::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);

        if ($tenant) {
            // The office's own identity: logo (or a monogram), name and the panel's role, in the sidebar,
            // the topbar on small screens and the login page; its brand colour re-skins the panel.
            $panel->brandLogo(fn () => view('filament.office-brand'))->brandLogoHeight('2.75rem');
            $panel->renderHook(PanelsRenderHook::SIDEBAR_LOGO_AFTER, fn () => view('filament.sidebar-mark'));
            $panel->renderHook(PanelsRenderHook::SIDEBAR_FOOTER, fn () => view('filament.sidebar-footer'));
            $panel->renderHook(PanelsRenderHook::SIMPLE_LAYOUT_START, fn () => view('filament.login-aside'));
            $panel->renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE, fn () => view('filament.quick-create'));
            // installable app (PWA) for every office panel; the portals also get a phone tab bar
            $panel->renderHook(PanelsRenderHook::HEAD_END, fn () => view('filament.pwa-head'));
            if (in_array($id, ['owner', 'client'], true)) {
                $panel->renderHook(PanelsRenderHook::BODY_END, fn () => auth()->check() ? view('filament.portal-mobile') : '');
            }
            $panel->multiFactorAuthentication([SmsAuthentication::make()]);
            $panel->widgets([LoginActivities::class]);
            $panel->passwordReset(RequestPasswordReset::class)->profile();
            $panel->renderHook(PanelsRenderHook::BODY_START, fn () => session()->has('impersonator_id') ? view('filament.impersonation-banner') : '');
            // the shared public demo says what visitors can expect (nothing is sent, it cleans itself up)
            $panel->renderHook(PanelsRenderHook::BODY_START, fn () => Demo::active() ? view('filament.demo-banner') : '');
            // a notice from the platform to every office (maintenance tonight, new feature…), set in the platform console
            $panel->renderHook(PanelsRenderHook::PAGE_START, fn () => ($a = PlatformSettings::announcement()) ? view('filament.platform-announcement', $a) : '');
            $panel->renderHook(PanelsRenderHook::BODY_END, fn () => auth()->check() ? view('filament.assistant-shortcut') : '');

            // Persistent so Livewire update requests are also resolved to the office's database.
            $panel->middleware([
                InitializeTenancyByDomain::class,
                PreventAccessFromCentralDomains::class,
                EnsureOfficeIsAccessible::class,
                TrackDemoVisit::class,
            ], isPersistent: true);
        }

        return $panel;
    }

    protected function discoverIn(Panel $panel, string $namespace): Panel
    {
        $dir = app_path('Filament/'.str_replace('\\', '/', $namespace));

        // discoverClusters() also registers the pages inside each cluster
        return $panel
            ->discoverResources(in: "{$dir}/Resources", for: "App\\Filament\\{$namespace}\\Resources")
            ->discoverPages(in: "{$dir}/Pages", for: "App\\Filament\\{$namespace}\\Pages")
            ->discoverWidgets(in: "{$dir}/Widgets", for: "App\\Filament\\{$namespace}\\Widgets")
            ->discoverClusters(in: "{$dir}/Clusters", for: "App\\Filament\\{$namespace}\\Clusters");
    }

    /**
     * Teal shifted one shade darker so buttons (shade 600) are #0f766e — the landing page colour —
     * with white text at an accessible contrast, instead of a pale teal with dark text.
     */
    protected static function brandTeal(): array
    {
        $t = Color::Teal;

        return [50 => $t[50], 100 => $t[100], 200 => $t[200], 300 => $t[300], 400 => $t[500], 500 => $t[600],
            600 => $t[700], 700 => $t[800], 800 => $t[900], 900 => $t[950], 950 => $t[950]];
    }
}
