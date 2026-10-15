<?php

namespace App\Providers\Filament;

use App\Providers\Filament\Concerns\ConfiguresPanel;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;

/** SaaS operator console: offices, plans, subscriptions. Central domains only. */
class PlatformPanelProvider extends PanelProvider
{
    use ConfiguresPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->discoverIn($this->basePanel($panel, 'platform', 'platform', tenant: false), 'Platform')
            ->domains(config('tenancy.central_domains'))
            ->brandName('رواد — إدارة المنصة')
            ->colors(['primary' => Color::Violet])
            ->pages([Dashboard::class]);
    }
}
