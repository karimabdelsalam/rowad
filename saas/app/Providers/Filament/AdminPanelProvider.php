<?php

namespace App\Providers\Filament;

use App\Providers\Filament\Concerns\ConfiguresPanel;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;

/** The office's back-office (legacy adminarea). Served on each office's domain. */
class AdminPanelProvider extends PanelProvider
{
    use ConfiguresPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->discoverIn($this->basePanel($panel, 'admin', 'admin'), 'Admin')
            ->default()
            ->brandName(fn () => tenancy()->initialized ? (\App\Support\Settings::get('site_name') ?? tenant('name')) : config('app.name'))
            ->passwordReset()
            ->profile()
            ->pages([Dashboard::class])
            ->navigationGroups([
                NavigationGroup::make(fn () => __('العقارات')),
                NavigationGroup::make(fn () => __('العقود')),
                NavigationGroup::make(fn () => __('المالية')),
                NavigationGroup::make(fn () => __('العملاء والملاك')),
                NavigationGroup::make(fn () => __('التقارير')),
                NavigationGroup::make(fn () => __('التشغيل')),
                NavigationGroup::make(fn () => __('التواصل')),
                NavigationGroup::make(fn () => __('الإعدادات'))->collapsed(),
            ]);
    }
}
