<?php

namespace App\Filament\Admin\Clusters\Reports\Pages;

use App\Filament\Admin\Clusters\Reports\ReportPage;
use App\Models\OwnerTransaction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Legacy reports/taxes: VAT and owner tax per unit (owner ledger entries). */
class TaxesReport extends ReportPage
{
    protected static string $report = 'taxes';

    protected static ?int $navigationSort = 5;

    /** @var list<string> */
    protected static array $codes = ['tax', 'owner_tax'];

    protected static function title(): string
    {
        return __('تقرير الضرائب');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(OwnerTransaction::query()->with(['type', 'unit', 'owner'])->whereHas('type', fn (Builder $q) => $q->whereIn('code', static::$codes)))
            ->defaultSort('due_date')
            ->columns([
                TextColumn::make('due_date')->date('Y-m-d')->sortable(),
                TextColumn::make('type.title')->label(__('النوع')),
                TextColumn::make('unit.title'),
                TextColumn::make('owner.full_name')->label(__('المالك'))->placeholder(__('المكتب')),
                TextColumn::make('is_settled')->label(__('مسوّى'))->badge()->formatStateUsing(fn ($state) => $state ? __('نعم') : __('لا')),
                TextColumn::make('amount')->numeric(2)->summarize(Sum::make('sum')->numeric(2)),
            ])
            ->filters([
                self::periodFilter('due_date'),
                SelectFilter::make('unit_id')->label(__('الوحدة'))->relationship('unit', 'title')->searchable(),
            ]);
    }
}
