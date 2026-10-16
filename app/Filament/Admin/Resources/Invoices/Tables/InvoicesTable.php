<?php

namespace App\Filament\Admin\Resources\Invoices\Tables;

use App\Filament\Admin\Resources\Invoices\Schemas\InvoiceInfolist;
use App\Models\Invoice;
use App\Services\Zatca\Phase2\ZatcaInvoicing;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Invoices and credit/debit notes with their e-invoicing state. Issued tax documents are never deleted. */
class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('client'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('number')->label(__('الرقم'))->searchable()->sortable()->weight('bold')
                    ->description(fn (Invoice $record) => $record->kind !== 'invoice' ? __('على :number', ['number' => $record->billing_reference]) : null),
                TextColumn::make('kind')->label(__('المستند'))->badge()
                    ->state(fn (Invoice $record) => $record->kind === 'invoice' ? $record->type : $record->kind)
                    ->formatStateUsing(fn ($state) => self::kinds()[$state] ?? $state)
                    ->color(fn ($state) => ['credit' => 'danger', 'debit' => 'warning', 'standard' => 'primary'][$state] ?? 'gray'),
                TextColumn::make('customer')->label(__('العميل'))
                    ->state(fn (Invoice $record) => $record->customer_name ?: $record->client?->full_name)
                    ->description(fn (Invoice $record) => $record->customer_vat_number)
                    ->searchable(['customer_name', 'customer_vat_number']),
                TextColumn::make('issue_date')->label(__('التاريخ'))->date('Y-m-d')->sortable(),
                TextColumn::make('subtotal')->label(__('قبل الضريبة'))->numeric(2)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('vat_total')->label(__('الضريبة'))->numeric(2)->toggleable(),
                TextColumn::make('total')->label(__('الإجمالي'))->numeric(2)->sortable()->weight('bold'),
                TextColumn::make('paid_amount')->label(__('المدفوع'))->numeric(2)->color('success')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('zatca_status')->label(__('زاتكا'))->badge()
                    ->formatStateUsing(fn ($state) => __(ZatcaInvoicing::STATUSES[$state] ?? $state))
                    ->color(fn ($state) => InvoiceInfolist::STATUS_COLORS[$state] ?? 'gray')
                    ->icon(fn ($state) => InvoiceInfolist::STATUS_ICONS[$state] ?? null)
                    ->placeholder(__('رمز QR')),
                TextColumn::make('zatca_icv')->label('ICV')->numeric()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('zatca_uuid')->label('UUID')->fontFamily('mono')->size('xs')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label(__('أنشئت'))->dateTime('Y-m-d H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('zatca_status')->label(__('حالة زاتكا'))
                    ->options(collect(ZatcaInvoicing::STATUSES)->map(fn ($label) => __($label))->all()),
                SelectFilter::make('kind')->label(__('المستند'))
                    ->options(['invoice' => __('فاتورة'), 'credit' => __('إشعار دائن'), 'debit' => __('إشعار مدين')]),
                SelectFilter::make('type')->label(__('النوع'))
                    ->options(['standard' => __('ضريبية'), 'simplified' => __('مبسطة')]),
                Filter::make('unpaid')->label(__('غير مسددة'))
                    ->query(fn (Builder $query) => $query->where('kind', 'invoice')->whereColumn('paid_amount', '<', 'total')),
            ])
            ->recordActions([
                Action::make('print')
                    ->label(__('طباعة'))
                    ->icon(Heroicon::OutlinedPrinter)
                    ->url(fn (Invoice $record) => route('documents.print', ['type' => 'invoice', 'id' => $record->getKey()]))
                    ->openUrlInNewTab(),
                ViewAction::make(),
            ]);
    }

    /** @return array<string, string> */
    private static function kinds(): array
    {
        return ['standard' => __('ضريبية'), 'simplified' => __('مبسطة'), 'credit' => __('إشعار دائن'), 'debit' => __('إشعار مدين')];
    }
}
