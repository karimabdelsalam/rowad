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
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

/** Legacy reports/building: receivables of a unit over a period, per contract. */
class UnitReport extends ReportPage
{
    protected static string $report = 'unit';

    protected static ?int $navigationSort = 7;

    protected static function title(): string
    {
        return __('تقرير وحدة');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Installment::query()->with(['unit', 'contract']))
            ->defaultSort('due_date')
            ->defaultGroup(Group::make('contract_id')->label(__('العقد'))->getTitleFromRecordUsing(fn ($record) => $record->contract?->number ?? '#'.$record->contract_id))
            ->columns([
                TextColumn::make('due_date')->date('Y-m-d')->sortable(),
                TextColumn::make('unit.title'),
                TextColumn::make('type')->badge(),
                TextColumn::make('amount')->numeric(2)->summarize(Sum::make('sum')->numeric(2)),
                ...self::paidColumns(),
                IconColumn::make('is_collected')->boolean(),
            ])
            ->filters([
                SelectFilter::make('unit_id')->label(__('الوحدة'))->relationship('unit', 'title')->searchable()->preload(),
                self::periodFilter('due_date'),
                TernaryFilter::make('is_collected'),
                SelectFilter::make('type')->options(InstallmentType::class),
            ]);
    }
}
