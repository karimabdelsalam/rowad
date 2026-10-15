<?php

namespace App\Filament\Owner\Widgets;

use App\Services\Reports\DashboardStats;
use App\Support\Portal;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OwnerStatsOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 10;

    protected function getColumns(): int|array|null
    {
        return ['default' => 1, 'md' => 2, 'xl' => 3];
    }

    protected function getStats(): array
    {
        $s = app(DashboardStats::class)->owner(Portal::owner());

        return [
            Stat::make(__('وحداتي'), (string) $s['units'])
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedHomeModern)
                ->description(__(':n مؤجرة', ['n' => $s['rented']]).' · '.__(':n شاغرة', ['n' => $s['units'] - $s['rented']]))
                ->color('primary'),
            Stat::make(__('الرصيد المستحق لك'), number_format($s['balance'], 2).' '.\App\Support\Settings::currencyLabel())
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedBanknotes)
                ->description($s['balance'] > 0 ? __('لم يُحوَّل بعد') : __('لا توجد مبالغ معلّقة'))
                ->color($s['balance'] > 0 ? 'warning' : 'success'),
            Stat::make(__('المحول لك هذا العام'), number_format($s['received_this_year'], 2).' '.\App\Support\Settings::currencyLabel())
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedArrowTrendingUp)
                ->description(__('منذ بداية :year', ['year' => now()->year]))
                ->color('success'),
        ];
    }
}
