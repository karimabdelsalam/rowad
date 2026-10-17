<?php

namespace App\Filament\Owner\Resources\OwnerTransactions;

use App\Filament\Owner\Resources\OwnerTransactions\Pages\ManageOwnerTransactions;
use App\Filament\PortalResource;
use App\Models\OwnerTransaction;
use App\Support\Portal;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** The owner's account statement (legacy units/transactions). */
class OwnerTransactionResource extends PortalResource
{
    protected static ?string $model = OwnerTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'حركة';

    protected static ?string $pluralModelLabel = 'كشف الحساب';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('owner_id', Portal::owner()->getKey())->with(['unit', 'type', 'receiptVoucher', 'paymentVoucher']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_date', 'desc')
            ->striped()
            ->description(fn () => __('الرصيد المستحق لك').': '.number_format(Portal::owner()->outstandingBalance(), 2))
            ->columns([
                TextColumn::make('due_date')->label(__('التاريخ'))->date('Y-m-d')->sortable()->weight('bold'),
                TextColumn::make('type.title')->label(__('البيان'))->weight('medium')
                    ->description(fn ($record) => $record->unit?->title),
                TextColumn::make('direction')->label(__('الاتجاه'))->badge()
                    ->formatStateUsing(fn ($state) => $state->value === 'debit' ? __('لك') : __('عليك')),
                TextColumn::make('amount')->label(__('المبلغ'))->numeric(2)->weight('bold')
                    ->color(fn ($record) => $record->direction->value === 'debit' ? 'success' : 'danger')
                    ->prefix(fn ($record) => $record->direction->value === 'debit' ? '+ ' : '- '),
                TextColumn::make('is_settled')->label(__('الحالة'))->badge()
                    ->formatStateUsing(fn ($state) => $state ? __('مُرحّلة') : __('معلّقة'))
                    ->color(fn ($state) => $state ? 'success' : 'warning')
                    ->description(fn ($record) => $record->settled_at?->format('Y-m-d')),
                TextColumn::make('voucher')->label(__('السند'))->placeholder('-')->fontFamily('mono')->size('xs')
                    ->state(fn ($record) => $record->paymentVoucher?->number ?? $record->receiptVoucher?->number),
            ])
            ->filters([
                TernaryFilter::make('is_settled'),
                \Filament\Tables\Filters\SelectFilter::make('unit_id')->label(__('الوحدة'))
                    ->options(fn () => Portal::ownerUnits(Portal::owner())->pluck('title', 'id')),
                \Filament\Tables\Filters\SelectFilter::make('transaction_type_id')->label(__('البيان'))->relationship('type', 'title'),
                \Filament\Tables\Filters\Filter::make('due_date')
                    ->schema([\Filament\Forms\Components\DatePicker::make('from')->label(__('من تاريخ')), \Filament\Forms\Components\DatePicker::make('until')->label(__('إلى تاريخ'))])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('due_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('due_date', '<=', $date))),
            ])
            // legacy printstatin / printstatout: the voucher behind an entry, when it was issued to this owner
            ->recordActions([
                \Filament\Actions\Action::make('printVoucher')
                    ->label(__('طباعة السند'))
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedPrinter)
                    ->visible(fn ($record) => ($record->paymentVoucher?->party_type === 'owner' && (int) $record->paymentVoucher->party_id === Portal::owner()->getKey())
                        || ($record->receiptVoucher?->party_type === 'owner' && (int) $record->receiptVoucher->party_id === Portal::owner()->getKey()))
                    ->url(fn ($record) => $record->paymentVoucher?->party_type === 'owner'
                        ? route('documents.print', ['type' => 'payment_voucher', 'id' => $record->payment_voucher_id])
                        : route('documents.print', ['type' => 'receipt_voucher', 'id' => $record->receipt_voucher_id]), shouldOpenInNewTab: true),
                \Filament\Actions\Action::make('voucherPdf')
                    ->label('PDF')
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedArrowDownTray)
                    ->visible(fn ($record) => ($record->paymentVoucher?->party_type === 'owner' && (int) $record->paymentVoucher->party_id === Portal::owner()->getKey())
                        || ($record->receiptVoucher?->party_type === 'owner' && (int) $record->receiptVoucher->party_id === Portal::owner()->getKey()))
                    ->url(fn ($record) => $record->paymentVoucher?->party_type === 'owner'
                        ? route('documents.print', ['type' => 'payment_voucher', 'id' => $record->payment_voucher_id, 'pdf' => 1])
                        : route('documents.print', ['type' => 'receipt_voucher', 'id' => $record->receipt_voucher_id, 'pdf' => 1]), shouldOpenInNewTab: true),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageOwnerTransactions::route('/')];
    }
}
