<?php

namespace App\Services\Reports;

use App\Enums\ContractStatus;
use App\Enums\LedgerDirection;
use App\Enums\UnitStatus;
use App\Models\Client;
use App\Models\Installment;
use App\Models\Owner;
use App\Models\OwnerTransaction;
use App\Models\RentContract;
use App\Models\SaleContract;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;

/** Figures behind the dashboards (legacy dashboard collectStats / graphicStats / collectOwnerStats). */
class DashboardStats
{
    /** @return array<string, float|int> */
    public function office(): array
    {
        $units = Unit::where('is_active', true);
        $total = (clone $units)->count();
        $rented = (clone $units)->where('status', UnitStatus::Rented)->count();
        $pending = Installment::where('is_collected', false)->where('type', '!=', 'expenses');

        return [
            'units' => $total,
            'rented' => $rented,
            'available' => (clone $units)->where('status', UnitStatus::Available)->count(),
            'occupancy' => $total > 0 ? round($rented / $total * 100, 1) : 0.0,
            'due_this_month' => $this->remaining((clone $pending)->whereBetween('due_date', [today()->startOfMonth(), today()->endOfMonth()])),
            'overdue' => $this->remaining((clone $pending)->whereDate('due_date', '<', today())),
            'collected_this_month' => round((float) Installment::where('is_collected', true)
                ->whereBetween('collected_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'), 2),
            'owners_payable' => $this->balance(OwnerTransaction::whereNotNull('owner_id')),
            'active_contracts' => RentContract::where('status', ContractStatus::Active)->count(),
        ];
    }

    /** @return array{collected: list<float>, due: list<float>} last $months months, oldest first (KPI sparklines) */
    public function recentMonths(int $months = 6): array
    {
        $from = today()->startOfMonth()->subMonths($months - 1);
        $collected = $due = [];
        for ($i = 0; $i < $months; $i++) {
            $start = $from->copy()->addMonths($i);
            $collected[] = round((float) Installment::where('is_collected', true)->whereBetween('collected_at', [$start, $start->copy()->endOfMonth()])->sum('amount'), 2);
            $due[] = round((float) Installment::where('type', '!=', 'expenses')->whereBetween('due_date', [$start, $start->copy()->endOfMonth()])->sum('amount'), 2);
        }

        return ['collected' => $collected, 'due' => $due];
    }

    /** @return array<string, int> unit status value => count (active units) */
    public function unitStatuses(): array
    {
        return Unit::where('is_active', true)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')
            ->mapWithKeys(fn ($c, $status) => [($status instanceof UnitStatus ? $status->value : (string) $status) => (int) $c])->all();
    }

    /** @return array{labels: list<string>, rate: list<float|null>} share of each month's dues that has been collected, last 12 months */
    public function collectionRate(int $months = 12): array
    {
        $from = today()->startOfMonth()->subMonths($months - 1);
        $labels = $rate = [];
        for ($i = 0; $i < $months; $i++) {
            $start = $from->copy()->addMonths($i);
            $dues = Installment::where('type', '!=', 'expenses')->whereBetween('due_date', [$start, $start->copy()->endOfMonth()]);
            $total = (float) (clone $dues)->sum('amount');
            $paid = (float) (clone $dues)->where('is_collected', true)->sum('amount') + (float) (clone $dues)->where('is_collected', false)->sum('paid_amount');
            $labels[] = $start->translatedFormat('M Y');
            $rate[] = $total > 0 ? round($paid / $total * 100, 1) : null;
        }

        return ['labels' => $labels, 'rate' => $rate];
    }

    /** @return array<string, float> building title => collected this year, highest first */
    public function topBuildings(int $limit = 6): array
    {
        return Installment::query()->where('installments.is_collected', true)->whereYear('installments.collected_at', now()->year)
            ->join('units', 'units.id', '=', 'installments.unit_id')->leftJoin('buildings', 'buildings.id', '=', 'units.building_id')
            ->selectRaw("coalesce(buildings.title, '".addslashes(__('وحدات مستقلة'))."') as name, sum(installments.amount) as total")
            ->groupBy('name')->orderByDesc('total')->limit($limit)->pluck('total', 'name')
            ->map(fn ($v) => round((float) $v, 2))->all();
    }

    /** @return array{expiring: int, overdue_count: int, open_tickets: int} */
    public function attention(): array
    {
        return [
            'expiring' => RentContract::where('status', ContractStatus::Active)->whereBetween('end_date', [today(), today()->addDays(60)])->count(),
            'overdue_count' => Installment::where('is_collected', false)->where('type', '!=', 'expenses')->whereDate('due_date', '<', today())->count(),
            'open_tickets' => \App\Models\SupportTicket::where('status', 'open')->count(),
        ];
    }

    /** @return array{labels: list<string>, collected: list<float>, due: list<float>} */
    public function monthlyCollections(int $year): array
    {
        // one SUM per month on indexed date ranges: loading every installment of the year was slow for large offices
        $collected = $due = [];
        foreach (range(1, 12) as $m) {
            $start = now()->setDate($year, $m, 1)->startOfDay();
            $end = $start->copy()->endOfMonth();
            $collected[$m] = (float) Installment::where('is_collected', true)->whereBetween('collected_at', [$start, $end])->sum('amount');
            $due[$m] = (float) Installment::where('type', '!=', 'expenses')->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])->sum('amount');
        }

        return [
            'labels' => array_map(fn ($m) => now()->setDate($year, $m, 1)->translatedFormat('M'), range(1, 12)),
            'collected' => array_values(array_map(fn ($v) => round($v, 2), $collected)),
            'due' => array_values(array_map(fn ($v) => round($v, 2), $due)),
        ];
    }

    /** @return array<string, float|int> */
    public function owner(Owner $owner): array
    {
        $units = Unit::whereHas('owners', fn (Builder $q) => $q->whereKey($owner->getKey()));

        return [
            'units' => (clone $units)->count(),
            'rented' => (clone $units)->where('status', UnitStatus::Rented)->count(),
            'balance' => $owner->outstandingBalance(),
            'received_this_year' => round((float) OwnerTransaction::where('owner_id', $owner->id)->where('direction', LedgerDirection::Debit)
                ->where('is_settled', true)->whereYear('settled_at', now()->year)->sum('amount'), 2),
        ];
    }

    /** @return array{next: ?Installment, outstanding: float, overdue: float, contracts: int} */
    public function client(Client $client): array
    {
        $mine = Installment::whereHasMorph('contract', [RentContract::class, SaleContract::class],
            fn (Builder $q) => $q->whereHas('clients', fn (Builder $q) => $q->whereKey($client->getKey())))
            ->where('is_collected', false);

        return [
            'next' => (clone $mine)->orderBy('due_date')->first(),
            'outstanding' => $this->remaining(clone $mine),
            'overdue' => $this->remaining((clone $mine)->whereDate('due_date', '<', today())),
            'contracts' => $client->rentContracts()->where('status', ContractStatus::Active)->count()
                + $client->saleContracts()->where('status', ContractStatus::Active)->count(),
        ];
    }

    private function remaining(Builder $installments): float
    {
        return round((float) $installments->sum('amount') - (float) $installments->sum('paid_amount'), 2);
    }

    private function balance(Builder $entries): float
    {
        $rows = (clone $entries)->where('is_settled', false)->selectRaw('direction, SUM(amount) as total')->groupBy('direction')->pluck('total', 'direction');

        return round((float) ($rows['debit'] ?? 0) - (float) ($rows['credit'] ?? 0), 2);
    }
}
