<?php

namespace App\Filament\Admin\Widgets;

use App\Services\Reports\DashboardStats;
use Filament\Widgets\ChartWidget;

/** Legacy graphicStats: due vs collected per month. */
class CollectionsChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    public function getHeading(): string
    {
        return __('المستحق والمحصل خلال :year', ['year' => now()->year]);
    }

    protected function getData(): array
    {
        $data = app(DashboardStats::class)->monthlyCollections(now()->year);

        return [
            'datasets' => [
                ['label' => __('المستحق'), 'data' => $data['due'], 'backgroundColor' => '#f59e0b'],
                ['label' => __('المحصل'), 'data' => $data['collected'], 'backgroundColor' => '#0d9488'],
            ],
            'labels' => $data['labels'],
        ];
    }

    /** Western digits on the axes, like every other figure in the panel. */
    protected function getOptions(): array
    {
        return ['locale' => 'en-US'];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
