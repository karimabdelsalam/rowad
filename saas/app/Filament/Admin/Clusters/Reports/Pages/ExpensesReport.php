<?php

namespace App\Filament\Admin\Clusters\Reports\Pages;

use App\Filament\Admin\Clusters\Reports\ReportPage;
use App\Models\PaymentVoucher;
use Filament\Tables\Table;

/** Legacy reports/outgoings. */
class ExpensesReport extends ReportPage
{
    protected static string $report = 'expenses';

    protected static ?int $navigationSort = 2;

    protected static function title(): string
    {
        return __('تقرير المصروفات');
    }

    public function table(Table $table): Table
    {
        return IncomeReport::voucherTable($table, PaymentVoucher::query()->with(['party', 'unit', 'expenseType']), 'expenseType.title');
    }
}
