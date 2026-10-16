<?php

namespace App\Filament\Admin\Resources\Invoices\Schemas;

use App\Filament\Admin\Resources\Clients\ClientResource;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Filament\Infolists\Parts;
use App\Models\Invoice;
use App\Services\Zatca\Phase2\ZatcaInvoicing;
use App\Support\Settings;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Tax invoice: customer, lines, totals and the ZATCA details. */
class InvoiceInfolist
{
    public const STATUS_COLORS = ['pending' => 'info', 'reported' => 'success', 'cleared' => 'success', 'rejected' => 'danger', 'failed' => 'warning'];

    public const STATUS_ICONS = [
        'pending' => Heroicon::OutlinedClock, 'reported' => Heroicon::OutlinedCheckBadge, 'cleared' => Heroicon::OutlinedShieldCheck,
        'rejected' => Heroicon::OutlinedXCircle, 'failed' => Heroicon::OutlinedArrowPath,
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'lg' => 3])->components([
            Parts::header(Heroicon::OutlinedReceiptPercent, fn (Invoice $r) => [
                'kicker' => match ($r->kind) {
                    'credit' => __('إشعار دائن'),
                    'debit' => __('إشعار مدين'),
                    default => $r->type === 'standard' ? __('فاتورة ضريبية') : __('فاتورة ضريبية مبسطة'),
                },
                'title' => $r->number,
                'sub' => [__('بتاريخ :date', ['date' => $r->issue_date?->format('Y-m-d')]), $r->customer_name ?? $r->client?->full_name],
                'facts' => [
                    ['label' => __('الإجمالي'), 'value' => number_format((float) $r->total, 2).' '.Settings::currencyLabel()],
                    ['label' => __('المدفوع'), 'value' => number_format((float) $r->paid_amount, 2), 'tone' => (float) $r->paid_amount >= (float) $r->total ? 'success' : 'warning'],
                ],
            ]),
            Grid::make(1)->columnSpan(['lg' => 2])->schema([
                Section::make(__('البنود'))->icon(Heroicon::OutlinedListBullet)->schema([
                    RepeatableEntry::make('items')->hiddenLabel()->columns(['default' => 2, 'md' => 5])->schema([
                        TextEntry::make('description')->label(__('البيان'))->columnSpan(2),
                        TextEntry::make('quantity')->label(__('الكمية'))->numeric(),
                        TextEntry::make('unit_price')->label(__('السعر'))->numeric(2),
                        TextEntry::make('vat_amount')->label(__('الضريبة'))->numeric(2)
                            ->helperText(fn ($record) => isset($record['vat_percent']) ? $record['vat_percent'].'%' : null),
                    ]),
                ]),
                Section::make(__('الإجماليات'))->icon(Heroicon::OutlinedCalculator)->columns(['default' => 2, 'md' => 4])->schema([
                    Parts::money('subtotal', 'قبل الضريبة'),
                    Parts::money('vat_total', 'الضريبة'),
                    Parts::money('total', 'الإجمالي')->weight('bold'),
                    TextEntry::make('remaining')->label(__('المتبقي'))->numeric(2)->suffix(' '.Settings::currencyLabel())
                        ->state(fn (Invoice $r) => max(0, (float) $r->total - (float) $r->paid_amount))
                        ->color(fn ($state) => $state > 0 ? 'danger' : 'success')->weight('bold'),
                    TextEntry::make('notes')->label(__('ملاحظات'))->placeholder('-')->columnSpanFull(),
                ]),
            ]),
            Grid::make(1)->columnSpan(['lg' => 1])->schema([
                Section::make(__('العميل'))->icon(Heroicon::OutlinedUser)->schema([
                    Parts::link('client.full_name', 'العميل', fn (Invoice $r) => $r->client ? ClientResource::getUrl('view', ['record' => $r->client]) : null),
                    TextEntry::make('customer_name')->label(__('الاسم على الفاتورة'))->placeholder('-'),
                    TextEntry::make('customer_vat_number')->label(__('الرقم الضريبي للعميل'))->placeholder('-')->copyable(),
                ]),
                Section::make(__('زاتكا'))->icon(Heroicon::OutlinedQrCode)->columns(2)->schema([
                    TextEntry::make('zatca_status')->label(__('الحالة'))->badge()->columnSpanFull()
                        ->formatStateUsing(fn ($state) => __(ZatcaInvoicing::STATUSES[$state] ?? $state))
                        ->color(fn ($state) => self::STATUS_COLORS[$state] ?? 'gray')
                        ->icon(fn ($state) => self::STATUS_ICONS[$state] ?? null)
                        ->placeholder(__('المرحلة الأولى (رمز QR)')),
                    TextEntry::make('zatca_icv')->label(__('عداد الفاتورة (ICV)'))->placeholder('-')->visible(fn (Invoice $r) => $r->zatca_icv !== null),
                    TextEntry::make('zatca_submitted_at')->label(__('آخر إرسال'))->dateTime('Y-m-d H:i')->placeholder('-')
                        ->visible(fn (Invoice $r) => $r->zatca_icv !== null),
                    TextEntry::make('zatca_uuid')->label('UUID')->placeholder('-')->copyable()->fontFamily('mono')->size('xs')->columnSpanFull(),
                    TextEntry::make('zatca_hash')->label(__('بصمة الفاتورة'))->placeholder('-')->copyable()->fontFamily('mono')->size('xs')->columnSpanFull()
                        ->visible(fn (Invoice $r) => $r->zatca_icv !== null),
                    TextEntry::make('zatca_qr')->label(__('رمز QR'))->placeholder('-')->limit(40)->copyable()->fontFamily('mono')->size('xs')->columnSpanFull(),
                    TextEntry::make('zatca_messages')->label(__('رسائل زاتكا'))->listWithLineBreaks()->bulleted()->columnSpanFull()
                        ->color(fn (Invoice $r) => $r->zatca_status === 'rejected' ? 'danger' : 'warning')
                        ->visible(fn (Invoice $r) => filled($r->zatca_messages)),
                ]),
                Section::make(__('مرجع الإشعار'))->icon(Heroicon::OutlinedArrowUturnLeft)->visible(fn (Invoice $r) => $r->kind !== 'invoice')->schema([
                    TextEntry::make('billing_reference')->label(__('الفاتورة الأصلية'))->weight('bold')
                        ->url(fn (Invoice $r) => ($id = Invoice::where('number', $r->billing_reference)->value('id')) ? InvoiceResource::getUrl('view', ['record' => $id]) : null),
                    TextEntry::make('reason')->label(__('السبب'))->placeholder('-'),
                ]),
                Section::make(__('المستند'))->icon(Heroicon::OutlinedDocumentText)->columns(2)->schema([
                    TextEntry::make('number')->label(__('الرقم'))->weight('bold'),
                    Parts::date('issue_date', 'تاريخ الإصدار'),
                    TextEntry::make('type')->label(__('النوع'))->badge()->color('gray')
                        ->formatStateUsing(fn ($state) => $state === 'standard' ? __('ضريبية') : __('مبسطة')),
                    TextEntry::make('calendar')->label(__('التقويم'))->badge()->color('gray'),
                ]),
                Parts::record(),
            ]),
        ]);
    }
}
