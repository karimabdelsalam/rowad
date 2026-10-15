<?php

namespace App\Filament\Admin\Clusters\Reports\Pages;

use App\Filament\Admin\Clusters\Reports\ReportPage;
use App\Models\BankTransaction;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

/** Legacy reports/profits: income vs expenses (vouchers and bank movements), month by month. */
class ProfitsReport extends ReportPage
{
    protected static string $report = 'profits';

    protected static ?int $navigationSort = 3;

    protected static function title(): string
    {
        return __('تقرير الأرباح');
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (array $filters) {
                $year = (int) ($filters['year']['year'] ?? now()->year);
                $sum = fn ($query, string $column, int $m) => round((float) (clone $query)->whereYear($column, $year)->whereMonth($column, $m)->sum('amount'), 2);

                return collect(range(1, 12))->mapWithKeys(function (int $m) use ($sum, $year) {
                    $income = $sum(ReceiptVoucher::query(), 'issued_at', $m);
                    $expenses = $sum(PaymentVoucher::query(), 'issued_at', $m);

                    return [$m => [
                        'month' => now()->setDate($year, $m, 1)->translatedFormat('F Y'),
                        'income' => $income,
                        'expenses' => $expenses,
                        'net' => round($income - $expenses, 2),
                        'deposits' => $sum(BankTransaction::where('type', 'deposit'), 'occurred_at', $m),
                        'withdrawals' => $sum(BankTransaction::where('type', 'withdrawal'), 'occurred_at', $m),
                    ]];
                })->pipe(fn ($rows) => $rows->put('total', ['month' => __('الإجمالي')] + collect(['income', 'expenses', 'net', 'deposits', 'withdrawals'])
                    ->mapWithKeys(fn ($k) => [$k => round($rows->sum($k), 2)])->all()))->all();
            })
            ->paginated(false)
            ->columns([
                TextColumn::make('month')->label(__('الشهر')),
                TextColumn::make('income')->label(__('الإيرادات'))->numeric(2),
                TextColumn::make('expenses')->label(__('المصروفات'))->numeric(2),
                TextColumn::make('net')->label(__('الصافي'))->numeric(2)->color(fn ($state) => $state < 0 ? 'danger' : 'success'),
                TextColumn::make('deposits')->label(__('إيداعات بنكية'))->numeric(2),
                TextColumn::make('withdrawals')->label(__('سحوبات بنكية'))->numeric(2),
            ])
            ->filters([
                Filter::make('year')->schema([TextInput::make('year')->label(__('السنة'))->numeric()->default(now()->year)]),
            ]);
    }
}
