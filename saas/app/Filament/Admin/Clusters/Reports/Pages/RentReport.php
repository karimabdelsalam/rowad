<?php

namespace App\Filament\Admin\Clusters\Reports\Pages;

use App\Enums\InstallmentType;
use App\Filament\Admin\Clusters\Reports\ReportPage;
use App\Models\Installment;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/** Legacy reports/rent: rent receivables with paid and remaining amounts. */
class RentReport extends ReportPage
{
    protected static string $report = 'rent';

    protected static ?int $navigationSort = 9;

    protected static function title(): string
    {
        return __('تقرير الإيجارات');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Installment::query()->where('contract_type', 'rent_contract')->with(['unit', 'contract.clients']))
            ->defaultSort('due_date')
            ->columns([
                TextColumn::make('due_date')->date('Y-m-d')->sortable(),
                TextColumn::make('contract.number')->label(__('العقد')),
                TextColumn::make('renters')->label(__('المستأجرون'))->state(fn ($record) => $record->contract?->clients->pluck('full_name')->join('، ')),
                TextColumn::make('unit.title'),
                TextColumn::make('type')->badge(),
                TextColumn::make('amount')->numeric(2)->summarize(Sum::make('sum')->numeric(2)),
                ...self::paidColumns(),
                IconColumn::make('is_collected')->boolean(),
            ])
            ->filters([
                self::periodFilter('due_date'),
                TernaryFilter::make('is_collected'),
                SelectFilter::make('type')->options(collect(InstallmentType::cases())->filter->isRent()->mapWithKeys(fn ($c) => [$c->value => $c->getLabel()])),
            ]);
    }
}
