<?php

namespace App\Filament\Client\Resources\Units;

use App\Filament\Client\Resources\Units\Pages\ManageUnits;
use App\Filament\Infolists\Parts;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Legacy ccp/units: the units the client rents or bought. */
class UnitResource extends PortalResource
{
    protected static ?string $model = Unit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'وحدة';

    protected static ?string $pluralModelLabel = 'وحداتي';

    public static function getEloquentQuery(): Builder
    {
        return Portal::clientUnits(Portal::client())->with(['building', 'city', 'district', 'currentContract', 'media']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'lg' => 3])->components([
            Parts::header(Heroicon::OutlinedHomeModern, fn (Unit $r) => [
                'kicker' => ($r->building?->title ?? __('وحدة مستقلة')).' · '.($r->offer_type?->getLabel() ?? ''),
                'title' => $r->title,
                'sub' => [implode(' · ', array_filter([$r->city?->name, $r->district?->name, $r->address])), $r->number ? __('رقم :n', ['n' => $r->number]) : null, $r->floor ? __('الدور :f', ['f' => $r->floor]) : null],
                'facts' => array_values(array_filter([
                    $r->currentContract ? Parts::fact('العقد', $r->currentContract->number) : null,
                    $r->currentContract instanceof RentContract ? Parts::fact('ينتهي في', $r->currentContract->end_date?->format('Y-m-d'), $r->currentContract->end_date?->diffInDays(today(), false) > -60 ? 'warning' : null) : null,
                    $r->currentContract instanceof RentContract && $r->currentContract->next_payment_date ? Parts::fact('الدفعة القادمة', $r->currentContract->next_payment_date->format('Y-m-d'), 'info') : null,
                ])),
            ]),
            Grid::make(1)->columnSpan(['lg' => 2])->schema([
                Section::make(__('الصور'))->icon(Heroicon::OutlinedPhoto)
                    ->visible(fn (Unit $r) => $r->getMedia('photos')->isNotEmpty())
                    ->schema([
                        ImageEntry::make('photos')->hiddenLabel()->imageHeight(130)->limit(6)->limitedRemainingText()
                            ->state(fn (Unit $r) => $r->getMedia('photos')->map(fn ($m) => route('site.media', ['media' => $m, 'size' => 'thumb']))->all())
                            ->extraImgAttributes(['class' => 'rounded-xl object-cover', 'loading' => 'lazy']),
                    ]),
                Section::make(__('بيانات الوحدة'))->icon(Heroicon::OutlinedClipboardDocumentList)->columns(['default' => 2, 'md' => 4])->schema([
                    TextEntry::make('building.title')->label(__('العقار'))->placeholder('-')->columnSpan(2),
                    TextEntry::make('number')->label(__('الرقم'))->placeholder('-'),
                    TextEntry::make('floor')->label(__('الدور'))->placeholder('-'),
                    TextEntry::make('rooms')->label(__('الغرف'))->numeric()->placeholder('-'),
                    TextEntry::make('area')->label(__('المساحة (م²)'))->numeric(2)->placeholder('-'),
                    TextEntry::make('is_furnished')->label(__('التأثيث'))->badge()->color('gray')->formatStateUsing(fn ($state) => $state ? __('مفروشة') : __('غير مفروشة')),
                    TextEntry::make('max_occupants')->label(__('أقصى عدد للسكان'))->numeric()->placeholder('-'),
                    TextEntry::make('details')->label(__('التفاصيل'))->placeholder('-')->columnSpanFull()->prose(),
                ]),
            ]),
            Grid::make(1)->columnSpan(['lg' => 1])->schema([
                Section::make(__('عقدي'))->icon(Heroicon::OutlinedDocumentText)->schema([
                    TextEntry::make('currentContract.number')->label(__('رقم العقد'))->weight('bold')->placeholder(__('لا يوجد عقد ساري')),
                    TextEntry::make('currentContract.status')->label(__('الحالة'))->badge()->placeholder('-'),
                    TextEntry::make('period')->label(__('المدة'))->placeholder('-')
                        ->state(fn (Unit $r) => $r->currentContract instanceof RentContract
                            ? $r->currentContract->start_date?->format('Y-m-d').' ← '.$r->currentContract->end_date?->format('Y-m-d') : null),
                    TextEntry::make('rent')->label(__('الإيجار السنوي'))->placeholder('-')->weight('bold')
                        ->state(fn (Unit $r) => $r->currentContract instanceof RentContract ? Parts::amount($r->currentContract->rent_amount) : null),
                ]),
                Section::make(__('الموقع'))->icon(Heroicon::OutlinedMapPin)->schema([
                    TextEntry::make('city.name')->label(__('المدينة'))->placeholder('-'),
                    TextEntry::make('district.name')->label(__('الحي'))->placeholder('-'),
                    TextEntry::make('address')->label(__('العنوان'))->placeholder('-'),
                    TextEntry::make('coordinates')->label(__('الاتجاهات'))->placeholder('-')->color('primary')->icon(Heroicon::OutlinedMap)
                        ->state(fn (Unit $r) => filled($r->latitude) && filled($r->longitude) ? __('افتح في الخرائط') : null)
                        ->url(fn (Unit $r) => filled($r->latitude) && filled($r->longitude) ? \App\Support\Maps::directionsUrl($r->latitude, $r->longitude) : null, true),
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
                    ->description(fn (Unit $r) => implode(' · ', array_filter([$r->building?->title, $r->city?->name, $r->number ? __('رقم :n', ['n' => $r->number]) : null]))),
                TextColumn::make('offer_type')->label(__('نوع العقد'))->badge(),
                TextColumn::make('currentContract.number')->label(__('العقد'))->placeholder('-'),
                TextColumn::make('contract_end')->label(__('ينتهي في'))->placeholder('-')
                    ->state(fn (Unit $r) => $r->currentContract instanceof RentContract ? $r->currentContract->end_date?->format('Y-m-d') : null)
                    ->color(fn (Unit $r) => $r->currentContract instanceof RentContract && $r->currentContract->end_date?->diffInDays(today(), false) > -60 ? 'warning' : null),
            ])
            ->recordActions([ViewAction::make()->label(__('التفاصيل'))->modalWidth('6xl')]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUnits::route('/')];
    }
}
