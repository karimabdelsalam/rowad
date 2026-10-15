<?php

namespace App\Filament\Admin\Clusters\Reports\Pages;

use App\Enums\PaymentMethod;
use App\Filament\Admin\Clusters\Reports\ReportPage;
use App\Filament\Admin\Resources\Concerns\VoucherFormComponents;
use App\Models\ReceiptVoucher;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Legacy reports/income: receipt vouchers with totals by method and counterparty type. */
class IncomeReport extends ReportPage
{
    protected static string $report = 'income';

    protected static ?int $navigationSort = 1;

    protected static function title(): string
    {
        return __('تقرير الإيرادات');
    }

    public function table(Table $table): Table
    {
        return self::voucherTable($table, ReceiptVoucher::query()->with(['party', 'unit', 'transactionType']), 'transactionType.title');
    }

    public static function voucherTable(Table $table, Builder $query, string $typeColumn): Table
    {
        return $table
            ->query($query)
            ->defaultSort('issued_at')
            ->groups([
                Group::make('payment_method')->label(__('طريقة الدفع')),
                Group::make('party_type')->label(__('نوع الطرف'))->getTitleFromRecordUsing(fn ($record) => VoucherFormComponents::partyTypes()[$record->party_type] ?? __('أخرى')),
            ])
            ->columns([
                TextColumn::make('number')->searchable(),
                TextColumn::make('issued_at')->date('Y-m-d')->sortable(),
                TextColumn::make('party_display')->label(__('الطرف'))
                    ->state(fn ($record) => $record->party_name ?: ($record->party?->full_name ?? $record->party?->name ?? $record->party?->title)),
                TextColumn::make('unit.title'),
                TextColumn::make($typeColumn)->label(__('النوع')),
                TextColumn::make('payment_method')->badge(),
                TextColumn::make('reason')->limit(40),
                TextColumn::make('vat_amount')->numeric(2)->summarize(Sum::make('sum')->numeric(2)),
                TextColumn::make('amount')->numeric(2)->sortable()->summarize(Sum::make('sum')->numeric(2)),
            ])
            ->filters([
                self::periodFilter('issued_at'),
                SelectFilter::make('payment_method')->options(PaymentMethod::class),
                SelectFilter::make('party_type')->label(__('نوع الطرف'))->options(VoucherFormComponents::partyTypes()),
                Filter::make('amount_range')->label(__('المبلغ'))
                    ->schema([TextInput::make('min')->label(__('من مبلغ'))->numeric(), TextInput::make('max')->label(__('إلى مبلغ'))->numeric()])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['min'] ?? null, fn ($q, $v) => $q->where('amount', '>=', $v))
                        ->when($data['max'] ?? null, fn ($q, $v) => $q->where('amount', '<=', $v))),
            ], layout: \Filament\Tables\Enums\FiltersLayout::AboveContentCollapsible);
    }
}
