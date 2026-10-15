<?php

namespace App\Providers\Filament;

use App\Providers\Filament\Concerns\ConfiguresPanel;
use Filament\Pages\Dashboard;
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
            ->pages([Dashboard::class]);
    }
}
