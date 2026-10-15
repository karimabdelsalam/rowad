<?php

namespace App\Filament\Client\Widgets;

use App\Services\Reports\DashboardStats;
use App\Support\Portal;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClientStatsOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 10;

    protected function getColumns(): int|array|null
    {
        return ['default' => 1, 'md' => 2, 'xl' => 4];
    }

    protected function getStats(): array
    {
        $s = app(DashboardStats::class)->client(Portal::client());
        $next = $s['next'];

        return [
            Stat::make(__('الدفعة القادمة'), $next ? number_format($next->remainingAmount(), 2) : '—')
                ->description($next ? $next->due_date->toDateString() : __('لا توجد دفعات مستحقة')),
            Stat::make(__('إجمالي المستحق'), number_format($s['outstanding'], 2)),
            Stat::make(__('متأخرات'), number_format($s['overdue'], 2))->color($s['overdue'] > 0 ? 'danger' : 'success'),
            Stat::make(__('عقودي السارية'), (string) $s['contracts']),
        ];
    }
}
