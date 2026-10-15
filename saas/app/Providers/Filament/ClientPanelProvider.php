<?php

namespace App\Providers\Filament;

use App\Providers\Filament\Concerns\ConfiguresPanel;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;

/** Renters' and buyers' portal (legacy clientarea / ccp). */
class ClientPanelProvider extends PanelProvider
{
    use ConfiguresPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->discoverIn($this->basePanel($panel, 'client', 'client'), 'Client')
            ->brandName(fn () => __('بوابة العملاء'))
            ->colors(['primary' => Color::Sky])
            ->pages([Dashboard::class]);
    }
}
