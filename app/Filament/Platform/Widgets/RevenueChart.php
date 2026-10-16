<?php

namespace App\Filament\Platform\Widgets;

use App\Filament\Widgets\Concerns\StyledChart;
use App\Models\SubscriptionPayment;
use Filament\Widgets\ChartWidget;

/** Money received per month (paid subscription payments), last twelve months. */
class RevenueChart extends ChartWidget
{
    use StyledChart;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 6];

    protected ?string $maxHeight = '280px';

    public function getHeading(): string
    {
        return __('الإيرادات الشهرية');
    }

    public function getDescription(): ?string
    {
        return __('الدفعات المحصّلة شامل الضريبة — آخر 12 شهرًا');
    }

    protected function getData(): array
    {
        $months = collect(range(11, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $payments = SubscriptionPayment::query()->where('status', 'paid')->where('paid_at', '>=', $months->first())->get(['paid_at', 'amount']);
        $data = $months->map(fn ($m) => round((float) $payments->filter(fn ($p) => $p->paid_at?->isSameMonth($m))->sum('amount'), 2))->all();

        return [
            'datasets' => [[
                'label' => __('الإيراد'), 'data' => $data, 'borderColor' => self::BLUE, 'backgroundColor' => 'rgba(42, 120, 214, .12)',
                'fill' => true, 'tension' => 0.35, 'borderWidth' => 2, 'pointRadius' => 4, 'pointBackgroundColor' => '#fff', 'pointBorderWidth' => 2,
            ]],
            'labels' => $months->map(fn ($m) => $m->translatedFormat('M y'))->all(),
        ];
    }

    protected function getOptions(): array
    {
        return $this->baseOptions(legend: false);
    }

    protected function getType(): string
    {
        return 'line';
    }
}
