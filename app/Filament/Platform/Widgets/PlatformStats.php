<?php

namespace App\Filament\Platform\Widgets;

use App\Models\Subscription;
use App\Models\Tenant;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** The platform at a glance: offices by state and recurring revenue. */
class PlatformStats extends StatsOverviewWidget
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
        $byStatus = Tenant::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $total = (int) $byStatus->sum();
        $newThisMonth = Tenant::query()->where('created_at', '>=', now()->startOfMonth())->count();
        $trialsEnding = Tenant::query()->where('status', Tenant::STATUS_TRIAL)->whereBetween('trial_ends_at', [now(), now()->addDays(7)])->count();
        $requests = Tenant::query()->whereNotNull('data->upgrade_request')->count();

        // Monthly recurring revenue from paid periods running today (yearly plans spread over 12 months).
        $mrr = Subscription::query()->whereIn('status', ['active', 'past_due'])->where('starts_at', '<=', now())->where('ends_at', '>', now())
            ->get(['amount', 'billing_cycle'])
            ->sum(fn (Subscription $s) => $s->billing_cycle === 'yearly' ? (float) $s->amount / 12 : (float) $s->amount);

        return [
            Stat::make(__('المؤسسات'), number_format($total))
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->description(trans_choice('{0} لا توجد مؤسسات جديدة هذا الشهر|{1} مؤسسة جديدة هذا الشهر|[2,*] :count مؤسسات جديدة هذا الشهر', $newThisMonth))
                ->color('primary'),
            Stat::make(__('مشتركة ومدفوعة'), number_format((int) ($byStatus[Tenant::STATUS_ACTIVE] ?? 0)))
                ->icon(Heroicon::OutlinedCheckBadge)
                ->description(__(':count متأخرة السداد', ['count' => (int) ($byStatus[Tenant::STATUS_PAST_DUE] ?? 0)]))
                ->color('success'),
            Stat::make(__('في الفترة التجريبية'), number_format((int) ($byStatus[Tenant::STATUS_TRIAL] ?? 0)))
                ->icon(Heroicon::OutlinedClock)
                ->description(__(':count تنتهي خلال 7 أيام', ['count' => $trialsEnding]))
                ->color($trialsEnding > 0 ? 'warning' : 'info'),
            Stat::make(__('الإيراد الشهري المتكرر'), number_format($mrr).' '.__('ر.س'))
                ->icon(Heroicon::OutlinedBanknotes)
                ->description(trans_choice('{0} لا توجد طلبات ترقية|{1} طلب ترقية بانتظارك|[2,*] :count طلبات ترقية بانتظارك', $requests))
                ->color($requests > 0 ? 'warning' : 'gray'),
        ];
    }
}
