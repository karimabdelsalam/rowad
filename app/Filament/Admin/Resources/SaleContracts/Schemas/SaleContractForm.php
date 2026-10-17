<?php

namespace App\Filament\Admin\Resources\SaleContracts\Schemas;

use App\Enums\Calendar;
use App\Enums\CommissionPayer;
use App\Enums\OfferType;
use App\Enums\SalePaymentType;
use App\Enums\UnitStatus;
use App\Models\Client;
use App\Support\Settings;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Schemas\Schema;

/** Legacy sell form (paytype once / parts / installment). */
class SaleContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('الوحدة والأطراف'))->columns(2)->schema([
                Select::make('unit_id')
                    ->relationship('unit', 'title', fn ($query) => $query->where('offer_type', OfferType::Sale)->where('status', '!=', UnitStatus::Sold))
                    ->searchable()->preload()->required(),
                Select::make('employee_id')->relationship('employee', 'full_name')->searchable()->preload(),
                Repeater::make('parties')
                    ->label(__('المشترون'))
                    ->columnSpanFull()->columns(2)->minItems(1)
                    ->default([['client_id' => null, 'share_percent' => 100]])
                    ->schema([
                        \App\Filament\Support\QuickCreate::client(Select::make('client_id')->label(__('العميل'))
                            ->options(fn () => Client::query()->orderBy('full_name')->pluck('full_name', 'id'))
                            ->searchable()->required()->distinct()),
                        TextInput::make('share_percent')->label(__('النسبة %'))->numeric()->minValue(0.01)->maxValue(100)->default(100)->required(),
                    ]),
            ]),

            Section::make(__('القيمة وطريقة السداد'))->columns(3)->schema([
                Select::make('calendar')->options(Calendar::class)->default(fn () => Settings::get('system_calendar', 'gregorian'))->required(),
                DatePicker::make('contract_date')->default(today())->required(),
                TextInput::make('total_amount')->numeric()->minValue(1)->required(),
                Select::make('payment_type')->options(SalePaymentType::class)->default('once')->required()->live(),

                Fieldset::make(__('العمولة'))->statePath('payment_plan')->columnSpanFull()->columns(3)
                    ->visible(fn (Get $get) => self::type($get) !== 'installment')
                    ->schema([
                        Select::make('commission_payer')->label(__('يدفع العمولة'))->options(CommissionPayer::class)->default('buyer'),
                        TextInput::make('commission')->label(__('العمولة'))->numeric()->minValue(0)->default(0)
                            ->visible(fn (Get $get) => self::type($get, '../payment_type') === 'once'),
                        Select::make('commission_type')->label(__('نوع العمولة'))
                            ->options(['money' => __('مبلغ ثابت'), 'percent' => __('نسبة من القيمة')])->default('money')
                            ->visible(fn (Get $get) => self::type($get, '../payment_type') === 'once'),
                    ]),

                Repeater::make('payment_plan.parts')
                    ->label(__('الدفعات'))
                    ->columnSpanFull()->columns(3)
                    ->visible(fn (Get $get) => self::type($get) === 'parts')
                    ->addActionLabel(__('إضافة دفعة'))
                    ->schema([
                        TextInput::make('amount')->label(__('المبلغ'))->numeric()->minValue(0.01)->required(),
                        TextInput::make('commission')->label(__('العمولة'))->numeric()->default(0),
                        TextInput::make('cheque_number')->label(__('رقم الشيك')),
                        DatePicker::make('received_date')->label(__('تاريخ الاستلام'))->required(),
                        Toggle::make('deferred')->label(__('شيك مؤجل'))->live(),
                        DatePicker::make('due_date')->label(__('تاريخ الاستحقاق'))->visible(fn (Get $get) => (bool) $get('deferred'))->required(fn (Get $get) => (bool) $get('deferred')),
                    ]),

                Fieldset::make(__('خطة الأقساط'))->statePath('payment_plan')->columnSpanFull()->columns(4)
                    ->visible(fn (Get $get) => self::type($get) === 'installment')
                    ->schema([
                        TextInput::make('first_payment')->label(__('الدفعة الأولى'))->numeric()->minValue(0)->default(0),
                        TextInput::make('count')->label(__('عدد الأقساط'))->numeric()->integer()->minValue(1)->required(),
                        Select::make('cycle')->label(__('دورة الأقساط'))
                            ->options(['month' => __('شهري'), 'quarter' => __('ربع سنوي'), 'half_year' => __('نصف سنوي'), 'year' => __('سنوي')])
                            ->default('month')->required(),
                        DatePicker::make('start_date')->label(__('تاريخ البداية'))->required(),
                    ]),
            ]),

            Section::make(__('البنود والملاحظات'))->collapsible()->schema([
                CheckboxList::make('terms')->relationship('terms', 'title')->columns(2),
                RichEditor::make('extra_terms'),
                Textarea::make('notes')->rows(3),
                SpatieMediaLibraryFileUpload::make('attachments')->label(__('المرفقات'))->collection('attachments')->multiple()->downloadable(),
            ]),
        ]);
    }

    private static function type(Get $get, string $path = 'payment_type'): ?string
    {
        $type = $get($path);

        return $type instanceof SalePaymentType ? $type->value : $type;
    }
}
