<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Widgets\Concerns\StyledChart;
use App\Services\Reports\DashboardStats;
use Filament\Widgets\ChartWidget;

/** Legacy graphicStats: due vs collected per month of the current year. */
class CollectionsChart extends ChartWidget
{
    use StyledChart;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 8];

    protected ?string $maxHeight = '300px';

    public function getHeading(): string
    {
        return __('التحصيل الشهري :year', ['year' => now()->year]);
    }

    public function getDescription(): ?string
    {
        return __('المستحق مقابل المحصل لكل شهر');
    }

    protected function getData(): array
    {
        $data = app(DashboardStats::class)->monthlyCollections(now()->year);
        $bar = ['borderRadius' => 4, 'borderSkipped' => 'start', 'maxBarThickness' => 18, 'categoryPercentage' => 0.7, 'barPercentage' => 0.9];

        return [
            'datasets' => [
                ['label' => __('المحصل'), 'data' => $data['collected'], 'backgroundColor' => self::BLUE] + $bar,
                ['label' => __('المستحق'), 'data' => $data['due'], 'backgroundColor' => self::ORANGE] + $bar,
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getOptions(): array
    {
        return $this->baseOptions();
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
