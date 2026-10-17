<?php

namespace App\Filament\Admin\Resources\Units\Schemas;

use App\Filament\Admin\Resources\RentContracts\RentContractResource;
use App\Filament\Admin\Resources\SaleContracts\SaleContractResource;
use App\Models\RentContract;
use App\Models\SaleContract;
use App\Models\Unit;
use App\Models\UnitPlot;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Unit card: grouped like the legacy building view — summary, location, deeds & plots, prices, management, record. */
class UnitInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                self::header(),

                Tabs::make('unit')->contained(false)->persistTabInQueryString()->tabs([
                    Tab::make(__('نظرة عامة'))->icon(Heroicon::OutlinedHomeModern)->schema([
                        Grid::make(['default' => 1, 'lg' => 3])->schema([
                            Grid::make(1)->columnSpan(['lg' => 2])->schema([
                                Section::make(__('بيانات الوحدة'))->icon(Heroicon::OutlinedHomeModern)->columns(['default' => 2, 'md' => 4])->schema([
                        TextEntry::make('title')->label(__('العنوان'))->weight('bold')->columnSpan(2),
                        TextEntry::make('building.title')->label(__('العقار'))->placeholder('-')->columnSpan(2),
                        TextEntry::make('offer_type')->label(__('نوع العرض'))->badge(),
                        TextEntry::make('status')->label(__('الحالة'))->badge(),
                        TextEntry::make('category.title')->label(__('التصنيف'))->placeholder('-'),
                        TextEntry::make('clientCategory.title')->label(__('فئة العملاء'))->placeholder('-'),
                        TextEntry::make('number')->label(__('رقم الوحدة'))->placeholder('-'),
                        TextEntry::make('floor')->label(__('الدور'))->placeholder('-'),
                        TextEntry::make('rooms')->label(__('الغرف'))->numeric()->placeholder('-'),
                        TextEntry::make('area')->label(__('المساحة (م²)'))->numeric(2)->placeholder('-'),
                        IconEntry::make('is_furnished')->label(__('مفروشة'))->boolean(),
                        TextEntry::make('max_occupants')->label(__('أقصى عدد للسكان'))->numeric()->placeholder('-'),
                        IconEntry::make('is_active')->label(__('مفعلة'))->boolean(),
                        IconEntry::make('is_published')->label(__('على الموقع'))->boolean(),
                        TextEntry::make('details')->label(__('التفاصيل'))->placeholder('-')->columnSpanFull()->prose(),
                    ]),
                            ]),
                            Grid::make(1)->columnSpan(['lg' => 1])->schema([
                                Section::make(__('العقد الحالي'))->icon(Heroicon::OutlinedDocumentText)->schema([
                        TextEntry::make('current_contract')->hiddenLabel()
                            ->state(fn (Unit $record) => self::currentContractLabel($record))
                            ->url(fn (Unit $record) => self::currentContractUrl($record))
                            ->color('primary')->weight('bold')->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                            ->placeholder(__('لا يوجد عقد ساري — الوحدة متاحة')),
                        TextEntry::make('current_contract_party')->label(__('الطرف'))
                            ->state(fn (Unit $record) => $record->currentContract?->clients?->pluck('full_name')->implode('، '))
                            ->visible(fn (Unit $record) => $record->currentContract !== null),
                        TextEntry::make('current_contract_period')->label(__('المدة'))
                            ->state(fn (Unit $record) => $record->currentContract instanceof RentContract
                                ? $record->currentContract->start_date?->format('Y-m-d').' ← '.$record->currentContract->end_date?->format('Y-m-d')
                                : null)
                            ->visible(fn (Unit $record) => $record->currentContract instanceof RentContract),
                    ]),
                                Section::make(__('الأسعار'))->icon(Heroicon::OutlinedBanknotes)->columns(2)->schema([
                        TextEntry::make('sale_price')->label(__('سعر البيع المطلوب'))->numeric(2)->suffix(' '.__('ر.س'))->weight('bold')->columnSpanFull()
                            ->visible(fn (\App\Models\Unit $r) => $r->offer_type === \App\Enums\OfferType::Sale),
                        TextEntry::make('rent_amount')->label(__('الإيجار السنوي'))->numeric(2)->suffix(' '.__('ر.س'))->weight('bold')->placeholder('-')->columnSpanFull()
                            ->hidden(fn (\App\Models\Unit $r) => $r->offer_type === \App\Enums\OfferType::Sale && ! (float) $r->rent_amount),
                        TextEntry::make('monthly_rent')->label(__('الإيجار الشهري'))->numeric(2)->placeholder('-'),
                        TextEntry::make('daily_rent')->label(__('الإيجار اليومي'))->numeric(2)->placeholder('-'),
                        TextEntry::make('rent_payment_cycle')->label(__('دورة السداد'))->badge()->placeholder('-'),
                        TextEntry::make('rent_period')->label(__('مدة الإيجار'))->badge()->placeholder('-'),
                        TextEntry::make('rent_commission')->label(__('عمولة الإيجار'))->numeric(2)->placeholder('-'),
                        TextEntry::make('sale_commission')->label(__('عمولة البيع'))->numeric(2)->placeholder('-'),
                        IconEntry::make('vat_applicable')->label(__('خاضع للضريبة'))->boolean(),
                    ]),
                                Section::make(__('السعر المقترح'))->icon(Heroicon::OutlinedSparkles)
                        ->description(__('من عقود وحدات مماثلة لدى المؤسسة'))
                        ->visible(fn (Unit $record) => $record->offer_type !== \App\Enums\OfferType::Sale)
                        ->schema([
                            \Filament\Infolists\Components\ViewEntry::make('rent_advice')->hiddenLabel()
                                ->view('filament.insights.rent-advice')
                                ->viewData(fn (Unit $record) => ['advice' => app(\App\Services\Insights\RentAdvisor::class)->forUnit($record)]),
                        ]),
                            ]),
                        ]),
                    ]),
                    Tab::make(__('الصور والموقع'))->icon(Heroicon::OutlinedMapPin)->schema([
                                Section::make(__('الصور'))->icon(Heroicon::OutlinedPhoto)
                        ->visible(fn (Unit $record) => $record->getMedia('photos')->isNotEmpty())
                        ->schema([
                            ImageEntry::make('photos')->hiddenLabel()->imageHeight(130)->limit(8)->limitedRemainingText()
                                ->state(fn (Unit $record) => $record->getMedia('photos')
                                    ->map(fn ($media) => route('site.media', ['media' => $media, 'size' => 'thumb']))->all())
                                ->extraImgAttributes(['class' => 'rounded-xl object-cover', 'loading' => 'lazy']),
                        ]),
                                Section::make(__('الموقع'))->icon(Heroicon::OutlinedMapPin)->columns(['default' => 2, 'md' => 4])->schema([
                        TextEntry::make('city.name')->label(__('المدينة'))->placeholder('-'),
                        TextEntry::make('district.name')->label(__('الحي'))->placeholder('-'),
                        TextEntry::make('street')->label(__('الشارع'))->placeholder('-'),
                        TextEntry::make('zone')->label(__('المنطقة'))->placeholder('-'),
                        TextEntry::make('address')->label(__('العنوان التفصيلي'))->placeholder('-')->columnSpan(2),
                        TextEntry::make('coordinates')->label(__('الإحداثيات'))
                            ->state(fn (Unit $record) => filled($record->latitude) && filled($record->longitude) ? $record->latitude.', '.$record->longitude : null)
                            ->url(fn (Unit $record) => filled($record->latitude) && filled($record->longitude) ? \App\Support\Maps::directionsUrl($record->latitude, $record->longitude) : null, true)
                            ->color('primary')->placeholder('-')->columnSpan(2),
                        TextEntry::make('address_details')->label(__('وصف الموقع'))->placeholder('-')->columnSpanFull(),
                        \Filament\Infolists\Components\ViewEntry::make('map')->hiddenLabel()->columnSpanFull()
                            ->view('filament.unit-map')
                            ->visible(fn (Unit $record) => \App\Support\Maps::embedUrl($record->latitude, $record->longitude) !== null),
                    ]),
                    ]),
                    Tab::make(__('الصكوك والقطع'))->icon(Heroicon::OutlinedDocumentCheck)->schema([
                                Section::make(__('الصكوك والقطع'))->icon(Heroicon::OutlinedDocumentCheck)->schema([
                        Grid::make(['default' => 2, 'md' => 4])->schema([
                            TextEntry::make('deedType.title')->label(__('نوع الصك'))->placeholder('-'),
                            TextEntry::make('deed_number')->label(__('رقم الصك'))->placeholder('-'),
                            TextEntry::make('plan_number')->label(__('رقم المخطط'))->placeholder('-'),
                            TextEntry::make('plot_number')->label(__('رقم القطعة'))->placeholder('-'),
                            TextEntry::make('ejar_unit_id')->label(__('منصة إيجار'))->badge()
                                ->state(fn (Unit $record) => filled($record->ejar_unit_id) ? __('مربوطة بإيجار') : __('غير مربوطة'))
                                ->color(fn (Unit $record) => filled($record->ejar_unit_id) ? 'success' : 'gray')
                                ->visible(fn () => \App\Services\Ejar\EjarApi::connected()),
                        ]),
                        RepeatableEntry::make('deeds')->label(__('الصكوك'))->columns(['default' => 2, 'md' => 4])
                            ->visible(fn (Unit $record) => $record->deeds->count() > 1)
                            ->schema([
                                TextEntry::make('deedType.title')->label(__('نوع الصك'))->placeholder('-'),
                                TextEntry::make('number')->label(__('رقم الصك')),
                                TextEntry::make('source')->label(__('مصدر الصك'))->placeholder('-'),
                                TextEntry::make('issue_date')->label(__('تاريخ الصك'))->placeholder('-'),
                            ]),
                        RepeatableEntry::make('landPlots')->label(__('القطع والحدود'))->columns(['default' => 2, 'md' => 4])
                            ->visible(fn (Unit $record) => $record->landPlots->isNotEmpty())
                            ->schema([
                                TextEntry::make('number')->label(__('رقم القطعة'))->placeholder('-'),
                                TextEntry::make('area')->label(__('المساحة (م²)'))->placeholder('-'),
                                TextEntry::make('boundaries')->label(__('الحدود'))->columnSpan(['default' => 2, 'md' => 2])
                                    ->state(fn (UnitPlot $record) => self::boundaries($record))->listWithLineBreaks(),
                            ]),
                    ]),
                    ]),
                    Tab::make(__('الملاك والإدارة'))->icon(Heroicon::OutlinedUserGroup)->schema([
                        Grid::make(['default' => 1, 'lg' => 2])->schema([
                                Section::make(__('الملاك'))->icon(Heroicon::OutlinedUserGroup)->schema([
                        TextEntry::make('owners.full_name')->hiddenLabel()->listWithLineBreaks()->bulleted()->placeholder('-'),
                    ]),
                                Section::make(__('رسوم الإدارة'))->icon(Heroicon::OutlinedCalculator)->columns(2)
                        ->visible(fn (Unit $record) => (float) $record->management_fee > 0)->schema([
                        TextEntry::make('management_fee')->label(__('المبلغ'))->numeric(2)->placeholder('-'),
                        TextEntry::make('management_fee_cycle')->label(__('الدورة'))->badge()->placeholder('-'),
                        TextEntry::make('management_fee_start_date')->label(__('تاريخ البداية'))->date('Y-m-d')->placeholder('-'),
                        TextEntry::make('management_fee_next_date')->label(__('الاستحقاق القادم'))->date('Y-m-d')->placeholder('-'),
                    ]),
                        ]),
                    ]),
                    Tab::make(__('السجل'))->icon(Heroicon::OutlinedClock)->schema([
                                Section::make(__('السجل'))->icon(Heroicon::OutlinedClock)->columns(2)->schema([
                        TextEntry::make('calendar')->label(__('التقويم'))->badge(),
                        TextEntry::make('created_at')->label(__('تاريخ الإضافة'))->dateTime('Y-m-d H:i'),
                        TextEntry::make('updated_at')->label(__('آخر تعديل'))->dateTime('Y-m-d H:i'),
                        TextEntry::make('deleted_at')->label(__('تاريخ الحذف'))->dateTime('Y-m-d H:i')
                            ->visible(fn (Unit $record): bool => $record->trashed()),
                    ]),
                    ]),
                ]),
            ]);
    }

    /** The unit at a glance: where it is, its state, price, tenant and owners. */
    private static function header(): \Filament\Infolists\Components\ViewEntry
    {
        return \App\Filament\Infolists\Parts::header(Heroicon::OutlinedHomeModern, function (Unit $r) {
            $contract = $r->currentContract;

            return [
                'kicker' => implode(' · ', array_filter([$r->building?->title, $r->category?->title])) ?: __('وحدة'),
                'title' => $r->title,
                'sub' => [trim(($r->city?->name ?? '').' '.($r->district?->name ?? '')), (float) $r->area > 0 ? rtrim(rtrim((string) $r->area, '0'), '.').' '.__('م²') : null,
                    $r->rooms ? trans_choice('{1} غرفة|[2,10] :count غرف|[11,*] :count غرفة', $r->rooms) : null],
                'facts' => array_values(array_filter([
                    \App\Filament\Infolists\Parts::fact('الحالة', $r->status?->getLabel(), $contract ? 'success' : 'warning'),
                    \App\Filament\Infolists\Parts::fact('العرض', $r->offer_type?->getLabel()),
                    $r->offer_type === \App\Enums\OfferType::Sale && (float) $r->sale_price > 0 ? \App\Filament\Infolists\Parts::fact('سعر البيع', number_format((float) $r->sale_price, 2))
                        : ((float) $r->rent_amount > 0 ? \App\Filament\Infolists\Parts::fact('الإيجار السنوي', number_format((float) $r->rent_amount, 2)) : null),
                    $contract ? \App\Filament\Infolists\Parts::fact($contract instanceof RentContract ? 'المستأجر' : 'المشتري', $contract->clients?->pluck('full_name')->first()) : null,
                    \App\Filament\Infolists\Parts::fact('الملاك', $r->owners->pluck('full_name')->join('، ') ?: '-'),
                ])),
            ];
        });
    }

    /** @return list<string> */
    private static function boundaries(UnitPlot $plot): array
    {
        $names = ['north' => __('شمالًا'), 'east' => __('شرقًا'), 'south' => __('جنوبًا'), 'west' => __('غربًا')];

        return collect(UnitPlot::SIDES)
            ->filter(fn (string $side) => filled($plot->describeSide($side)))
            ->map(fn (string $side) => $names[$side].': '.$plot->describeSide($side, true)
                .(filled($plot->sides[$side]['length'] ?? null) ? ' '.__('بطول').' '.$plot->sides[$side]['length'].' '.__('م') : ''))
            ->values()->all() ?: ['-'];
    }

    private static function currentContractLabel(Unit $unit): ?string
    {
        $contract = $unit->currentContract;

        return match (true) {
            $contract instanceof RentContract => __('عقد إيجار').' #'.($contract->number ?: $contract->id),
            $contract instanceof SaleContract => __('عقد بيع').' #'.($contract->number ?? $contract->id),
            default => null,
        };
    }

    private static function currentContractUrl(Unit $unit): ?string
    {
        $contract = $unit->currentContract;

        return match (true) {
            $contract instanceof RentContract => RentContractResource::getUrl('view', ['record' => $contract]),
            $contract instanceof SaleContract => SaleContractResource::getUrl('view', ['record' => $contract]),
            default => null,
        };
    }
}
