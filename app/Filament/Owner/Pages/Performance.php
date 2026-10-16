<?php

namespace App\Filament\Owner\Pages;

use App\Services\Insights\OwnerPerformance;
use App\Support\Portal;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/** The owner's portfolio as an investor sees it (net income, occupancy, arrears and yield per property). */
class Performance extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'performance';

    protected string $view = 'filament.owner.performance';

    #[Url]
    public ?int $year = null;

    public static function getNavigationLabel(): string
    {
        return __('أداء أملاكي');
    }

    public function getTitle(): string
    {
        return __('أداء أملاكي');
    }

    public function getSubheading(): ?string
    {
        return __('صافي دخل كل عقار بعد العمولات والمصروفات، ونسبة إشغاله وعائده.');
    }

    protected function getViewData(): array
    {
        $current = (int) now()->year;
        $this->year = in_array($this->year, range($current - 4, $current), true) ? $this->year : $current;

        return [
            'report' => app(OwnerPerformance::class)->forOwner(Portal::owner(), $this->year),
            'years' => range($current, $current - 4),
        ];
    }
}
