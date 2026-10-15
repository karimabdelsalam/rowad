<?php

namespace App\Providers\Filament;

use App\Providers\Filament\Concerns\ConfiguresPanel;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;

/** Property owners' portal (legacy ownerarea). */
class OwnerPanelProvider extends PanelProvider
{
    use ConfiguresPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->discoverIn($this->basePanel($panel, 'owner', 'owner'), 'Owner')
            ->brandName(fn () => __('بوابة الملاك'))
            ->colors(['primary' => Color::Indigo])
            ->pages([Dashboard::class]);
    }
}
