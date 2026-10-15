<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Widgets\Concerns\StyledChart;
use App\Services\Reports\DashboardStats;
use Filament\Widgets\ChartWidget;

/** How much of each month's dues was collected, over the last 12 months. */
class CollectionRateChart extends ChartWidget
{
    use StyledChart;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 6];

    protected ?string $maxHeight = '260px';

    public function getHeading(): string
    {
        return __('نسبة التحصيل (%)');
    }

    public function getDescription(): ?string
    {
        return __('نسبة ما حُصّل من مستحقات كل شهر — آخر 12 شهرًا');
    }

    protected function getData(): array
    {
        $data = app(DashboardStats::class)->collectionRate(12);

        return [
            'datasets' => [[
                'label' => __('نسبة التحصيل'), 'data' => $data['rate'], 'borderColor' => self::TEAL, 'backgroundColor' => 'rgba(15,118,110,0.08)',
                'borderWidth' => 2, 'fill' => true, 'tension' => 0.35, 'pointRadius' => 4, 'pointHoverRadius' => 6,
                'pointBackgroundColor' => self::TEAL, 'pointBorderColor' => '#ffffff', 'pointBorderWidth' => 2, 'spanGaps' => true,
            ]],
            'labels' => $data['labels'],
        ];
    }

    protected function getOptions(): array
    {
        return array_replace_recursive($this->baseOptions(legend: false), ['scales' => ['y' => ['max' => 100,
            'ticks' => ['stepSize' => 25]]]]);
    }

    protected function getType(): string
    {
        return 'line';
    }
}
