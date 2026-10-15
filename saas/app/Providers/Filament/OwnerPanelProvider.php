<?php

namespace App\Providers\Filament;

use App\Providers\Filament\Concerns\ConfiguresPanel;
use App\Filament\Pages\PortalDashboard;
use Filament\Panel;
use Filament\PanelProvider;

/** Property owners' portal (legacy ownerarea). */
class OwnerPanelProvider extends PanelProvider
{
    use ConfiguresPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->discoverIn($this->basePanel($panel, 'owner', 'owner'), 'Owner')
            // the organisation's own name and colours; the banner says which portal this is
            ->brandName(fn () => \App\Support\Website::name() ?: __('بوابة الملاك'))
            ->pages([PortalDashboard::class]);
    }
}
