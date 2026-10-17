<?php

namespace App\Filament\Admin\Resources\RentContracts\Schemas;

use App\Enums\Calendar;
use App\Enums\OfferType;
use App\Enums\PaymentCycle;
use App\Enums\RentPeriodUnit;
use App\Enums\UnitUsage;
use App\Models\Client;
use App\Models\Unit;
use App\Support\Settings;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Schemas\Schema;

class RentContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('الوحدة والأطراف'))->columns(2)->schema([
                Select::make('unit_id')
                    ->relationship('unit', 'title', fn ($query) => $query->where('offer_type', OfferType::Rent)->where('is_active', true))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set) {
                        $unit = $state ? Unit::find($state) : null;
                        if ($unit === null) {
                            return;
                        }
                        $set('rent_amount', $unit->rent_amount);
                        $set('payment_cycle', $unit->rent_payment_cycle);
                        $set('commission', $unit->rent_commission);
                        $set('vat_applicable', $unit->vat_applicable);
                    }),
                Select::make('employee_id')
                    ->relationship('employee', 'full_name')
                    ->searchable()
                    ->preload(),
                Repeater::make('parties')
                    ->label(__('المستأجرون'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->minItems(1)
                    ->defaultItems(1)
                    ->default([['client_id' => null, 'share_percent' => 100]])
                    ->schema([
                        \App\Filament\Support\QuickCreate::client(Select::make('client_id')
                            ->label(__('العميل'))
                            ->options(fn () => Client::query()->orderBy('full_name')->pluck('full_name', 'id'))
                            ->searchable()
                            ->required()
                            ->distinct()),
                        TextInput::make('share_percent')
                            ->label(__('النسبة %'))
                            ->numeric()
                            ->minValue(0.01)
                            ->maxValue(100)
                            ->default(100)
                            ->required(),
                    ]),
            ]),

            Section::make(__('المدة والدفع'))->columns(3)->schema([
                Select::make('calendar')->options(Calendar::class)->default(fn () => Settings::get('system_calendar', 'gregorian'))->required(),
                DatePicker::make('contract_date')->default(today())->required(),
                Select::make('usage')->options(UnitUsage::class),
                DatePicker::make('start_date')->required()->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::fillEndDate($get, $set)),
                Select::make('period_unit')->options(RentPeriodUnit::class)->default('year')->required()->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::fillEndDate($get, $set)),
                TextInput::make('period_count')->numeric()->integer()->minValue(1)->default(1)->required()->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::fillEndDate($get, $set)),
                DatePicker::make('end_date')->required()->afterOrEqual('start_date'),
                Select::make('payment_cycle')
                    ->options(collect(PaymentCycle::cases())->reject(fn ($c) => $c === PaymentCycle::Once)->mapWithKeys(fn ($c) => [$c->value => $c->getLabel()]))
                    ->default('month')
                    ->required(),
            ]),

            Section::make(__('المبالغ'))->columns(3)->schema([
                TextInput::make('rent_amount')->label(__('قيمة الدفعة (شاملة الضريبة)'))->numeric()->required()->minValue(0)->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::fillVat($get, $set)),
                TextInput::make('yearly_rent')->numeric(),
                Grid::make(3)->columnSpanFull()->schema([
                    Toggle::make('vat_applicable')->live()->afterStateUpdated(fn (Get $get, Set $set) => self::fillVat($get, $set)),
                    TextInput::make('vat_percent')->numeric()->default(fn () => Settings::get('vat_percent', 15))->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::fillVat($get, $set)),
                    TextInput::make('vat_amount')->numeric()->default(0)->helperText(__('تحسب تلقائيًا من قيمة الدفعة')),
                ]),
                TextInput::make('commission')->numeric()->default(0),
                Select::make('commission_cycle')->options(PaymentCycle::class)->placeholder(__('بدون عمولة')),
            ]),

            // legacy rent contract meters: reading of each unit meter at handover, printed on the contract
            Section::make(__('قراءات العدادات عند التسليم'))->collapsible()->collapsed()->schema([
                \Filament\Forms\Components\KeyValue::make('meter_readings')->hiddenLabel()
                    ->keyLabel(__('رقم العداد'))->valueLabel(__('القراءة'))
                    ->addActionLabel(__('إضافة قراءة'))
                    ->helperText(__('تُطبع في العقد بجانب عدادات الوحدة.')),
            ]),

            Section::make(__('البنود والملاحظات'))->collapsible()->schema([
                CheckboxList::make('terms')->relationship('terms', 'title')->columns(2),
                RichEditor::make('extra_terms'),
                Textarea::make('notes')->rows(3),
                SpatieMediaLibraryFileUpload::make('attachments')->label(__('المرفقات'))->collection('attachments')->multiple()->downloadable(),
            ]),
        ]);
    }

    /** End date = start + period - 1 day (legacy form JS). */
    private static function fillEndDate(Get $get, Set $set): void
    {
        if (! $get('start_date') || ! $get('period_count') || ! $get('period_unit')) {
            return;
        }
        $start = \Carbon\CarbonImmutable::parse($get('start_date'));
        $count = (int) $get('period_count');
        $unit = $get('period_unit') instanceof RentPeriodUnit ? $get('period_unit')->value : $get('period_unit');
        $end = match ($unit) {
            'day' => $start->addDays($count - 1),
            'month' => $start->addMonthsNoOverflow($count)->subDay(),
            default => $start->addYearsNoOverflow($count)->subDay(),
        };
        $set('end_date', $end->toDateString());
    }

    /** VAT included in the payment amount: amount - amount / (1 + rate). */
    private static function fillVat(Get $get, Set $set): void
    {
        if (! $get('vat_applicable')) {
            $set('vat_amount', 0);

            return;
        }
        $amount = (float) $get('rent_amount');
        $rate = (float) $get('vat_percent') / 100;
        $set('vat_amount', round($amount - $amount / (1 + $rate), 2));
    }
}
