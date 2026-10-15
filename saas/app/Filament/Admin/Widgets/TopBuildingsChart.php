<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Widgets\Concerns\StyledChart;
use App\Services\Reports\DashboardStats;
use Filament\Widgets\ChartWidget;

/** Buildings ranked by what they brought in this year. */
class TopBuildingsChart extends ChartWidget
{
    use StyledChart;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 6];

    protected ?string $maxHeight = '260px';

    public function getHeading(): string
    {
        return __('أعلى المباني تحصيلًا');
    }

    public function getDescription(): ?string
    {
        return __('إجمالي المحصل منذ بداية :year', ['year' => now()->year]);
    }

    protected function getData(): array
    {
        $rows = app(DashboardStats::class)->topBuildings(6);

        return [
            'datasets' => [[
                'label' => __('المحصل'), 'data' => array_values($rows), 'backgroundColor' => self::TEAL,
                'borderRadius' => 4, 'borderSkipped' => 'start', 'maxBarThickness' => 16,
            ]],
            'labels' => array_keys($rows),
        ];
    }

    protected function getOptions(): array
    {
        $options = $this->baseOptions(legend: false);
        [$options['scales']['x'], $options['scales']['y']] = [$options['scales']['y'], $options['scales']['x']];
        $rtl = app()->getLocale() === 'ar';
        $options['scales']['x'] = ['reverse' => $rtl, 'position' => 'bottom'] + $options['scales']['x'];
        $options['scales']['y'] = ['position' => $rtl ? 'right' : 'left', 'reverse' => false] + $options['scales']['y'];

        return $options + ['indexAxis' => 'y'];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
