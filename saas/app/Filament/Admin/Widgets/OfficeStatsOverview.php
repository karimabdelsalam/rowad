<?php

namespace App\Filament\Admin\Widgets;

use App\Services\Reports\DashboardStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OfficeStatsOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $s = app(DashboardStats::class)->office();
        $money = fn (float $v) => number_format($v, 2);

        return [
            Stat::make(__('نسبة الإشغال'), $s['occupancy'].' %')
                ->description(__(':rented مؤجرة من :units وحدة', ['rented' => $s['rented'], 'units' => $s['units']]))
                ->color('primary'),
            Stat::make(__('مستحق هذا الشهر'), $money($s['due_this_month']))->color('warning'),
            Stat::make(__('متأخرات'), $money($s['overdue']))->color($s['overdue'] > 0 ? 'danger' : 'success'),
            Stat::make(__('محصل هذا الشهر'), $money($s['collected_this_month']))->color('success'),
            Stat::make(__('مستحقات الملاك'), $money($s['owners_payable']))->description(__('غير مسواة')),
            Stat::make(__('عقود إيجار سارية'), (string) $s['active_contracts']),
        ];
    }
}
