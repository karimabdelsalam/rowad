<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\UnitStatus;
use App\Filament\Widgets\Concerns\StyledChart;
use App\Services\Reports\DashboardStats;
use Filament\Widgets\ChartWidget;

/** Share of active units by status; counts are in the legend so colour is never the only cue. */
class UnitStatusChart extends ChartWidget
{
    use StyledChart;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    protected ?string $maxHeight = '300px';

    public function getHeading(): string
    {
        return __('حالة الوحدات');
    }

    public function getDescription(): ?string
    {
        return __('الوحدات النشطة حسب الحالة');
    }

    protected function getData(): array
    {
        $counts = app(DashboardStats::class)->unitStatuses();
        $order = [[UnitStatus::Rented, self::BLUE], [UnitStatus::Available, self::ORANGE], [UnitStatus::Sold, self::AQUA], [UnitStatus::Reserved, self::YELLOW]];
        $order = array_values(array_filter($order, fn ($row) => ($counts[$row[0]->value] ?? 0) > 0)) ?: [[UnitStatus::Available, self::ORANGE]];

        return [
            'datasets' => [[
                'data' => array_map(fn ($row) => $counts[$row[0]->value] ?? 0, $order),
                'backgroundColor' => array_column($order, 1),
                'borderColor' => '#ffffff', 'borderWidth' => 2, 'hoverOffset' => 6,
            ]],
            'labels' => array_map(fn ($row) => $row[0]->getLabel().' ('.($counts[$row[0]->value] ?? 0).')', $order),
        ];
    }

    protected function getOptions(): array
    {
        return array_replace_recursive($this->baseOptions(axes: false), ['cutout' => '68%', 'interaction' => ['mode' => 'nearest', 'intersect' => true]]);
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
