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
            ->description(fn () => __('الرصيد المستحق لك').': '.number_format(Portal::owner()->outstandingBalance(), 2))
            ->columns([
                TextColumn::make('due_date')->date('Y-m-d')->sortable(),
                TextColumn::make('unit.title'),
                TextColumn::make('type.title')->label(__('البيان')),
                TextColumn::make('direction')->badge()->color(fn ($state) => $state->value === 'debit' ? 'success' : 'danger'),
                TextColumn::make('amount')->numeric(2),
                IconColumn::make('is_settled')->boolean(),
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
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageOwnerTransactions::route('/')];
    }
}
