<?php

namespace App\Services\Accounting;

use App\Enums\AccountType;
use App\Models\Account;
use App\Support\Branches;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Trial balance, account ledger, income statement and balance sheet, read from the journal. */
class Statements
{
    /**
     * Every account with its opening balance (before $from), the period's debits and credits and the closing
     * balance, headings rolled up from their sub-accounts. Balances are signed by the account's normal side.
     *
     * @return Collection<int, array{account: Account, depth: int, opening: float, debit: float, credit: float, closing: float}>
     */
    public function trialBalance(?string $from, ?string $until, bool $withEmpty = false): Collection
    {
        $before = $from ? $this->sums(fn (Builder $q) => $q->whereDate('e.entry_date', '<', $from)) : collect();
        $period = $this->sums(fn (Builder $q) => $q->when($from, fn ($q) => $q->whereDate('e.entry_date', '>=', $from))
            ->when($until, fn ($q) => $q->whereDate('e.entry_date', '<=', $until)));

        $accounts = Account::query()->orderBy('code')->get();
        $rows = [];
        foreach ($accounts as $account) {
            $sign = $account->type->isDebitNormal() ? 1 : -1;
            $o = $before[$account->id] ?? null;
            $p = $period[$account->id] ?? null;
            $rows[$account->id] = [
                'account' => $account,
                'depth' => 0,
                'opening' => $o ? $sign * ((float) $o->debit - (float) $o->credit) : 0.0,
                'debit' => (float) ($p->debit ?? 0),
                'credit' => (float) ($p->credit ?? 0),
            ];
        }
        // roll sub-accounts up into their headings (deepest first: codes sort parents before children)
        foreach ($accounts->sortByDesc(fn (Account $a) => strlen($a->code)) as $account) {
            if ($account->parent_id && isset($rows[$account->parent_id])) {
                foreach (['opening', 'debit', 'credit'] as $k) {
                    $rows[$account->parent_id][$k] += $rows[$account->id][$k];
                }
            }
        }

        return collect($rows)->map(function (array $row) use ($accounts) {
            $sign = $row['account']->type->isDebitNormal() ? 1 : -1;
            $row['depth'] = $this->depth($accounts, $row['account']->id);
            $row['closing'] = round($row['opening'] + $sign * ($row['debit'] - $row['credit']), 2);

            return array_map(fn ($v) => is_float($v) ? round($v, 2) + 0.0 : $v, $row);
        })->filter(fn (array $r) => $withEmpty || $r['opening'] != 0 || $r['debit'] != 0 || $r['credit'] != 0)->values();
    }

    /**
     * One account's lines in the period with a running balance.
     *
     * @return array{opening: float, lines: Collection<int, object>, closing: float, debit: float, credit: float}
     */
    public function ledger(Account $account, ?string $from, ?string $until): array
    {
        $ids = $this->withDescendants($account);
        $sign = $account->type->isDebitNormal() ? 1 : -1;
        $opening = 0.0;
        if ($from) {
            $o = $this->lines()->whereIn('l.account_id', $ids)->whereDate('e.entry_date', '<', $from)
                ->selectRaw('coalesce(sum(l.debit), 0) as debit, coalesce(sum(l.credit), 0) as credit')->first();
            $opening = round($sign * ((float) $o->debit - (float) $o->credit), 2) + 0.0; // never -0
        }
        $balance = $opening;
        $lines = $this->lines()->whereIn('l.account_id', $ids)
            ->when($from, fn ($q) => $q->whereDate('e.entry_date', '>=', $from))
            ->when($until, fn ($q) => $q->whereDate('e.entry_date', '<=', $until))
            ->orderBy('e.entry_date')->orderBy('e.id')->orderBy('l.id')
            ->get(['e.id as entry_id', 'e.number', 'e.entry_date', 'e.description', 'e.source_type', 'l.debit', 'l.credit', 'l.memo', 'l.account_id'])
            ->map(function (object $line) use (&$balance, $sign) {
                $balance = round($balance + $sign * ((float) $line->debit - (float) $line->credit), 2);
                $line->balance = $balance;

                return $line;
            });

        return ['opening' => $opening, 'lines' => $lines, 'closing' => $balance,
            'debit' => round((float) $lines->sum('debit'), 2), 'credit' => round((float) $lines->sum('credit'), 2)];
    }

    /** @return array{revenue: Collection, expenses: Collection, total_revenue: float, total_expenses: float, net: float} */
    public function incomeStatement(?string $from, ?string $until): array
    {
        $rows = $this->trialBalance($from, $until)->filter(fn ($r) => ! $r['account']->is_group);
        $movement = fn (array $r) => round($r['account']->type->isDebitNormal() ? $r['debit'] - $r['credit'] : $r['credit'] - $r['debit'], 2);
        $revenue = $rows->filter(fn ($r) => $r['account']->type === AccountType::Revenue)->map(fn ($r) => ['account' => $r['account'], 'amount' => $movement($r)])->values();
        $expenses = $rows->filter(fn ($r) => $r['account']->type === AccountType::Expense)->map(fn ($r) => ['account' => $r['account'], 'amount' => $movement($r)])->values();
        $totalRevenue = round($revenue->sum('amount'), 2);
        $totalExpenses = round($expenses->sum('amount'), 2);

        return ['revenue' => $revenue, 'expenses' => $expenses, 'total_revenue' => $totalRevenue,
            'total_expenses' => $totalExpenses, 'net' => round($totalRevenue - $totalExpenses, 2)];
    }

    /** Balances as of a date; the profit not yet closed into retained earnings shows under equity. */
    public function balanceSheet(?string $asOf): array
    {
        $rows = $this->trialBalance(null, $asOf)->filter(fn ($r) => ! $r['account']->is_group && $r['closing'] != 0);
        $of = fn (AccountType $type) => $rows->filter(fn ($r) => $r['account']->type === $type)->map(fn ($r) => ['account' => $r['account'], 'amount' => $r['closing']])->values();
        $profit = $this->incomeStatement(null, $asOf)['net'];
        $assets = $of(AccountType::Asset);
        $liabilities = $of(AccountType::Liability);
        $equity = $of(AccountType::Equity);

        return [
            'assets' => $assets, 'liabilities' => $liabilities, 'equity' => $equity, 'profit' => $profit,
            'total_assets' => round($assets->sum('amount'), 2),
            'total_liabilities' => round($liabilities->sum('amount'), 2),
            'total_equity' => round($equity->sum('amount') + $profit, 2),
        ];
    }

    /** @return Collection<int, object{debit: string, credit: string}> keyed by account id */
    private function sums(callable $period): Collection
    {
        $query = $this->lines()->groupBy('l.account_id')
            ->selectRaw('l.account_id, coalesce(sum(l.debit), 0) as debit, coalesce(sum(l.credit), 0) as credit');
        $period($query);

        return $query->get()->keyBy('account_id');
    }

    private function lines(): Builder
    {
        return DB::table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->when(Branches::current(), fn (Builder $q, int $branch) => $q->where('e.branch_id', $branch));
    }

    /** @return list<int> */
    private function withDescendants(Account $account): array
    {
        $all = Account::query()->get(['id', 'parent_id']);
        $ids = [$account->id];
        for ($i = 0; $i < count($ids); $i++) {
            foreach ($all->where('parent_id', $ids[$i]) as $child) {
                $ids[] = $child->id;
            }
        }

        return $ids;
    }

    /** How many headings sit above the account. */
    private function depth(Collection $accounts, int $id): int
    {
        $parent = $accounts->firstWhere('id', $id)?->parent_id;

        return $parent ? 1 + $this->depth($accounts, $parent) : 0;
    }
}
