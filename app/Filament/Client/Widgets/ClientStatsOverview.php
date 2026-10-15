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
            Stat::make(__('الدفعة القادمة'), $next ? number_format($next->remainingAmount(), 2).' '.\App\Support\Settings::currencyLabel() : '—')
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedCalendarDays)
                ->description($next ? ($next->due_date->isPast() ? __('متأخرة منذ :d', ['d' => $next->due_date->format('Y-m-d')]) : __('تستحق في :d', ['d' => $next->due_date->format('Y-m-d')])) : __('لا توجد دفعات مستحقة'))
                ->color($next?->due_date->isPast() ? 'danger' : 'info'),
            Stat::make(__('إجمالي المستحق'), number_format($s['outstanding'], 2).' '.\App\Support\Settings::currencyLabel())
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedBanknotes)
                ->description(__('على كل عقودك'))
                ->color('warning'),
            Stat::make(__('متأخرات'), number_format($s['overdue'], 2).' '.\App\Support\Settings::currencyLabel())
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedExclamationTriangle)
                ->description($s['overdue'] > 0 ? __('يرجى السداد في أقرب وقت') : __('لا توجد متأخرات'))
                ->color($s['overdue'] > 0 ? 'danger' : 'success'),
            Stat::make(__('عقودي السارية'), (string) $s['contracts'])
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedDocumentText)
                ->description(__('عقود إيجار نشطة'))
                ->color('primary'),
        ];
    }
}
