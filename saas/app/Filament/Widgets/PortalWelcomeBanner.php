<?php

namespace App\Filament\Widgets;

use App\Support\Hijri;
use App\Support\Settings;
use Filament\Widgets\Widget;

/** The admin welcome banner, for the owner and client portals: organisation name, greeting and the portal's main actions. */
abstract class PortalWelcomeBanner extends Widget
{
    protected static ?int $sort = 0;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.welcome-banner';

    /** @return list<array{label: string, url: string, icon: string, external?: bool}> */
    abstract protected function actions(): array;

    public function getViewData(): array
    {
        $hour = (int) now()->format('G');

        return [
            'greeting' => $hour < 12 ? __('صباح الخير') : __('مساء الخير'),
            'name' => (string) auth()->user()?->name,
            'gregorian' => now()->locale(app()->getLocale())->translatedFormat('l، j F Y'),
            'hijri' => Hijri::toString(now()).' هـ',
            'subtitle' => __('مرحبًا بك في بوابتك لدى :org', ['org' => Settings::get('site_name', tenant('name'))]),
            'actions' => $this->actions(),
        ];
    }
}
