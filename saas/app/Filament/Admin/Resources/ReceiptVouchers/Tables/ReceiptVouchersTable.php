<?php

namespace App\Filament\Admin\Resources\ReceiptVouchers\Tables;

use App\Enums\PaymentMethod;
use App\Filament\Admin\Resources\Concerns\VoucherFormComponents;
use App\Models\ReceiptVoucher;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;

class ReceiptVouchersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['party', 'unit', 'bankAccount']))
            ->defaultSort('issued_at', 'desc')
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('issued_at')->date('Y-m-d')->sortable(),
                TextColumn::make('party_display')
                    ->label(__('الطرف'))
                    ->state(fn (ReceiptVoucher $record) => $record->party_name ?: ($record->party?->full_name ?? $record->party?->name ?? $record->party?->title))
                    ->description(fn (ReceiptVoucher $record) => VoucherFormComponents::partyTypes()[$record->party_type] ?? null),
                TextColumn::make('unit.title')->toggleable(),
                TextColumn::make('amount')->numeric(2)->sortable()->summarize(\Filament\Tables\Columns\Summarizers\Sum::make()->numeric(2)),
                TextColumn::make('payment_method')->badge(),
                TextColumn::make('bankAccount.holder_name')->toggleable(),
                TextColumn::make('reason')->limit(40)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('payment_method')->options(PaymentMethod::class),
                SelectFilter::make('party_type')->label(__('نوع الطرف'))->options(VoucherFormComponents::partyTypes()),
                Filter::make('period')
                    ->schema([DatePicker::make('from')->label(__('من')), DatePicker::make('until')->label(__('إلى'))])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('issued_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('issued_at', '<=', $d))),
                TrashedFilter::make(),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('print')
                    ->label(__('طباعة'))
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedPrinter)
                    ->url(fn ($record) => route('documents.print', ['type' => 'receipt_voucher', 'id' => $record->getKey()]))
                    ->openUrlInNewTab(),
ViewAction::make(), EditAction::make()]);
    }
}
