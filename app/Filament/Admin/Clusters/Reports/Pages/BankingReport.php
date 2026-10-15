<?php

namespace App\Filament\Admin\Clusters\Reports\Pages;

use App\Filament\Admin\Clusters\Reports\ReportPage;
use App\Models\BankTransaction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

/** Legacy reports/banking: bank account movements. */
class BankingReport extends ReportPage
{
    protected static string $report = 'banking';

    protected static ?int $navigationSort = 4;

    protected static function title(): string
    {
        return __('تقرير الحركات البنكية');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(BankTransaction::query()->with(['account', 'voucher']))
            ->defaultSort('occurred_at')
            ->defaultGroup(Group::make('account.holder_name')->label(__('الحساب')))
            ->columns([
                TextColumn::make('occurred_at')->date('Y-m-d')->sortable(),
                TextColumn::make('account.holder_name')->label(__('الحساب')),
                TextColumn::make('type')->label(__('النوع'))->badge()
                    ->formatStateUsing(fn ($state) => $state === 'deposit' ? __('إيداع') : __('سحب'))
                    ->color(fn ($state) => $state === 'deposit' ? 'success' : 'danger'),
                TextColumn::make('voucher.number')->label(__('السند')),
                TextColumn::make('description')->label(__('البيان'))->limit(40),
                TextColumn::make('deposit')->label(__('إيداع'))->state(fn ($record) => $record->type === 'deposit' ? (float) $record->amount : null)->numeric(2),
                TextColumn::make('withdrawal')->label(__('سحب'))->state(fn ($record) => $record->type === 'withdrawal' ? (float) $record->amount : null)->numeric(2),
                TextColumn::make('amount')->numeric(2)->summarize(Sum::make('sum')->numeric(2)),
            ])
            ->filters([
                self::periodFilter('occurred_at'),
                SelectFilter::make('bank_account_id')->label(__('الحساب'))->relationship('account', 'holder_name'),
                SelectFilter::make('type')->label(__('النوع'))->options(['deposit' => __('إيداع'), 'withdrawal' => __('سحب')]),
            ]);
    }
}
