<?php

namespace App\Filament\Admin\Resources\Units\Schemas;

use App\Enums\Calendar;
use App\Enums\CustomFieldType;
use App\Enums\OfferType;
use App\Enums\PaymentCycle;
use App\Enums\RentPeriodUnit;
use App\Enums\UnitStatus;
use App\Models\CustomField;
use App\Models\District;
use App\Models\Owner;
use App\Models\Lookups\BorderType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/** Legacy building add/edit form. */
class UnitForm
{
    private const SIDE_LABELS = ['north' => 'الحد الشمالي', 'east' => 'الحد الشرقي', 'south' => 'الحد الجنوبي', 'west' => 'الحد الغربي'];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->columnSpanFull()->persistTabInQueryString()->tabs([
                Tab::make(__('البيانات الأساسية'))->columns(3)->schema([
                    TextInput::make('title')->required()->maxLength(255),
                    Select::make('building_id')->relationship('building', 'title')->searchable()->preload(),
                    Select::make('property_category_id')->relationship('category', 'title')->searchable()->preload()->live(),
                    Select::make('offer_type')->options(OfferType::class)->default('rent')->required()->live(),
                    Select::make('status')->options(UnitStatus::class)->default('available')->required(),
                    Select::make('client_category_id')->relationship('clientCategory', 'title'),
                    TextInput::make('number'),
                    TextInput::make('floor'),
                    TextInput::make('rooms')->numeric()->integer()->minValue(0),
                    TextInput::make('area')->numeric()->minValue(0),
                    TextInput::make('max_occupants')->numeric()->integer()->minValue(0),
                    Select::make('calendar')->options(Calendar::class)->default('gregorian')->required(),
                    Toggle::make('is_furnished'),
                    Toggle::make('is_active')->default(true),
                    Textarea::make('details')->columnSpanFull()->rows(3),
                ]),

                Tab::make(__('الملاك'))->schema([
                    Repeater::make('owner_shares')
                        ->hiddenLabel()
                        ->columns(3)
                        ->minItems(1)
                        ->default([['owner_id' => null, 'share_percent' => 100, 'expenses_percent' => 100]])
                        ->addActionLabel(__('إضافة مالك'))
                        ->schema([
                            Select::make('owner_id')
                                ->label(__('المالك'))
                                ->options(fn () => Owner::query()->orderBy('full_name')->pluck('full_name', 'id'))
                                ->searchable()
                                ->required()
                                ->distinct(),
                            TextInput::make('share_percent')->label(__('نسبة الملكية'))->numeric()->suffix('%')->minValue(0)->maxValue(100)->required(),
                            TextInput::make('expenses_percent')->label(__('نسبة تحمل المصروفات'))->numeric()->suffix('%')->minValue(0)->maxValue(100)->default(100)->required(),
                        ]),
                ]),

                Tab::make(__('الموقع'))->columns(3)->schema([
                    Select::make('city_id')->relationship('city', 'name')->searchable()->preload()->live(),
                    Select::make('district_id')
                        ->label(__('الحي'))
                        ->options(fn (Get $get) => District::query()
                            ->when($get('city_id'), fn ($q, $city) => $q->where('city_id', $city))
                            ->pluck('name', 'id'))
                        ->searchable(),
                    TextInput::make('street'),
                    TextInput::make('zone'),
                    TextInput::make('address')->columnSpan(2),
                    Textarea::make('address_details')->columnSpanFull(),
                    TextInput::make('latitude')->numeric(),
                    TextInput::make('longitude')->numeric(),
                ]),

                Tab::make(__('الصك والحدود'))->columns(3)->schema([
                    TextInput::make('plan_number'),
                    TextInput::make('plot_number'),
                    // legacy building.deed: several title deeds per unit
                    Repeater::make('deeds')
                        ->label(__('الصكوك'))
                        ->relationship()
                        ->columnSpanFull()
                        ->columns(4)
                        ->defaultItems(0)
                        ->addActionLabel(__('إضافة صك'))
                        ->schema([
                            Select::make('deed_type_id')->label(__('نوع الصك'))->relationship('deedType', 'title'),
                            TextInput::make('number')->label(__('رقم الصك'))->required()->maxLength(100),
                            TextInput::make('source')->label(__('مصدر الصك')),
                            TextInput::make('issue_date')->label(__('تاريخ الصك'))->maxLength(20)->placeholder('1445-06-12'),
                        ]),
                    // legacy building.plots: land plots with their four boundaries
                    Repeater::make('landPlots')
                        ->label(__('القطع والحدود'))
                        ->relationship()
                        ->columnSpanFull()
                        ->defaultItems(0)
                        ->addActionLabel(__('إضافة قطعة'))
                        ->schema([
                            TextInput::make('number')->label(__('رقم القطعة'))->maxLength(50),
                            TextInput::make('area')->label(__('المساحة (م²)'))->numeric()->minValue(0),
                            ...collect(\App\Models\UnitPlot::SIDES)->map(fn (string $side) => \Filament\Schemas\Components\Fieldset::make(__(self::SIDE_LABELS[$side]))
                                ->columns(4)
                                ->schema([
                                    Select::make("sides.{$side}.type")->label(__('نوع الحد'))
                                        ->options(fn () => BorderType::query()->whereNotNull('code')->pluck('title', 'code')),
                                    TextInput::make("sides.{$side}.length")->label(__('الطول (م)'))->numeric(),
                                    TextInput::make("sides.{$side}.width")->label(__('العرض (م)'))->numeric(),
                                    TextInput::make("sides.{$side}.plot_number")->label(__('رقم القطعة المجاورة')),
                                ]))->all(),
                        ]),
                    Repeater::make('meters')
                        ->relationship()
                        ->columnSpanFull()
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel(__('إضافة عداد'))
                        ->schema([
                            Select::make('meter_type_id')->relationship('meterType', 'title')->required(),
                            TextInput::make('number')->label(__('رقم العداد'))->required(),
                            Select::make('ownership')->label(__('نوع العداد'))->options(['private' => __('خاص'), 'shared' => __('مشترك')]),
                            TextInput::make('payment_code')->label(__('رمز السداد'))->maxLength(100),
                            TextInput::make('payment_account')->label(__('رقم حساب السداد'))->maxLength(100),
                        ]),
                ]),

                Tab::make(__('الأسعار والعمولات'))->columns(3)->schema([
                    TextInput::make('rent_amount')->numeric()->minValue(0),
                    TextInput::make('monthly_rent')->numeric()->minValue(0),
                    TextInput::make('daily_rent')->numeric()->minValue(0),
                    Select::make('rent_period')->options(RentPeriodUnit::class),
                    Select::make('rent_payment_cycle')->options(PaymentCycle::class),
                    Toggle::make('vat_applicable'),
                    TextInput::make('rent_commission')->numeric()->minValue(0),
                    TextInput::make('sale_commission')->numeric()->minValue(0),
                ]),

                Tab::make(__('رسوم الإدارة'))->columns(3)->schema([
                    TextInput::make('management_fee')->numeric()->minValue(0),
                    Select::make('management_fee_cycle')->options(PaymentCycle::class),
                    DatePicker::make('management_fee_start_date'),
                ]),

                Tab::make(__('حقول إضافية'))
                    ->visible(fn (Get $get) => self::customFields($get('property_category_id'))->isNotEmpty())
                    ->columns(2)
                    ->schema(fn (Get $get) => self::customFields($get('property_category_id'))->map(function (CustomField $field) {
                        $name = "custom_fields.{$field->id}";
                        $component = match ($field->type) {
                            CustomFieldType::Checkbox => Toggle::make($name),
                            CustomFieldType::Number => TextInput::make($name)->numeric()->suffix($field->suffix),
                            CustomFieldType::Date => DatePicker::make($name),
                            CustomFieldType::Select => Select::make($name)->options(array_combine($field->options ?? [], $field->options ?? [])),
                            default => TextInput::make($name)->suffix($field->suffix),
                        };

                        return $component->label($field->title)->helperText($field->description)->required($field->is_required);
                    })->all()),

                Tab::make(__('النشر على الموقع'))->columns(3)->schema([
                    Toggle::make('is_published')->label(__('عرض الوحدة على موقع المؤسسة'))->helperText(__('تظهر فقط إذا كانت متاحة للإيجار')),
                    Toggle::make('show_price')->label(__('إظهار السعر'))->default(true),
                    Toggle::make('is_featured')->label(__('وحدة مميزة')),
                    TextInput::make('title_en')->label(__('العنوان بالإنجليزية'))->columnSpan(3),
                    Textarea::make('details_en')->label(__('الوصف بالإنجليزية'))->rows(4)->columnSpanFull(),
                ]),

                Tab::make(__('المرفقات'))->schema([
                    SpatieMediaLibraryFileUpload::make('attachments')
                        ->hiddenLabel()
                        ->collection('attachments')
                        ->multiple()
                        ->downloadable()
                        ->maxSize(20 * 1024),
                    SpatieMediaLibraryFileUpload::make('photos')
                        ->label(__('الصور'))
                        ->collection('photos')
                        ->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']) // no SVG: it can carry scripts
                        ->maxSize(10 * 1024)
                        ->multiple()
                        ->reorderable(),
                ]),
            ]),
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, CustomField> */
    private static function customFields(mixed $categoryId)
    {
        return CustomField::query()
            ->whereHas('group', fn ($q) => $q->whereNull('property_category_id')->when($categoryId, fn ($q) => $q->orWhere('property_category_id', $categoryId)))
            ->orderBy('sort_order')
            ->get();
    }
}
