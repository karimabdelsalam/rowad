<?php

namespace App\Filament\Owner\Resources\Units;

use App\Enums\UnitStatus;
use App\Filament\Infolists\Parts;
use App\Filament\Owner\Resources\Units\Pages\ManageUnits;
use App\Filament\PortalResource;
use App\Models\RentContract;
use App\Models\Unit;
use App\Support\Portal;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Legacy ownerarea "my properties": the owner's units, their tenants and what each one brings in. */
class UnitResource extends PortalResource
{
    protected static ?string $model = Unit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'وحدة';

    protected static ?string $pluralModelLabel = 'وحداتي';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('owners', fn (Builder $q) => $q->whereKey(Portal::owner()->getKey()))
            ->with(['building', 'city', 'district', 'currentContract.clients', 'media', 'owners' => fn ($q) => $q->whereKey(Portal::owner()->getKey())]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'lg' => 3])->components([
            Parts::header(Heroicon::OutlinedHomeModern, fn (Unit $r) => [
                'kicker' => ($r->building?->title ?? __('وحدة مستقلة')).' · '.($r->offer_type?->getLabel() ?? ''),
                'title' => $r->title,
                'sub' => [implode(' · ', array_filter([$r->city?->name, $r->district?->name, $r->address])), $r->number ? __('رقم :n', ['n' => $r->number]) : null, $r->floor ? __('الدور :f', ['f' => $r->floor]) : null],
                'facts' => [
                    Parts::fact('الحالة', $r->status?->getLabel(), $r->status === UnitStatus::Rented ? 'success' : ($r->status === UnitStatus::Available ? 'warning' : null)),
                    Parts::fact('الإيجار السنوي', Parts::amount($r->rent_amount)),
                    Parts::fact('نسبتي', ($r->owners->first()?->pivot->share_percent ?? 100).' %'),
                ],
            ]),
            Grid::make(1)->columnSpan(['lg' => 2])->schema([
                Section::make(__('الصور'))->icon(Heroicon::OutlinedPhoto)
                    ->visible(fn (Unit $r) => $r->getMedia('photos')->isNotEmpty())
                    ->schema([
                        ImageEntry::make('photos')->hiddenLabel()->imageHeight(130)->limit(6)->limitedRemainingText()
                            ->state(fn (Unit $r) => $r->getMedia('photos')->map(fn ($m) => route('site.media', ['media' => $m, 'size' => 'thumb']))->all())
                            ->extraImgAttributes(['class' => 'rounded-xl object-cover', 'loading' => 'lazy']),
                    ]),
                Section::make(__('العقد الحالي'))->icon(Heroicon::OutlinedDocumentText)->columns(['default' => 2, 'md' => 4])->schema([
                    TextEntry::make('tenant')->label(__('المستأجر'))->weight('bold')->columnSpan(2)
                        ->state(fn (Unit $r) => $r->currentContract?->clients?->pluck('full_name')->implode('، '))
                        ->placeholder(__('لا يوجد عقد ساري — الوحدة متاحة')),
                    TextEntry::make('currentContract.number')->label(__('رقم العقد'))->placeholder('-'),
                    TextEntry::make('currentContract.status')->label(__('الحالة'))->badge()->placeholder('-'),
                    TextEntry::make('period')->label(__('المدة'))->placeholder('-')->columnSpan(2)
                        ->state(fn (Unit $r) => $r->currentContract instanceof RentContract
                            ? $r->currentContract->start_date?->format('Y-m-d').' ← '.$r->currentContract->end_date?->format('Y-m-d') : null),
                    TextEntry::make('currentContract.payment_cycle')->label(__('دورة السداد'))->badge()->color('gray')->placeholder('-'),
                    TextEntry::make('next_due')->label(__('الدفعة القادمة'))->placeholder('-')
                        ->state(fn (Unit $r) => $r->currentContract instanceof RentContract && $r->currentContract->next_payment_date
                            ? $r->currentContract->next_payment_date->format('Y-m-d').' · '.number_format((float) $r->currentContract->next_payment_amount, 2) : null),
                ]),
                Section::make(__('بيانات الوحدة'))->icon(Heroicon::OutlinedClipboardDocumentList)->columns(['default' => 2, 'md' => 4])->schema([
                    TextEntry::make('category.title')->label(__('التصنيف'))->placeholder('-'),
                    TextEntry::make('number')->label(__('الرقم'))->placeholder('-'),
                    TextEntry::make('floor')->label(__('الدور'))->placeholder('-'),
                    TextEntry::make('rooms')->label(__('الغرف'))->numeric()->placeholder('-'),
                    TextEntry::make('area')->label(__('المساحة (م²)'))->numeric(2)->placeholder('-'),
                    TextEntry::make('is_furnished')->label(__('التأثيث'))->badge()->color('gray')->formatStateUsing(fn ($state) => $state ? __('مفروشة') : __('غير مفروشة')),
                    TextEntry::make('details')->label(__('التفاصيل'))->placeholder('-')->columnSpanFull()->prose(),
                ]),
            ]),
            Grid::make(1)->columnSpan(['lg' => 1])->schema([
                Section::make(__('ملكيتي'))->icon(Heroicon::OutlinedChartPie)->columns(2)->schema([
                    TextEntry::make('my_share')->label(__('نسبة الملكية'))->weight('bold')->size('lg')
                        ->state(fn (Unit $r) => ($r->owners->first()?->pivot->share_percent ?? 100).' %'),
                    TextEntry::make('my_expenses')->label(__('نسبة المصروفات'))
                        ->state(fn (Unit $r) => ($r->owners->first()?->pivot->expenses_percent ?? $r->owners->first()?->pivot->share_percent ?? 100).' %'),
                    TextEntry::make('my_rent')->label(__('حصتي من الإيجار السنوي'))->color('success')->weight('bold')->columnSpanFull()
                        ->state(fn (Unit $r) => Parts::amount((float) $r->rent_amount * ((float) ($r->owners->first()?->pivot->share_percent ?? 100)) / 100)),
                ]),
                Section::make(__('الأسعار'))->icon(Heroicon::OutlinedBanknotes)->columns(2)->schema([
                    Parts::money('rent_amount', 'الإيجار السنوي')->weight('bold'),
                    Parts::money('monthly_rent', 'الإيجار الشهري'),
                    TextEntry::make('rent_payment_cycle')->label(__('دورة السداد'))->badge()->color('gray')->placeholder('-'),
                    TextEntry::make('management_fee')->label(__('رسوم الإدارة'))->numeric(2)->placeholder('-'),
                ]),
                Section::make(__('الموقع'))->icon(Heroicon::OutlinedMapPin)->schema([
                    TextEntry::make('city.name')->label(__('المدينة'))->placeholder('-'),
                    TextEntry::make('district.name')->label(__('الحي'))->placeholder('-'),
                    TextEntry::make('address')->label(__('العنوان'))->placeholder('-'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')->label('')->circular()->size(44)
                    ->state(fn (Unit $r) => $r->getMedia('photos')->first() ? route('site.media', ['media' => $r->getMedia('photos')->first(), 'size' => 'thumb']) : null),
                TextColumn::make('title')->label(__('الوحدة'))->weight('bold')->searchable()
                    ->description(fn (Unit $r) => implode(' · ', array_filter([$r->building?->title, $r->city?->name]))),
                TextColumn::make('status')->label(__('الحالة'))->badge(),
                TextColumn::make('tenant')->label(__('المستأجر'))->placeholder(__('شاغرة'))
                    ->state(fn (Unit $r) => $r->currentContract?->clients?->pluck('full_name')->implode('، ')),
                TextColumn::make('rent_amount')->label(__('الإيجار السنوي'))->numeric(2)->weight('bold'),
                TextColumn::make('my_share')->label(__('نسبتي'))->badge()->color('info')
                    ->state(fn (Unit $r) => ($r->owners->first()?->pivot->share_percent ?? 100).' %'),
            ])
            ->filters([SelectFilter::make('status')->options(UnitStatus::class)])
            ->recordActions([
                ViewAction::make()->label(__('التفاصيل'))->modalWidth('6xl'),
                \Filament\Actions\Action::make('print')->label(__('طباعة'))->icon(Heroicon::OutlinedPrinter)
                    ->url(fn ($record) => route('documents.print', ['type' => 'unit', 'id' => $record->getKey()]), true),
                \App\Filament\Support\DocumentActions::pdf('unit', portal: true),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUnits::route('/')];
    }
}
