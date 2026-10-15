<?php

namespace App\Filament\Admin\Resources\OwnerTransactions\Schemas;

use App\Enums\LedgerDirection;
use App\Filament\Admin\Resources\Installments\InstallmentResource;
use App\Filament\Admin\Resources\Owners\OwnerResource;
use App\Filament\Admin\Resources\PaymentVouchers\PaymentVoucherResource;
use App\Filament\Admin\Resources\ReceiptVouchers\ReceiptVoucherResource;
use App\Filament\Admin\Resources\RentContracts\RentContractResource;
use App\Filament\Admin\Resources\SaleContracts\SaleContractResource;
use App\Filament\Admin\Resources\Units\UnitResource;
use App\Filament\Infolists\Parts;
use App\Models\OwnerTransaction;
use App\Models\RentContract;
use App\Support\Settings;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** One owner-ledger entry: for whom, from what, settled or not, and the vouchers that moved the money. */
class OwnerTransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'lg' => 3])->components([
            Parts::header(Heroicon::OutlinedScale, fn (OwnerTransaction $r) => [
                'kicker' => __('حركة حساب مالك').' · '.($r->type?->title ?? ''),
                'title' => $r->owner?->full_name ?? __('حساب المؤسسة'),
                'sub' => [$r->unit?->title, __('بتاريخ :date', ['date' => $r->due_date?->format('Y-m-d')])],
                'facts' => [
                    ['label' => $r->direction === LedgerDirection::Debit ? __('مستحق للمالك') : __('خصم على المالك'),
                        'value' => number_format((float) $r->amount, 2).' '.Settings::currencyLabel(), 'tone' => $r->direction === LedgerDirection::Debit ? 'success' : 'danger'],
                    ['label' => __('الحالة'), 'value' => $r->is_settled ? __('مُرحّلة') : __('معلّقة'), 'tone' => $r->is_settled ? 'success' : 'warning'],
                ],
            ]),
            Grid::make(1)->columnSpan(['lg' => 2])->schema([
                Section::make(__('الحركة'))->icon(Heroicon::OutlinedScale)->columns(['default' => 2, 'md' => 4])->schema([
                    Parts::link('owner.full_name', 'المالك', fn (OwnerTransaction $r) => $r->owner ? OwnerResource::getUrl('view', ['record' => $r->owner]) : null)
                        ->placeholder(__('حساب المؤسسة'))->columnSpan(2),
                    TextEntry::make('type.title')->label(__('النوع'))->badge()->color('gray'),
                    TextEntry::make('direction')->label(__('الاتجاه'))->badge(),
                    Parts::money('amount', 'المبلغ')->weight('bold'),
                    Parts::money('vat_amount', 'منها ضريبة'),
                    Parts::date('due_date', 'تاريخ الاستحقاق'),
                    TextEntry::make('source')->label(__('المصدر'))->badge()->color('gray')->placeholder('-')
                        ->formatStateUsing(fn ($state) => ['rent' => __('إيجار'), 'sell' => __('بيع'), 'build' => __('عقار')][$state] ?? $state),
                ]),
                Section::make(__('التسوية'))->icon(Heroicon::OutlinedCheckCircle)->columns(['default' => 2, 'md' => 4])->schema([
                    TextEntry::make('is_settled')->label(__('الحالة'))->badge()
                        ->formatStateUsing(fn ($state) => $state ? __('مُرحّلة') : __('معلّقة'))->color(fn ($state) => $state ? 'success' : 'warning'),
                    TextEntry::make('settled_at')->label(__('تاريخ التسوية'))->dateTime('Y-m-d H:i')->placeholder('-'),
                    Parts::link('receiptVoucher.number', 'سند القبض', fn (OwnerTransaction $r) => $r->receiptVoucher ? ReceiptVoucherResource::getUrl('view', ['record' => $r->receiptVoucher]) : null),
                    Parts::link('paymentVoucher.number', 'سند الصرف', fn (OwnerTransaction $r) => $r->paymentVoucher ? PaymentVoucherResource::getUrl('view', ['record' => $r->paymentVoucher]) : null),
                ]),
            ]),
            Grid::make(1)->columnSpan(['lg' => 1])->schema([
                Section::make(__('المصدر'))->icon(Heroicon::OutlinedLink)->schema([
                    Parts::link('unit.title', 'الوحدة', fn (OwnerTransaction $r) => $r->unit ? UnitResource::getUrl('view', ['record' => $r->unit]) : null),
                    Parts::link('contract.number', 'العقد', fn (OwnerTransaction $r) => $r->contract
                        ? ($r->contract instanceof RentContract ? RentContractResource::getUrl('view', ['record' => $r->contract]) : SaleContractResource::getUrl('view', ['record' => $r->contract]))
                        : null),
                    Parts::link('installment_label', 'القسط', fn (OwnerTransaction $r) => $r->installment ? InstallmentResource::getUrl('view', ['record' => $r->installment]) : null)
                        ->state(fn (OwnerTransaction $r) => $r->installment ? __('قسط :date', ['date' => $r->installment->due_date?->format('Y-m-d')]) : null),
                    Parts::link('parent_label', 'الحركة الأصلية', fn (OwnerTransaction $r) => $r->parent ? \App\Filament\Admin\Resources\OwnerTransactions\OwnerTransactionResource::getUrl('view', ['record' => $r->parent]) : null)
                        ->state(fn (OwnerTransaction $r) => $r->parent ? '#'.$r->parent->id : null),
                ]),
                Parts::record(false),
            ]),
        ]);
    }
}
