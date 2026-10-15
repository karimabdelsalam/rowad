<?php

namespace App\Filament\Admin\Resources\Installments\Schemas;

use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Filament\Admin\Resources\ReceiptVouchers\ReceiptVoucherResource;
use App\Filament\Admin\Resources\RentContracts\RentContractResource;
use App\Filament\Admin\Resources\SaleContracts\SaleContractResource;
use App\Filament\Admin\Resources\Units\UnitResource;
use App\Filament\Infolists\Parts;
use App\Models\Installment;
use App\Models\RentContract;
use App\Support\Settings;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** One installment: what is due, what was paid (and when, by which voucher), and the contract it belongs to. */
class InstallmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'lg' => 3])->components([
            Parts::header(Heroicon::OutlinedCalendarDays, function (Installment $r) {
                $late = ! $r->is_collected && $r->due_date?->isPast();
                $state = $r->is_collected ? __('محصّل') : ($late ? __('متأخر :days يومًا', ['days' => (int) $r->due_date->diffInDays(today())]) : __('مستحق'));

                return [
                    'kicker' => __('قسط').' · '.($r->type?->getLabel() ?? ''),
                    'title' => $r->unit?->title ?? '#'.$r->id,
                    'sub' => [__('يستحق في :date', ['date' => $r->due_date?->format('Y-m-d')]), $r->contract?->number, $r->contract?->clients?->pluck('full_name')->implode('، ')],
                    'facts' => [
                        ['label' => __('المبلغ'), 'value' => number_format((float) $r->amount, 2).' '.Settings::currencyLabel()],
                        ['label' => __('المتبقي'), 'value' => number_format($r->remainingAmount(), 2), 'tone' => $r->remainingAmount() > 0 ? ($late ? 'danger' : 'warning') : 'success'],
                        ['label' => __('الحالة'), 'value' => $state, 'tone' => $r->is_collected ? 'success' : ($late ? 'danger' : 'warning')],
                    ],
                ];
            }),
            Grid::make(1)->columnSpan(['lg' => 2])->schema([
                Section::make(__('الاستحقاق'))->icon(Heroicon::OutlinedBanknotes)->columns(['default' => 2, 'md' => 4])->schema([
                    Parts::money('amount', 'المبلغ')->weight('bold'),
                    TextEntry::make('paid_total')->label(__('المدفوع'))->numeric(2)->state(fn (Installment $r) => $r->paidTotal())->color('success'),
                    TextEntry::make('remaining')->label(__('المتبقي'))->numeric(2)->state(fn (Installment $r) => $r->remainingAmount())
                        ->color(fn ($state) => $state > 0 ? 'danger' : 'success')->weight('bold'),
                    TextEntry::make('type')->label(__('النوع'))->badge(),
                    Parts::date('due_date', 'تاريخ الاستحقاق')->weight('bold'),
                    TextEntry::make('due_hijri')->label(__('بالهجري'))->state(fn (Installment $r) => $r->due_date ? \App\Support\Hijri::toString($r->due_date) : null)->placeholder('-'),
                    Parts::money('vat_amount', 'الضريبة')->helperText(fn (Installment $r) => $r->vat_percent > 0 ? $r->vat_percent.'%' : null),
                    TextEntry::make('commissions')->label(__('العمولات'))->placeholder('-')
                        ->state(fn (Installment $r) => ($r->seller_commission > 0 || $r->buyer_commission > 0)
                            ? __('بائع :s · مشتري :b', ['s' => number_format((float) $r->seller_commission, 2), 'b' => number_format((float) $r->buyer_commission, 2)]) : null),
                    TextEntry::make('reason')->label(__('ملاحظة'))->placeholder('-')->columnSpanFull(),
                ]),
                Section::make(__('الدفعات المسجلة'))->icon(Heroicon::OutlinedReceiptPercent)
                    ->visible(fn (Installment $r) => $r->payments->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('payments')->hiddenLabel()->state(fn (Installment $r) => $r->payments()->with(['receiptVoucher', 'creator'])->get())->columns(['default' => 2, 'md' => 4])->schema([
                            Parts::date('paid_at', 'التاريخ'),
                            Parts::money('amount', 'المبلغ')->weight('bold'),
                            Parts::link('receiptVoucher.number', 'سند القبض', fn ($record) => $record->receiptVoucher ? ReceiptVoucherResource::getUrl('view', ['record' => $record->receiptVoucher]) : null),
                            TextEntry::make('creator.name')->label(__('سجّلها'))->placeholder('-'),
                        ]),
                    ]),
            ]),
            Grid::make(1)->columnSpan(['lg' => 1])->schema([
                Section::make(__('العقد والوحدة'))->icon(Heroicon::OutlinedDocumentText)->schema([
                    Parts::link('contract.number', 'العقد', fn (Installment $r) => $r->contract
                        ? ($r->contract instanceof RentContract ? RentContractResource::getUrl('view', ['record' => $r->contract]) : SaleContractResource::getUrl('view', ['record' => $r->contract]))
                        : null)->helperText(fn (Installment $r) => $r->contract instanceof RentContract ? __('عقد إيجار') : __('عقد بيع')),
                    Parts::link('unit.title', 'الوحدة', fn (Installment $r) => $r->unit ? UnitResource::getUrl('view', ['record' => $r->unit]) : null)
                        ->helperText(fn (Installment $r) => $r->unit?->building?->title),
                    TextEntry::make('tenants')->label(__('الأطراف'))->badge()->color('gray')->placeholder('-')
                        ->state(fn (Installment $r) => $r->contract?->clients?->pluck('full_name')->all()),
                ]),
                Section::make(__('التحصيل'))->icon(Heroicon::OutlinedCheckCircle)->columns(2)->schema([
                    TextEntry::make('is_collected')->label(__('الحالة'))->badge()
                        ->formatStateUsing(fn ($state) => $state ? __('محصّل') : __('غير محصّل'))->color(fn ($state) => $state ? 'success' : 'warning'),
                    TextEntry::make('collected_at')->label(__('تاريخ التحصيل'))->dateTime('Y-m-d H:i')->placeholder('-'),
                    Parts::link('receiptVoucher.number', 'سند القبض', fn (Installment $r) => $r->receiptVoucher ? ReceiptVoucherResource::getUrl('view', ['record' => $r->receiptVoucher]) : null),
                    Parts::link('invoice.number', 'الفاتورة', fn (Installment $r) => $r->invoice ? InvoiceResource::getUrl('view', ['record' => $r->invoice]) : null),
                    TextEntry::make('reminder_stage')->label(__('التذكيرات المرسلة'))->numeric()->placeholder('0')
                        ->helperText(fn (Installment $r) => $r->last_reminded_at ? __('آخرها :d', ['d' => $r->last_reminded_at->format('Y-m-d')]) : null),
                ]),
                Parts::record(false),
            ]),
        ]);
    }
}
