<?php

namespace App\Filament\Admin\Widgets;

use App\Services\Reports\DashboardStats;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Headline figures (legacy collectStats), each with a six-month trend where one exists. */
class OfficeStatsOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $stats = app(DashboardStats::class);
        $s = $stats->office();
        $trend = $stats->recentMonths(6);
        $attention = $stats->attention();
        $money = fn (float $v) => number_format($v, 0).' '.\App\Support\Settings::currencyLabel();
        $lastMonth = $trend['collected'][4] ?? 0;
        $change = $lastMonth > 0 ? round(($s['collected_this_month'] - $lastMonth) / $lastMonth * 100) : null;
        $undocumented = \App\Models\RentContract::query()->where('status', \App\Enums\ContractStatus::Active)
            ->whereIn('ejar_status', [\App\Enums\EjarStatus::Pending->value, \App\Enums\EjarStatus::AwaitingParties->value])->count();
        $maintenance = \App\Models\MaintenanceRequest::query()->open()->count();

        return [
            Stat::make(__('محصل هذا الشهر'), $money($s['collected_this_month']))
                ->icon(Heroicon::OutlinedBanknotes)
                ->description($change === null ? __('آخر 6 أشهر') : __(':change% عن الشهر الماضي', ['change' => ($change > 0 ? '+' : '').$change]))
                ->descriptionIcon($change !== null && $change < 0 ? Heroicon::ArrowTrendingDown : Heroicon::ArrowTrendingUp)
                ->chart($trend['collected'])->color('success'),
            Stat::make(__('مستحق هذا الشهر'), $money($s['due_this_month']))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->description(__('المستحقات الشهرية — آخر 6 أشهر'))
                ->chart($trend['due'])->color('warning'),
            Stat::make(__('متأخرات'), $money($s['overdue']))
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->description(trans_choice('{0} لا توجد أقساط متأخرة|{1} قسط واحد متأخر|[2,*] :count أقساط متأخرة', $attention['overdue_count']))
                ->color($s['overdue'] > 0 ? 'danger' : 'success'),
            Stat::make(__('نسبة الإشغال'), $s['occupancy'].' %')
                ->icon(Heroicon::OutlinedHomeModern)
                ->description(__(':rented مؤجرة من :units وحدة', ['rented' => $s['rented'], 'units' => $s['units']]))
                ->color('primary'),
            Stat::make(__('عقود إيجار سارية'), number_format($s['active_contracts']))
                ->icon(Heroicon::OutlinedDocumentText)
                ->description(__(':count تنتهي خلال 60 يومًا', ['count' => $attention['expiring']])
                    .($undocumented > 0 ? ' · '.__(':count غير موثقة في إيجار', ['count' => $undocumented]) : ''))
                ->color($attention['expiring'] > 0 || $undocumented > 0 ? 'warning' : 'gray'),
            Stat::make(__('مستحقات الملاك'), $money($s['owners_payable']))
                ->icon(Heroicon::OutlinedScale)
                ->description(__('غير مسواة')),
            Stat::make(__('وحدات متاحة'), number_format($s['available']))
                ->icon(Heroicon::OutlinedKey)
                ->description(__('جاهزة للتأجير')),
            Stat::make(__('طلبات صيانة مفتوحة'), number_format($maintenance))
                ->icon(Heroicon::OutlinedWrenchScrewdriver)
                ->description(__('و:count تذاكر دعم مفتوحة', ['count' => $attention['open_tickets']]))
                ->url(\App\Filament\Admin\Resources\MaintenanceRequests\MaintenanceRequestResource::getUrl())
                ->color($maintenance + $attention['open_tickets'] > 0 ? 'warning' : 'gray'),
        ];
    }
}
