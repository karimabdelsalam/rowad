<?php

namespace App\Filament\Admin\Resources\Buildings\Schemas;

use App\Enums\UnitStatus;
use App\Filament\Admin\Resources\Units\UnitResource;
use App\Filament\Infolists\Parts;
use App\Models\Building;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Building card: occupancy at a glance, the units inside, location and land data. */
class BuildingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'lg' => 3])->components([
            Parts::header(Heroicon::OutlinedBuildingOffice2, function (Building $r) {
                $units = $r->units;
                $rented = $units->where('status', UnitStatus::Rented)->count();

                return [
                    'kicker' => $r->category?->title ?? __('عقار'),
                    'title' => $r->title,
                    'sub' => [implode(' · ', array_filter([$r->city?->name, $r->district?->name, $r->street])), $r->number ? __('رقم :n', ['n' => $r->number]) : null],
                    'facts' => [
                        Parts::fact('الوحدات', $units->count()),
                        Parts::fact('مؤجرة', $rented, 'success'),
                        Parts::fact('متاحة', $units->where('status', UnitStatus::Available)->count(), 'warning'),
                        Parts::fact('الإشغال', $units->count() > 0 ? round($rented / $units->count() * 100).' %' : '-'),
                    ],
                ];
            }),
            Grid::make(1)->columnSpan(['lg' => 2])->schema([
                Section::make(__('الوحدات'))->icon(Heroicon::OutlinedHomeModern)
                    ->description(fn (Building $r) => trans_choice('{0} لا توجد وحدات|{1} وحدة واحدة|{2} وحدتان|[3,*] :count وحدات', $r->units->count()))
                    ->headerActions([
                        \Filament\Actions\Action::make('addUnit')->label(__('إضافة وحدة'))->icon(Heroicon::OutlinedPlus)->link()
                            ->url(fn (Building $r) => UnitResource::getUrl('create', ['building_id' => $r->getKey()])),
                    ])
                    ->schema([
                        RepeatableEntry::make('units')->hiddenLabel()->state(fn (Building $r) => $r->units()->with('currentContract')->get())->columns(['default' => 2, 'md' => 6])
                            ->placeholder(__('لم تُسجَّل وحدات في هذا العقار بعد'))
                            ->schema([
                                Parts::link('title', 'الوحدة', fn ($record) => UnitResource::getUrl('view', ['record' => $record]))->columnSpan(2),
                                TextEntry::make('number')->label(__('الرقم'))->placeholder('-'),
                                TextEntry::make('status')->label(__('الحالة'))->badge(),
                                TextEntry::make('offer_type')->label(__('العرض'))->badge(),
                                Parts::money('rent_amount', 'الإيجار'),
                            ]),
                    ]),
                Section::make(__('الموقع'))->icon(Heroicon::OutlinedMapPin)->columns(['default' => 2, 'md' => 4])->schema([
                    TextEntry::make('city.name')->label(__('المدينة'))->placeholder('-'),
                    TextEntry::make('district.name')->label(__('الحي'))->placeholder('-'),
                    TextEntry::make('street')->label(__('الشارع'))->placeholder('-'),
                    TextEntry::make('zone')->label(__('المنطقة'))->placeholder('-'),
                    TextEntry::make('address')->label(__('العنوان'))->placeholder('-')->columnSpan(2),
                    TextEntry::make('coordinates')->label(__('الإحداثيات'))->placeholder('-')->color('primary')->columnSpan(2)
                        ->state(fn (Building $r) => filled($r->latitude) && filled($r->longitude) ? $r->latitude.', '.$r->longitude : null)
                        ->url(fn (Building $r) => filled($r->latitude) && filled($r->longitude) ? \App\Support\Maps::directionsUrl($r->latitude, $r->longitude) : null, true),
                    TextEntry::make('address_details')->label(__('وصف الموقع'))->placeholder('-')->columnSpanFull(),
                    ViewEntry::make('map')->hiddenLabel()->columnSpanFull()->view('filament.unit-map')
                        ->visible(fn (Building $r) => \App\Support\Maps::embedUrl($r->latitude, $r->longitude) !== null),
                ]),
            ]),
            Grid::make(1)->columnSpan(['lg' => 1])->schema([
                Section::make(__('بيانات العقار'))->icon(Heroicon::OutlinedClipboardDocumentList)->columns(2)->schema([
                    TextEntry::make('category.title')->label(__('التصنيف'))->badge()->color('gray')->placeholder('-'),
                    TextEntry::make('number')->label(__('رقم العقار'))->placeholder('-'),
                    TextEntry::make('floors')->label(__('الأدوار'))->numeric()->placeholder('-'),
                    TextEntry::make('ejar_property_id')->label(__('منصة إيجار'))->badge()
                        ->state(fn (\App\Models\Building $record) => filled($record->ejar_property_id) ? __('مسجل في إيجار') : __('غير مسجل'))
                        ->color(fn (\App\Models\Building $record) => filled($record->ejar_property_id) ? 'success' : 'gray')
                        ->visible(fn () => \App\Services\Ejar\EjarApi::connected()),
                    TextEntry::make('area')->label(__('المساحة (م²)'))->numeric(2)->placeholder('-'),
                    TextEntry::make('plan_number')->label(__('رقم المخطط'))->placeholder('-'),
                    TextEntry::make('plot_number')->label(__('رقم القطعة'))->placeholder('-'),
                ]),
                Section::make(__('الإيجار السنوي'))->icon(Heroicon::OutlinedBanknotes)->schema([
                    TextEntry::make('rent_total')->label(__('إجمالي إيجار الوحدات'))->weight('bold')->size('lg')
                        ->state(fn (Building $r) => Parts::amount($r->units->sum('rent_amount'))),
                    TextEntry::make('rented_total')->label(__('منها مؤجّر فعلًا'))->color('success')
                        ->state(fn (Building $r) => Parts::amount($r->units->where('status', UnitStatus::Rented)->sum('rent_amount'))),
                ]),
                Parts::record(false),
            ]),
        ]);
    }
}
