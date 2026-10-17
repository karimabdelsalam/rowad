<?php

namespace App\Filament\Admin\Resources\ReceiptVouchers\Schemas;

use App\Filament\Admin\Resources\Concerns\VoucherFormComponents;
use App\Models\Installment;
use App\Models\OwnerTransaction;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ReceiptVoucherForm
{
    use VoucherFormComponents;

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            self::partySection(),
            Section::make(__('ربط المستحقات'))->visibleOn('create')->columns(2)->schema([
                Select::make('installment_ids')
                    ->label(__('الأقساط المحصلة بهذا السند'))
                    ->multiple()
                    ->options(fn (Get $get) => self::openInstallments($get('unit_id'), $get('party_type') === 'client' ? $get('party_id') : null))
                    ->live()
                    ->afterStateUpdated(fn (?array $state, Set $set) => $set('amount',
                        round(Installment::whereKey($state ?? [])->get()->sum(fn ($i) => $i->remainingAmount()), 2) ?: null)),
                Select::make('owner_transaction_ids')
                    ->label(__('حركات إيراد للمالك'))
                    ->multiple()
                    ->visible(fn (Get $get) => $get('party_type') === 'owner' && filled($get('party_id')))
                    ->options(fn (Get $get) => OwnerTransaction::query()
                        ->where('owner_id', $get('party_id'))->whereNull('receipt_voucher_id')
                        ->whereHas('type', fn ($q) => $q->where('is_income', true))
                        ->with('type')->get()
                        ->mapWithKeys(fn ($t) => [$t->id => "#{$t->id} {$t->type->title} — ".number_format((float) $t->amount, 2)])),
            ]),
            self::paymentSection([
                Select::make('transaction_type_id')->relationship('transactionType', 'title'),
                Select::make('account_id')->label(__('حساب الإيراد'))
                    ->options(fn () => \App\Filament\Admin\Resources\Accounts\AccountResource::options([\App\Enums\AccountType::Revenue, \App\Enums\AccountType::Liability, \App\Enums\AccountType::Equity]))
                    ->searchable()->placeholder(__('تلقائي'))
                    ->helperText(__('لسند لا يخص الإيجارات أو الملاك؛ يُترك تلقائيًا ليُقيد في الإيرادات الأخرى'))
                    ->visible(fn () => \App\Filament\Admin\Resources\Accounts\AccountResource::canViewAny()),
            ]),
        ]);
    }

    /** Uncollected receivables not yet linked to a receipt, for a unit and/or client. */
    private static function openInstallments(mixed $unitId, mixed $clientId): array
    {
        if (blank($unitId) && blank($clientId)) {
            return [];
        }

        return Installment::query()
            ->whereNull('receipt_voucher_id')
            ->where('is_collected', false)
            ->when($unitId, fn ($q) => $q->where('unit_id', $unitId))
            ->when($clientId, fn ($q) => $q->whereHasMorph('contract', ['rent_contract', 'sale_contract'],
                fn ($q) => $q->whereHas('clients', fn ($q) => $q->whereKey($clientId))))
            ->orderBy('due_date')
            ->with('unit')
            ->get()
            ->mapWithKeys(fn (Installment $i) => [$i->id => $i->due_date->toDateString().' — '.$i->unit->title.' — '.number_format($i->remainingAmount(), 2)])
            ->all();
    }
}
