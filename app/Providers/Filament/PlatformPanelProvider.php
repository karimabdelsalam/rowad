<?php

namespace App\Providers\Filament;

use App\Providers\Filament\Concerns\ConfiguresPanel;
use App\Filament\Platform\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;

/** SaaS operator console: offices, plans, subscriptions. Central domains only. */
class PlatformPanelProvider extends PanelProvider
{
    use ConfiguresPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->discoverIn($this->basePanel($panel, 'platform', 'platform', tenant: false), 'Platform')
            ->domains(config('tenancy.central_domains'))
            ->brandName(fn () => __('عقار نوفا — إدارة المنصة'))
            ->brandLogo(fn () => view('filament.platform-brand'))
            ->brandLogoHeight('2.25rem')
            ->pages([Dashboard::class])
            ->login(\App\Filament\Platform\Auth\Login::class)
            // the sign-in page gets the platform's own panel beside the form (offices get theirs in ConfiguresPanel)
            ->renderHook(\Filament\View\PanelsRenderHook::SIMPLE_LAYOUT_START, fn () => view('filament.platform-login-aside'));
    }
}
