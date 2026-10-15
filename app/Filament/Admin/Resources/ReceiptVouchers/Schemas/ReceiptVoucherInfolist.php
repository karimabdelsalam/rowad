<?php

namespace App\Filament\Admin\Resources\ReceiptVouchers\Schemas;

use App\Filament\Admin\Resources\Units\UnitResource;
use App\Filament\Admin\Resources\Concerns\VoucherFormComponents;
use App\Filament\Infolists\Parts;
use App\Models\ReceiptVoucher;
use App\Support\Settings;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Receipt voucher: who paid, how much, how, and what it was for. */
class ReceiptVoucherInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'lg' => 3])->components([
            Parts::header(Heroicon::OutlinedArrowDownTray, fn (ReceiptVoucher $r) => [
                'kicker' => __('سند قبض'),
                'title' => $r->number ?? '#'.$r->id,
                'sub' => [__('بتاريخ :date', ['date' => $r->issued_at?->format('Y-m-d')]), self::partyName($r), $r->unit?->title],
                'facts' => [
                    ['label' => __('المبلغ'), 'value' => number_format((float) $r->amount, 2).' '.Settings::currencyLabel(), 'tone' => 'success'],
                    ['label' => __('طريقة الدفع'), 'value' => $r->payment_method?->getLabel() ?? '-'],
                ],
            ]),
            Grid::make(1)->columnSpan(['lg' => 2])->schema([
                Section::make(__('الطرف والسبب'))->icon(Heroicon::OutlinedUser)->columns(['default' => 2, 'md' => 4])->schema([
                    TextEntry::make('party_display')->label(__('المستلم منه'))->weight('bold')->columnSpan(2)
                        ->state(fn (ReceiptVoucher $r) => self::partyName($r)),
                    TextEntry::make('party_type')->label(__('نوع الطرف'))->badge()->color('gray')->placeholder('-')
                        ->formatStateUsing(fn ($state) => VoucherFormComponents::partyTypes()[$state] ?? $state),
                    Parts::link('unit.title', 'الوحدة', fn (ReceiptVoucher $r) => $r->unit ? UnitResource::getUrl('view', ['record' => $r->unit]) : null),
                    TextEntry::make('transactionType.title')->label(__('نوع الحركة'))->placeholder('-')->columnSpan(2),
                    TextEntry::make('reason')->label(__('السبب'))->placeholder('-')->columnSpan(2),
                    TextEntry::make('notes')->label(__('ملاحظات'))->placeholder('-')->columnSpanFull(),
                ]),
                Section::make(__('الدفع'))->icon(Heroicon::OutlinedBanknotes)->columns(['default' => 2, 'md' => 4])->schema([
                    Parts::money('amount', 'المبلغ')->weight('bold'),
                    Parts::money('vat_amount', 'منها ضريبة'),
                    TextEntry::make('payment_method')->label(__('طريقة الدفع'))->badge(),
                    TextEntry::make('bankAccount.holder_name')->label(__('الحساب البنكي'))->placeholder('-')
                        ->helperText(fn (ReceiptVoucher $r) => $r->bankAccount?->bank?->title ?? $r->bankAccount?->iban),
                    TextEntry::make('bank_name')->label(__('بنك الشيك'))->placeholder('-')
                        ->visible(fn (ReceiptVoucher $r) => $r->payment_method?->value === 'cheque'),
                    TextEntry::make('cheque_number')->label(__('رقم الشيك'))->placeholder('-')
                        ->visible(fn (ReceiptVoucher $r) => $r->payment_method?->value === 'cheque'),
                    Parts::date('cheque_date', 'تاريخ الشيك')
                        ->visible(fn (ReceiptVoucher $r) => $r->payment_method?->value === 'cheque'),
                ]),
            ]),
            Grid::make(1)->columnSpan(['lg' => 1])->schema([
                Section::make(__('المستند'))->icon(Heroicon::OutlinedDocumentText)->columns(2)->schema([
                    TextEntry::make('number')->label(__('الرقم'))->weight('bold')->placeholder('-'),
                    Parts::date('issued_at', 'تاريخ الإصدار'),
                    TextEntry::make('calendar')->label(__('التقويم'))->badge()->color('gray'),
                    TextEntry::make('hijri')->label(__('بالهجري'))
                        ->state(fn (ReceiptVoucher $r) => $r->issued_at ? \App\Support\Hijri::toString($r->issued_at) : null)->placeholder('-'),
                ]),
                Parts::record(),
            ]),
        ]);
    }

    public static function partyName(ReceiptVoucher $r): ?string
    {
        return $r->party_name ?: ($r->party?->full_name ?? $r->party?->name ?? $r->party?->title);
    }
}
