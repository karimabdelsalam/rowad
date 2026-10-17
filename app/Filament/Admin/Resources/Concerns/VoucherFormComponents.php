<?php

namespace App\Filament\Admin\Resources\Concerns;

use App\Enums\Calendar;
use App\Enums\PaymentMethod;
use App\Models\Citizen;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Lookups\GovernmentOrganization;
use App\Models\Lookups\TradeOrganization;
use App\Models\Owner;
use App\Support\Settings;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

/** Shared receipt/payment voucher fields (legacy statementsin/statementsout forms). */
trait VoucherFormComponents
{
    /** Legacy from_type values mapped to morph aliases. */
    public static function partyTypes(): array
    {
        return [
            'owner' => __('مالك'),
            'client' => __('عميل'),
            'citizen' => __('مواطن / فرد'),
            'employee' => __('موظف'),
            'government_organization' => __('جهة حكومية'),
            'trade_organization' => __('جهة تجارية'),
        ];
    }

    public static function partyOptions(?string $type): array
    {
        return match ($type) {
            'owner' => Owner::orderBy('full_name')->pluck('full_name', 'id')->all(),
            'client' => Client::orderBy('full_name')->pluck('full_name', 'id')->all(),
            'citizen' => Citizen::orderBy('name')->pluck('name', 'id')->all(),
            'employee' => Employee::orderBy('full_name')->pluck('full_name', 'id')->all(),
            'government_organization' => GovernmentOrganization::orderBy('title')->pluck('title', 'id')->all(),
            'trade_organization' => TradeOrganization::orderBy('title')->pluck('title', 'id')->all(),
            default => [],
        };
    }

    protected static function partySection(): Section
    {
        return Section::make(__('الطرف'))->columns(3)->schema([
            Select::make('party_type')->label(__('نوع الطرف'))->options(self::partyTypes())->live()->placeholder(__('أخرى (اكتب الاسم)')),
            \App\Filament\Support\QuickCreate::party(Select::make('party_id')
                ->label(__('الطرف'))
                ->options(fn (Get $get) => self::partyOptions($get('party_type')))
                ->searchable())
                ->live()
                ->visible(fn (Get $get) => filled($get('party_type')))
                ->required(fn (Get $get) => filled($get('party_type'))),
            TextInput::make('party_name')->visible(fn (Get $get) => blank($get('party_type')))->maxLength(150),
            Select::make('unit_id')->relationship('unit', 'title')->searchable()->preload()->live(),
        ]);
    }

    protected static function paymentSection(array $extra = []): Section
    {
        return Section::make(__('بيانات السند'))->columns(3)->schema([...$extra,
            Select::make('calendar')->options(Calendar::class)->default(fn () => Settings::get('system_calendar', 'gregorian'))->required(),
            DatePicker::make('issued_at')->default(today())->required(),
            TextInput::make('amount')->numeric()->minValue(0.01)->required(),
            Select::make('payment_method')->options(PaymentMethod::class)->default('cash')->required()->live(),
            TextInput::make('cheque_number')->visible(fn (Get $get) => self::method($get) === 'cheque'),
            DatePicker::make('cheque_date')->visible(fn (Get $get) => self::method($get) === 'cheque'),
            TextInput::make('bank_name')->visible(fn (Get $get) => in_array(self::method($get), ['cheque', 'transfer'], true)),
            Select::make('bank_account_id')->relationship('bankAccount', 'holder_name')
                ->helperText(__('يتم تحديث رصيد الحساب تلقائيًا')),
            TextInput::make('reason')->columnSpan(2)->maxLength(255),
            Textarea::make('notes')->columnSpanFull()->rows(2),
            SpatieMediaLibraryFileUpload::make('attachments')->collection('attachments')->multiple()->columnSpanFull(),
        ]);
    }

    private static function method(Get $get): ?string
    {
        $method = $get('payment_method');

        return $method instanceof PaymentMethod ? $method->value : $method;
    }
}
