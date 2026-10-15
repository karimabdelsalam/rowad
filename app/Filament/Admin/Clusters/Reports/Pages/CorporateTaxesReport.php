<?php

namespace App\Filament\Admin\Clusters\Reports\Pages;

/** Legacy reports/corp_taxes: the office's income tax on its commissions and management fees. */
class CorporateTaxesReport extends TaxesReport
{
    protected static string $report = 'corporate_taxes';

    protected static ?int $navigationSort = 6;

    protected static array $codes = ['comp_tax'];

    protected static function title(): string
    {
        return __('تقرير ضريبة دخل المؤسسة');
    }
}
