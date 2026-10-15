<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\ReceiptVouchers\ReceiptVoucherResource;
use App\Filament\Admin\Resources\RentContracts\RentContractResource;
use App\Filament\Admin\Resources\Units\UnitResource;
use App\Support\Hijri;
use Filament\Widgets\Widget;

/** Greeting, today's date in both calendars and the actions staff take most. */
class WelcomeBanner extends Widget
{
    protected static ?int $sort = 0;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.welcome-banner';

    /** @return array{greeting: string, name: string, gregorian: string, hijri: string, actions: list<array{label: string, url: string, icon: string}>} */
    public function getViewData(): array
    {
        $hour = (int) now()->format('G');
        $actions = [];
        foreach ([
            [RentContractResource::class, __('عقد إيجار جديد'), 'heroicon-o-document-plus'],
            [ReceiptVoucherResource::class, __('سند قبض'), 'heroicon-o-banknotes'],
            [UnitResource::class, __('وحدة جديدة'), 'heroicon-o-home-modern'],
        ] as [$resource, $label, $icon]) {
            if ($resource::canCreate()) {
                $actions[] = ['label' => $label, 'url' => $resource::getUrl('create'), 'icon' => $icon];
            }
        }

        return [
            'greeting' => $hour < 12 ? __('صباح الخير') : __('مساء الخير'),
            'name' => (string) auth()->user()?->name,
            'gregorian' => now()->locale(app()->getLocale())->translatedFormat('l، j F Y'),
            'hijri' => Hijri::toString(now()).' هـ',
            'actions' => $actions,
        ];
    }
}
