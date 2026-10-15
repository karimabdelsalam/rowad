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
            Stat::make(__('وحداتي'), (string) $s['units'])->description(__(':n مؤجرة', ['n' => $s['rented']])),
            Stat::make(__('الرصيد المستحق لك'), number_format($s['balance'], 2))->color('success'),
            Stat::make(__('المحول لك هذا العام'), number_format($s['received_this_year'], 2)),
        ];
    }
}
