<?php

namespace App\Filament\Admin\Clusters\Reports\Pages;

use App\Filament\Admin\Clusters\Reports\ReportPage;
use App\Models\Client;
use App\Models\Installment;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Legacy reports/renter: one client's account across all their contracts. */
class RenterReport extends ReportPage
{
    protected static string $report = 'renter';

    protected static ?int $navigationSort = 10;

    protected static function title(): string
    {
        return __('كشف حساب عميل');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Installment::query()->with(['unit', 'contract']))
            ->defaultSort('due_date')
            ->columns([
                TextColumn::make('due_date')->date('Y-m-d')->sortable(),
                TextColumn::make('contract.number')->label(__('العقد')),
                TextColumn::make('unit.title'),
                TextColumn::make('type')->badge()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('amount')->numeric(2)->summarize(Sum::make('sum')->numeric(2)),
                ...self::paidColumns(),
                IconColumn::make('is_collected')->boolean(),
            ])
            ->filters([
                Filter::make('client')
                    ->schema([Select::make('client_id')->label(__('العميل'))->options(fn () => Client::orderBy('full_name')->pluck('full_name', 'id'))->searchable()])
                    ->query(fn (Builder $query, array $data) => filled($data['client_id'] ?? null)
                        ? $query->whereHasMorph('contract', ['rent_contract', 'sale_contract'], fn ($q) => $q->whereHas('clients', fn ($q) => $q->whereKey($data['client_id'])))
                        : $query->whereRaw('1 = 0'))
                    ->indicateUsing(fn (array $data) => filled($data['client_id'] ?? null) ? Client::find($data['client_id'])?->full_name : __('اختر العميل')),
                self::periodFilter('due_date'),
            ]);
    }
}
