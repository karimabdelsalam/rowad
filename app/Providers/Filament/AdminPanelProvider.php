<?php

namespace App\Providers\Filament;

use App\Providers\Filament\Concerns\ConfiguresPanel;
use Filament\Navigation\NavigationGroup;
use App\Filament\Admin\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;

/** The office's back-office (legacy adminarea). Served on each office's domain. */
class AdminPanelProvider extends PanelProvider
{
    use ConfiguresPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->discoverIn($this->basePanel($panel, 'admin', 'admin'), 'Admin')
            ->renderHook(\Filament\View\PanelsRenderHook::CONTENT_START, fn () => view('filament.subscription-notice', ['notice' => \App\Filament\Admin\Pages\Subscription::notice()]))
            ->default()
            ->brandName(fn () => tenancy()->initialized ? \App\Support\Website::name() : config('app.name'))
            ->pages([Dashboard::class])
            ->widgets([])
            ->navigationGroups([
                NavigationGroup::make(fn () => __('العقارات')),
                NavigationGroup::make(fn () => __('العقود')),
                NavigationGroup::make(fn () => __('المالية')),
                NavigationGroup::make(fn () => __('العملاء والملاك')),
                NavigationGroup::make(fn () => __('التقارير')),
                NavigationGroup::make(fn () => __('التشغيل')),
                NavigationGroup::make(fn () => __('الموارد البشرية')),
                NavigationGroup::make(fn () => __('التواصل')),
                NavigationGroup::make(fn () => __('قواعد البيانات'))->collapsed(),
                NavigationGroup::make(fn () => __('الإعدادات'))->collapsed(),
            ]);
    }
}
