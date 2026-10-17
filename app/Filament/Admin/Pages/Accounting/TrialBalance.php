<?php

namespace App\Filament\Admin\Pages\Accounting;

use App\Services\Accounting\Statements;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/** ميزان المراجعة: opening balance, the period's debits and credits, and the closing balance of every account. */
class TrialBalance extends AccountingPage
{
    protected static string $ability = 'trial_balance';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.admin.accounting.trial-balance';

    public bool $details = true;

    protected static bool $landscape = true;

    public static function getNavigationLabel(): string
    {
        return __('ميزان المراجعة');
    }

    public function rows(): Collection
    {
        return app(Statements::class)->trialBalance($this->from, $this->until)
            ->filter(fn (array $r) => $this->details || $r['account']->is_group || $r['depth'] === 0);
    }

    /** Totals of the accounts that take entries (headings would count them twice). */
    public function totals(): array
    {
        $leaves = app(Statements::class)->trialBalance($this->from, $this->until)->reject(fn (array $r) => $r['account']->is_group);
        $side = fn (string $k, bool $debitSide) => round($leaves->sum(function (array $r) use ($k, $debitSide) {
            $signed = $r['account']->type->isDebitNormal() ? $r[$k] : -$r[$k];

            return $debitSide ? max($signed, 0) : max(-$signed, 0);
        }), 2);

        return [
            'opening_debit' => $side('opening', true), 'opening_credit' => $side('opening', false),
            'debit' => round($leaves->sum('debit'), 2), 'credit' => round($leaves->sum('credit'), 2),
            'closing_debit' => $side('closing', true), 'closing_credit' => $side('closing', false),
        ];
    }
}
