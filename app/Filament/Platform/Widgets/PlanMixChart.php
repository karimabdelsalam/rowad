<?php

namespace App\Filament\Platform\Widgets;

use App\Filament\Widgets\Concerns\StyledChart;
use App\Models\Plan;
use App\Models\Tenant;
use Filament\Widgets\ChartWidget;

/** How the offices split across plans (every status but cancelled). */
class PlanMixChart extends ChartWidget
{
    use StyledChart;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    protected ?string $maxHeight = '280px';

    public function getHeading(): string
    {
        return __('توزيع الباقات');
    }

    protected function getData(): array
    {
        $counts = Tenant::query()->where('status', '!=', Tenant::STATUS_CANCELLED)->selectRaw('plan_id, count(*) as n')->groupBy('plan_id')->pluck('n', 'plan_id');
        $plans = Plan::orderBy('sort_order')->get();
        $labels = $plans->map(fn (Plan $p) => $p->name_ar)->all();
        $data = $plans->map(fn (Plan $p) => (int) ($counts[$p->id] ?? 0))->all();
        if (($none = (int) ($counts[null] ?? $counts[''] ?? 0)) > 0) {
            $labels[] = __('بدون باقة');
            $data[] = $none;
        }

        return [
            'datasets' => [['data' => $data, 'backgroundColor' => [self::BLUE, self::ORANGE, self::AQUA, self::YELLOW, '#94a3b8'], 'borderWidth' => 2, 'borderColor' => '#fff']],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return $this->baseOptions(legend: true, axes: false) + ['cutout' => '62%'];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
