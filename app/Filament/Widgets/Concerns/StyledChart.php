<?php

namespace App\Filament\Widgets\Concerns;


/**
 * One look for every dashboard chart: Cairo, Western digits, recessive grid, legend under the plot,
 * hover tooltips. Options stay plain arrays: Filament prints them inside an HTML attribute. Colours come from the validated categorical order (blue, orange, aqua, yellow) —
 * see docs/decisions.md; single-series charts use the brand teal.
 */
trait StyledChart
{
    public const BLUE = '#2a78d6';

    public const ORANGE = '#eb6834';

    public const AQUA = '#1baf7a';

    public const YELLOW = '#eda100';

    public const TEAL = '#0f766e';

    protected function baseOptions(bool $legend = true, bool $axes = true): array
    {
        $font = ['family' => 'Cairo, sans-serif', 'size' => 12];
        $ticks = ['font' => $font, 'color' => '#6b7280'];
        $rtl = app()->getLocale() === 'ar'; // Arabic reads right to left: first month on the right, values on the right

        return [
            'locale' => 'en-US',
            'maintainAspectRatio' => false,
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'plugins' => [
                'legend' => ['display' => $legend, 'position' => 'bottom', 'rtl' => app()->getLocale() === 'ar',
                    'labels' => ['font' => $font, 'usePointStyle' => true, 'pointStyle' => 'rectRounded', 'padding' => 16, 'color' => '#374151']],
                'tooltip' => ['rtl' => app()->getLocale() === 'ar', 'titleFont' => $font, 'bodyFont' => $font, 'padding' => 10, 'cornerRadius' => 8,
                    'backgroundColor' => '#111827'],
            ],
            'scales' => $axes ? [
                'x' => ['reverse' => $rtl, 'grid' => ['display' => false], 'border' => ['display' => false], 'ticks' => $ticks],
                'y' => ['position' => $rtl ? 'right' : 'left', 'beginAtZero' => true, 'grid' => ['color' => '#eef0f3'], 'border' => ['display' => false],
                    'ticks' => $ticks + ['maxTicksLimit' => 6]],
            ] : [],
        ];
    }
}
