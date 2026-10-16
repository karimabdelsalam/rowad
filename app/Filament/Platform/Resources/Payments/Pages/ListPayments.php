<?php

namespace App\Filament\Platform\Resources\Payments\Pages;

use App\Filament\Platform\Resources\Payments\PaymentResource;
use App\Models\SubscriptionPayment;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderWidgets(): array
    {
        return [PaymentStats::class];
    }
}

/** Revenue headline above the list: this month, this year, and all time (paid rows only). */
class PaymentStats extends \Filament\Widgets\StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $paid = fn () => SubscriptionPayment::query()->where('status', 'paid');
        $money = fn (float $v) => number_format($v, 0).' '.__('ر.س');

        return [
            Stat::make(__('هذا الشهر'), $money((float) $paid()->where('paid_at', '>=', now()->startOfMonth())->sum('amount')))
                ->icon(Heroicon::OutlinedCalendarDays)->color('success')
                ->description(trans_choice('{0} لا توجد دفعات|{1} دفعة واحدة|[2,*] :count دفعات', $paid()->where('paid_at', '>=', now()->startOfMonth())->count())),
            Stat::make(__('هذه السنة'), $money((float) $paid()->whereYear('paid_at', now()->year)->sum('amount')))
                ->icon(Heroicon::OutlinedArrowTrendingUp)->color('primary')
                ->description(__('منها ضريبة :v', ['v' => number_format((float) $paid()->whereYear('paid_at', now()->year)->sum('vat_amount'), 0)])),
            Stat::make(__('منذ البداية'), $money((float) $paid()->sum('amount')))
                ->icon(Heroicon::OutlinedBanknotes)->color('gray')
                ->description(trans_choice('{0} لا توجد دفعات|{1} دفعة واحدة|[2,*] :count دفعات', $paid()->count())),
        ];
    }
}
