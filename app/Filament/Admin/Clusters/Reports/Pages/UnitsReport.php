<?php

namespace App\Filament\Admin\Clusters\Reports\Pages;

use App\Filament\Admin\Clusters\Reports\ReportPage;
use App\Models\Installment;
use App\Models\Unit;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/** Legacy reports/units: per-unit collected rent and sale amounts for a year. */
class UnitsReport extends ReportPage
{
    protected static string $report = 'units';

    protected static ?int $navigationSort = 8;

    protected static function title(): string
    {
        return __('تقرير الوحدات السنوي');
    }

    public function table(Table $table): Table
    {
        return $table
            // paginated by hand: an office can have thousands of units; the totals row leads every page
            ->records(function (array $filters, ?string $search, int|string $page, int|string $recordsPerPage) {
                $rows = $this->rows($filters, $search);
                $total = $rows->pull('total');
                $perPage = $recordsPerPage === 'all' ? max($rows->count(), 1) : (int) $recordsPerPage;

                return new LengthAwarePaginator(['total' => $total] + $rows->forPage((int) $page, $perPage)->all(), $rows->count(), $perPage, (int) $page);
            })
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('title')->label(__('الوحدة'))->searchable(),
                TextColumn::make('building')->label(__('المبنى')),
                TextColumn::make('status')->label(__('الحالة'))->badge(),
                TextColumn::make('rent')->label(__('محصل الإيجار'))->numeric(2),
                TextColumn::make('sale')->label(__('محصل البيع'))->numeric(2),
                TextColumn::make('total')->label(__('الإجمالي'))->numeric(2),
            ])
            ->filters([
                Filter::make('year')->schema([TextInput::make('year')->label(__('السنة'))->numeric()->default(now()->year)]),
            ]);
    }

    /** @return Collection<int|string, array<string, mixed>> per-unit collected amounts, plus a 'total' row */
    private function rows(array $filters, ?string $search = null): Collection
    {
        $year = (int) ($filters['year']['year'] ?? now()->year);
        $totals = Installment::query()->where('is_collected', true)->whereYear('collected_at', $year)
            ->where('type', '!=', 'expenses')
            ->selectRaw('unit_id, contract_type, SUM(amount) as total')->groupBy('unit_id', 'contract_type')->get()
            ->groupBy('unit_id');

        return Unit::query()->with('building')->orderBy('title')
            ->when(filled($search), fn ($q) => $q->where('title', 'like', '%'.trim((string) $search).'%'))
            ->get()->mapWithKeys(function (Unit $u) use ($totals) {
                $rows = $totals->get($u->id, collect());
                $rent = (float) $rows->where('contract_type', 'rent_contract')->sum('total');
                $sale = (float) $rows->where('contract_type', 'sale_contract')->sum('total');

                return [$u->id => [
                    'title' => $u->title, 'building' => $u->building?->title, 'status' => $u->status?->getLabel(),
                    'rent' => round($rent, 2), 'sale' => round($sale, 2), 'total' => round($rent + $sale, 2),
                ]];
            })->pipe(fn ($rows) => $rows->put('total', ['title' => __('الإجمالي'), 'building' => null, 'status' => null]
                + collect(['rent', 'sale', 'total'])->mapWithKeys(fn ($k) => [$k => round($rows->sum($k), 2)])->all()));
    }

    protected function exportRecords(): iterable
    {
        $key = \Filament\Support\ArrayRecord::getKeyName();

        return $this->rows($this->tableFilters ?? [], $this->getTableSearch())->map(fn (array $row, $id) => $row + [$key => (string) $id])->all();
    }
}
