<?php

namespace App\Filament\Platform\Widgets;

use App\Filament\Widgets\Concerns\StyledChart;
use App\Models\Tenant;
use Filament\Widgets\ChartWidget;

/** New offices per month over the last twelve months, paid ones stacked on trials. */
class OfficeGrowthChart extends ChartWidget
{
    use StyledChart;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 6];

    protected ?string $maxHeight = '280px';

    public function getHeading(): string
    {
        return __('المؤسسات الجديدة شهريًا');
    }

    public function getDescription(): ?string
    {
        return __('آخر 12 شهرًا — المدفوعة مقابل التي ما زالت أو كانت تجريبية');
    }

    protected function getData(): array
    {
        $months = collect(range(11, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $offices = Tenant::query()->where('created_at', '>=', $months->first())->get(['created_at', 'status']);
        $paid = $trial = [];
        foreach ($months as $m) {
            $inMonth = $offices->filter(fn (Tenant $t) => $t->created_at->isSameMonth($m));
            $paid[] = $inMonth->whereIn('status', [Tenant::STATUS_ACTIVE, Tenant::STATUS_PAST_DUE])->count();
            $trial[] = $inMonth->count() - end($paid);
        }
        $bar = ['borderRadius' => 4, 'borderSkipped' => 'start', 'maxBarThickness' => 22];

        return [
            'datasets' => [
                ['label' => __('مشتركة'), 'data' => $paid, 'backgroundColor' => self::TEAL] + $bar,
                ['label' => __('تجريبية'), 'data' => $trial, 'backgroundColor' => self::YELLOW] + $bar,
            ],
            'labels' => $months->map(fn ($m) => $m->translatedFormat('M y'))->all(),
        ];
    }

    protected function getOptions(): array
    {
        $o = $this->baseOptions();
        $o['scales']['x']['stacked'] = true;
        $o['scales']['y']['stacked'] = true;
        $o['scales']['y']['ticks']['precision'] = 0;

        return $o;
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
