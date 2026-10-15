<?php

namespace App\Providers\Filament;

use App\Providers\Filament\Concerns\ConfiguresPanel;
use App\Filament\Pages\PortalDashboard;
use Filament\Panel;
use Filament\PanelProvider;

/** Renters' and buyers' portal (legacy clientarea / ccp). */
class ClientPanelProvider extends PanelProvider
{
    use ConfiguresPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->discoverIn($this->basePanel($panel, 'client', 'client'), 'Client')
            // the organisation's own name and colours; the banner says which portal this is
            ->brandName(fn () => \App\Support\Website::name() ?: __('بوابة العملاء'))
            ->middleware([\App\Http\Middleware\EnsurePlanFeature::class.':client_portal'])
            ->pages([PortalDashboard::class]);
    }
}
