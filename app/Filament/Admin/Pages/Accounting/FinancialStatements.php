<?php

namespace App\Filament\Admin\Pages\Accounting;

use App\Services\Accounting\Statements;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/** القوائم المالية: the income statement for the period and the balance sheet at its end. */
class FinancialStatements extends AccountingPage
{
    protected static string $ability = 'statements';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.admin.accounting.statements';

    #[Url]
    public string $tab = 'income';

    public static function getNavigationLabel(): string
    {
        return __('القوائم المالية');
    }

    public function income(): array
    {
        return app(Statements::class)->incomeStatement($this->from, $this->until);
    }

    public function balance(): array
    {
        return app(Statements::class)->balanceSheet($this->until);
    }
}
