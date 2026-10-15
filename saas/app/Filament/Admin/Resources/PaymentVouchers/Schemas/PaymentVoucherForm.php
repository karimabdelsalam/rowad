<?php

namespace App\Filament\Admin\Resources\PaymentVouchers\Schemas;

use App\Enums\LedgerDirection;
use App\Filament\Admin\Resources\Concerns\VoucherFormComponents;
use App\Models\OwnerTransaction;
use App\Services\Finance\VoucherService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PaymentVoucherForm
{
    use VoucherFormComponents;

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            self::partySection(),
            Section::make(__('توجيه المصروف'))->visibleOn('create')->columns(2)->schema([
                Select::make('charge_to')
                    ->label(__('تحميل المصروف على'))
                    ->options([
                        VoucherService::CHARGE_OWNERS => __('ملاك الوحدة (حسب نسب المصروفات)'),
                        VoucherService::CHARGE_CONTRACT => __('المستأجر/المشتري الحالي للوحدة'),
                    ])
                    ->placeholder(__('مصروف على المؤسسة'))
                    ->visible(fn (Get $get) => filled($get('unit_id'))),
                Toggle::make('with_vat')->label(__('إضافة ضريبة القيمة المضافة على المبلغ')),
                Select::make('owner_transaction_ids')
                    ->label(__('مستحقات المالك المدفوعة بهذا السند'))
                    ->multiple()
                    ->columnSpanFull()
                    ->visible(fn (Get $get) => $get('party_type') === 'owner' && filled($get('party_id')))
                    ->options(fn (Get $get) => OwnerTransaction::query()
                        ->where('owner_id', $get('party_id'))->whereNull('payment_voucher_id')
                        ->where('is_settled', false)->where('direction', LedgerDirection::Debit)
                        ->with(['type', 'unit'])->get()
                        ->mapWithKeys(fn ($t) => [$t->id => $t->due_date->toDateString().' — '.$t->type->title.' — '.($t->unit?->title ?? '').' — '.number_format((float) $t->amount, 2)]))
                    ->live()
                    ->afterStateUpdated(fn (?array $state, Set $set) => $set('amount', round((float) OwnerTransaction::whereKey($state ?? [])->sum('amount'), 2) ?: null)),
            ]),
            self::paymentSection([
                Select::make('expense_type_id')->relationship('expenseType', 'title'),
            ]),
        ]);
    }
}
