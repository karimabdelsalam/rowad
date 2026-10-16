<?php

namespace App\Services\Ai\Tools;

use App\Enums\ContractStatus;
use App\Enums\UnitStatus;
use App\Models\Building;
use App\Models\Client;
use App\Models\Installment;
use App\Models\Owner;
use App\Models\OwnerTransaction;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\RentContract;
use App\Models\SaleContract;
use App\Models\SupportTicket;
use App\Models\Unit;
use App\Services\Reports\DashboardStats;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** What office staff can ask about; every tool checks the same permission as the screen showing that data. */
final class StaffTools
{
    private const LIMIT = ['type' => 'integer', 'description' => 'Max rows (default 20, max 50)'];

    private const PERIOD = [
        'from' => ['type' => 'string', 'description' => 'Start date YYYY-MM-DD (default: start of this year)'],
        'to' => ['type' => 'string', 'description' => 'End date YYYY-MM-DD (default: today)'],
    ];

    /** @return list<Tool> */
    public static function all(): array
    {
        return [
            Tool::make('office_overview', 'Headline figures of the office today: units, occupancy, available units, rent due and collected this month, overdue amount, owners payable, active contracts, contracts expiring within 60 days, open support tickets, counts of owners/clients/buildings.',
                [], null, fn () => self::overview()),

            Tool::make('monthly_collections', 'Rent due vs collected for each month of a year.',
                ['year' => ['type' => 'integer', 'description' => 'Year, default current']], 'installments.view_any',
                fn (array $in) => app(DashboardStats::class)->monthlyCollections((int) ($in['year'] ?? now()->year))),

            Tool::make('collection_rate', 'Share of each month\'s dues that was collected, last 12 months (percent).',
                [], 'installments.view_any', fn () => app(DashboardStats::class)->collectionRate()),

            Tool::make('search_units', 'Find units by text (title, number, building, address) and/or status. Returns rent, area, owners and current tenant.',
                [
                    'query' => ['type' => 'string'],
                    'status' => ['type' => 'string', 'enum' => array_map(fn ($c) => $c->value, UnitStatus::cases())],
                    'offer_type' => ['type' => 'string', 'enum' => ['rent', 'sale']],
                    'limit' => self::LIMIT,
                ], 'units.view_any', fn (array $in) => self::units($in)),

            Tool::make('unit_details', 'Everything about one unit: data, owners and shares, current contract and its payments, totals collected/outstanding.',
                ['unit_id' => ['type' => 'integer']], 'units.view', fn (array $in) => self::unit((int) ($in['unit_id'] ?? 0))),

            Tool::make('search_rent_contracts', 'Find rent contracts by number, unit or tenant name, status, or those ending within N days.',
                [
                    'query' => ['type' => 'string'],
                    'status' => ['type' => 'string', 'enum' => array_map(fn ($c) => $c->value, ContractStatus::cases())],
                    'ending_within_days' => ['type' => 'integer'],
                    'limit' => self::LIMIT,
                ], 'rent_contracts.view_any', fn (array $in) => self::contracts($in)),

            Tool::make('rent_contract_details', 'One rent contract with its tenants and every installment (due, paid, remaining).',
                ['contract_id' => ['type' => 'integer'], 'number' => ['type' => 'string']], 'rent_contracts.view',
                fn (array $in) => self::contract($in)),

            Tool::make('installments', 'Rent/sale installments: overdue (unpaid and past due), upcoming (unpaid, due in the period) or collected (in the period). Includes totals.',
                ['status' => ['type' => 'string', 'enum' => ['overdue', 'upcoming', 'collected']], ...self::PERIOD, 'limit' => self::LIMIT],
                'installments.view_any', fn (array $in) => self::installments($in)),

            Tool::make('search_people', 'Find owners or clients (tenants/buyers) by name, mobile or ID number. Owners include their unsettled balance; clients include outstanding and overdue amounts.',
                ['type' => ['type' => 'string', 'enum' => ['owner', 'client']], 'query' => ['type' => 'string'], 'limit' => self::LIMIT],
                null, fn (array $in, $user) => self::people($in, $user)),

            Tool::make('owner_balances', 'Owners with unsettled balances (owed to them), highest first.',
                ['limit' => self::LIMIT], 'owner_transactions.view_any', fn (array $in) => self::ownerBalances($in)),

            Tool::make('finance_summary', 'Money in (receipt vouchers) and out (payment vouchers) in a period, expenses by type, net, and VAT.',
                self::PERIOD, 'reports.profits', fn (array $in) => self::finance($in)),

            Tool::make('buildings_performance', 'Each building: units, rented, occupancy %, rent collected in the period, overdue now.',
                [...self::PERIOD, 'limit' => self::LIMIT], 'reports.units', fn (array $in) => self::buildings($in)),

            Tool::make('support_tickets', 'Support tickets from owners and tenants, newest first.',
                ['status' => ['type' => 'string', 'enum' => ['open', 'answered', 'closed']], 'limit' => self::LIMIT],
                'support_tickets.view_any', fn (array $in) => self::tickets($in)),
            Tool::make('payment_risk', 'Late-payment risk of tenants from their payment history with this office: score 0-100, level, reasons and stats. Without client_id returns the tenants of active contracts most at risk; with client_id returns that tenant.',
                ['client_id' => ['type' => 'integer'], 'limit' => self::LIMIT],
                'clients.view_any', fn (array $in) => self::paymentRisk($in)),
            Tool::make('suggest_rent', 'Suggested fair yearly rent for a unit from recent contracts of comparable units in this office (same category, rooms ±1, similar size; district, then city, then office), with the 25-75% range, sample size, confidence and how the current rent compares. Find the unit id with search_units first.',
                ['unit_id' => ['type' => 'integer']],
                'units.view', fn (array $in) => self::suggestRent($in)),
        ];
    }

    private static function paymentRisk(array $in): array
    {
        $risk = app(\App\Services\Insights\PaymentRisk::class);
        if (! empty($in['client_id'])) {
            $client = \App\Models\Client::find((int) $in['client_id']);

            return $client ? ['client' => $client->displayName()] + $risk->forClient($client) : ['error' => 'client_not_found'];
        }

        return ['tenants' => array_map(fn (array $r) => ['client_id' => $r['client']->id, 'name' => $r['client']->displayName()]
            + array_diff_key($r, ['client' => 1]), $risk->atRisk(Fmt::limit($in, 10, 30)))];
    }

    private static function suggestRent(array $in): array
    {
        $unit = \App\Models\Unit::find((int) ($in['unit_id'] ?? 0));
        if ($unit === null) {
            return ['error' => 'unit_not_found'];
        }
        $advice = app(\App\Services\Insights\RentAdvisor::class)->forUnit($unit);

        return ['unit' => $unit->title] + ($advice ?? ['suggested' => null, 'reason' => 'not enough recent contracts of comparable units']);
    }

    private static function overview(): array
    {
        $stats = app(DashboardStats::class);

        return [
            'today' => today()->toDateString(),
            ...$stats->office(),
            ...$stats->attention(),
            'sale_contracts_active' => SaleContract::where('status', ContractStatus::Active)->count(),
            'owners' => Owner::count(),
            'clients' => Client::count(),
            'buildings' => Building::count(),
        ];
    }

    private static function units(array $in): array
    {
        $rows = Unit::query()->with(['building:id,title', 'owners:id,full_name', 'currentContract'])
            ->when($in['query'] ?? null, fn (Builder $q, $t) => $q->where(fn (Builder $q) => $q
                ->where('title', 'like', "%{$t}%")->orWhere('number', 'like', "%{$t}%")->orWhere('address', 'like', "%{$t}%")
                ->orWhereHas('building', fn (Builder $q) => $q->where('title', 'like', "%{$t}%"))))
            ->when($in['status'] ?? null, fn (Builder $q, $s) => $q->where('status', $s))
            ->when($in['offer_type'] ?? null, fn (Builder $q, $s) => $q->where('offer_type', $s))
            ->where('is_active', true)->orderBy('title')->limit(Fmt::limit($in))->get();

        return ['count_matching' => $rows->count(), 'units' => $rows->map(fn (Unit $u) => [
            'id' => $u->id, 'title' => $u->title, 'number' => $u->number, 'building' => $u->building?->title,
            'status' => Fmt::label($u->status), 'offer' => Fmt::label($u->offer_type), 'area_m2' => $u->area ? (float) $u->area : null,
            'rent_amount' => $u->rent_amount ? Fmt::money($u->rent_amount) : null,
            'owners' => $u->owners->pluck('full_name')->all(),
            'current_contract' => $u->currentContract ? ['number' => $u->currentContract->number, 'ends' => Fmt::date($u->currentContract->end_date ?? null)] : null,
        ])->all()];
    }

    private static function unit(int $id): array
    {
        $unit = Unit::with(['building:id,title', 'owners:id,full_name,mobile', 'currentContract'])->find($id);
        if ($unit === null) {
            return ['error' => 'not_found'];
        }
        $installments = Installment::where('unit_id', $unit->id)->where('type', '!=', 'expenses');

        return [
            'id' => $unit->id, 'title' => $unit->title, 'number' => $unit->number, 'floor' => $unit->floor, 'building' => $unit->building?->title,
            'status' => Fmt::label($unit->status), 'offer' => Fmt::label($unit->offer_type), 'area_m2' => $unit->area ? (float) $unit->area : null,
            'rent_amount' => $unit->rent_amount ? Fmt::money($unit->rent_amount) : null, 'address' => $unit->address,
            'owners' => $unit->owners->map(fn ($o) => ['name' => $o->full_name, 'share_percent' => (float) $o->pivot->share_percent])->all(),
            'current_contract' => $unit->currentContract instanceof RentContract ? self::contractRow($unit->currentContract->load('clients:id,full_name')) : null,
            'collected_total' => Fmt::money((clone $installments)->where('is_collected', true)->sum('amount')),
            'outstanding_total' => Fmt::money((float) (clone $installments)->where('is_collected', false)->sum('amount') - (float) (clone $installments)->where('is_collected', false)->sum('paid_amount')),
            'overdue_total' => Fmt::money((float) (clone $installments)->where('is_collected', false)->whereDate('due_date', '<', today())->sum(DB::raw('amount - paid_amount'))),
            'contracts_history' => $unit->rentContracts()->latest('start_date')->limit(10)->get()->map(fn ($c) => [
                'number' => $c->number, 'start' => Fmt::date($c->start_date), 'end' => Fmt::date($c->end_date), 'status' => Fmt::label($c->status), 'rent' => Fmt::money($c->rent_amount),
            ])->all(),
        ];
    }

    private static function contractRow(RentContract $c): array
    {
        return [
            'id' => $c->id, 'number' => $c->number, 'unit' => $c->unit?->title, 'tenants' => $c->clients->pluck('full_name')->all(),
            'start' => Fmt::date($c->start_date), 'end' => Fmt::date($c->end_date), 'status' => Fmt::label($c->status),
            'rent_per_payment' => Fmt::money($c->rent_amount), 'payment_cycle' => Fmt::label($c->payment_cycle),
            'yearly_rent' => $c->yearly_rent ? Fmt::money($c->yearly_rent) : null,
            'next_payment' => $c->next_payment_date ? ['date' => Fmt::date($c->next_payment_date), 'amount' => Fmt::money($c->next_payment_amount)] : null,
        ];
    }

    private static function contracts(array $in): array
    {
        $rows = RentContract::query()->with(['unit:id,title', 'clients:id,full_name'])
            ->when($in['query'] ?? null, fn (Builder $q, $t) => $q->where(fn (Builder $q) => $q->where('number', 'like', "%{$t}%")
                ->orWhereHas('unit', fn (Builder $q) => $q->where('title', 'like', "%{$t}%"))
                ->orWhereHas('clients', fn (Builder $q) => $q->where('full_name', 'like', "%{$t}%"))))
            ->when($in['status'] ?? null, fn (Builder $q, $s) => $q->where('status', $s))
            ->when($in['ending_within_days'] ?? null, fn (Builder $q, $d) => $q->where('status', ContractStatus::Active)
                ->whereBetween('end_date', [today(), today()->addDays((int) $d)]))
            ->orderBy('end_date')->limit(Fmt::limit($in))->get();

        return ['count' => $rows->count(), 'contracts' => $rows->map(fn ($c) => self::contractRow($c))->all()];
    }

    private static function contract(array $in): array
    {
        $contract = RentContract::with(['unit:id,title', 'clients:id,full_name,mobile'])
            ->when($in['contract_id'] ?? null, fn ($q, $id) => $q->whereKey($id), fn ($q) => $q->where('number', (string) ($in['number'] ?? '')))
            ->first();
        if ($contract === null) {
            return ['error' => 'not_found'];
        }

        return [...self::contractRow($contract), 'installments' => $contract->installments()->orderBy('due_date')->get()->map(fn (Installment $i) => [
            'due' => Fmt::date($i->due_date), 'type' => Fmt::label($i->type), 'amount' => Fmt::money($i->amount),
            'paid' => Fmt::money($i->paidTotal()), 'remaining' => Fmt::money($i->remainingAmount()), 'collected' => $i->is_collected,
        ])->all()];
    }

    private static function installments(array $in): array
    {
        [$from, $to] = Fmt::period($in);
        $status = $in['status'] ?? 'overdue';
        $query = Installment::query()->with(['unit:id,title', 'contract'])->where('type', '!=', 'expenses');
        match ($status) {
            'collected' => $query->where('is_collected', true)->whereBetween('collected_at', [$from, $to])->orderByDesc('collected_at'),
            'upcoming' => $query->where('is_collected', false)->whereBetween('due_date', [max($from, today()), isset($in['to']) ? $to : today()->addDays(30)])->orderBy('due_date'),
            default => $query->where('is_collected', false)->whereDate('due_date', '<', today())->orderBy('due_date'),
        };
        $total = (clone $query)->reorder();
        $rows = $query->limit(Fmt::limit($in))->get();

        return [
            'status' => $status,
            'count' => (clone $total)->count(),
            'total_amount' => Fmt::money((clone $total)->sum('amount')),
            'total_remaining' => $status === 'collected' ? 0 : Fmt::money((float) (clone $total)->sum('amount') - (float) (clone $total)->sum('paid_amount')),
            'rows' => $rows->map(fn (Installment $i) => [
                'due' => Fmt::date($i->due_date), 'unit' => $i->unit?->title, 'contract' => $i->contract?->number,
                'tenants' => $i->contract && method_exists($i->contract, 'clients') ? $i->contract->clients()->pluck('full_name')->all() : [],
                'amount' => Fmt::money($i->amount), 'remaining' => Fmt::money($i->remainingAmount()),
                'days_late' => ! $i->is_collected && $i->due_date->isPast() ? (int) $i->due_date->diffInDays(today()) : 0,
                'collected_at' => Fmt::date($i->collected_at),
            ])->all(),
        ];
    }

    private static function people(array $in, $user): array
    {
        $type = ($in['type'] ?? 'client') === 'owner' ? 'owner' : 'client';
        if (! $user->is_super_admin && ! $user->can($type.'s.view_any')) {
            return ['error' => 'not_permitted'];
        }
        $model = $type === 'owner' ? Owner::query() : Client::query();
        $rows = $model->when($in['query'] ?? null, fn (Builder $q, $t) => $q->where(fn (Builder $q) => $q
            ->where('full_name', 'like', "%{$t}%")->orWhere('mobile', 'like', "%{$t}%")->orWhere('id_number', 'like', "%{$t}%")))
            ->orderBy('full_name')->limit(Fmt::limit($in))->get();
        $stats = app(DashboardStats::class);

        return [$type.'s' => $rows->map(fn ($p) => [
            'id' => $p->id, 'name' => $p->full_name, 'mobile' => $p->mobile, 'email' => $p->email,
            ...($type === 'owner'
                ? ['units' => $p->units()->count(), 'unsettled_balance' => $p->outstandingBalance()]
                : collect($stats->client($p))->except('next')->all()),
        ])->all()];
    }

    private static function ownerBalances(array $in): array
    {
        $rows = OwnerTransaction::query()->whereNotNull('owner_id')->where('is_settled', false)
            ->selectRaw("owner_id, SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END) as balance")
            ->groupBy('owner_id')->orderByDesc('balance')->limit(Fmt::limit($in))->get();
        $names = Owner::whereIn('id', $rows->pluck('owner_id'))->pluck('full_name', 'id');

        return ['owners' => $rows->map(fn ($r) => ['owner' => $names[$r->owner_id] ?? null, 'balance' => Fmt::money($r->balance)])->all()];
    }

    private static function finance(array $in): array
    {
        [$from, $to] = Fmt::period($in);
        $in_ = ReceiptVoucher::whereBetween('issued_at', [$from->toDateString(), $to->toDateString()]);
        $out = PaymentVoucher::whereBetween('issued_at', [$from->toDateString(), $to->toDateString()]);
        $received = (float) (clone $in_)->sum('amount');
        $paid = (float) (clone $out)->sum('amount');

        return [
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'received' => Fmt::money($received), 'receipts_count' => (clone $in_)->count(),
            'paid_out' => Fmt::money($paid), 'payments_count' => (clone $out)->count(),
            'net' => Fmt::money($received - $paid),
            'vat_collected' => Fmt::money((clone $in_)->sum('vat_amount')), 'vat_paid' => Fmt::money((clone $out)->sum('vat_amount')),
            'expenses_by_type' => (clone $out)->leftJoin('expense_types', 'expense_types.id', '=', 'payment_vouchers.expense_type_id')
                ->selectRaw("coalesce(expense_types.title, '-') as type, SUM(payment_vouchers.amount) as total")
                ->groupBy('type')->orderByDesc('total')->pluck('total', 'type')->map(fn ($v) => Fmt::money($v))->all(),
            'received_by_month' => (clone $in_)->get(['issued_at', 'amount'])->groupBy(fn ($v) => $v->issued_at->format('Y-m'))
                ->map(fn ($g) => Fmt::money($g->sum('amount')))->all(),
        ];
    }

    private static function buildings(array $in): array
    {
        [$from, $to] = Fmt::period($in);

        return ['buildings' => Building::query()->withCount([
            'units as active_units' => fn ($q) => $q->where('is_active', true),
            'units as rented_units' => fn ($q) => $q->where('is_active', true)->where('status', UnitStatus::Rented),
        ])->orderByDesc('active_units')->limit(Fmt::limit($in))->get()->map(function (Building $b) use ($from, $to) {
            $inst = Installment::whereIn('unit_id', $b->units()->select('id'))->where('type', '!=', 'expenses');

            return [
                'building' => $b->title, 'units' => $b->active_units, 'rented' => $b->rented_units,
                'occupancy_percent' => $b->active_units ? round($b->rented_units / $b->active_units * 100, 1) : 0,
                'collected_in_period' => Fmt::money((clone $inst)->where('is_collected', true)->whereBetween('collected_at', [$from, $to])->sum('amount')),
                'overdue_now' => Fmt::money((clone $inst)->where('is_collected', false)->whereDate('due_date', '<', today())->sum(DB::raw('amount - paid_amount'))),
            ];
        })->all()];
    }

    private static function tickets(array $in): array
    {
        return ['tickets' => SupportTicket::with(['owner:id,full_name', 'client:id,full_name', 'unit:id,title'])
            ->when($in['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->latest()->limit(Fmt::limit($in))->get()->map(fn (SupportTicket $t) => [
                'code' => $t->code, 'subject' => $t->subject, 'type' => Fmt::label($t->type), 'status' => Fmt::label($t->status),
                'from' => $t->owner?->full_name ?? $t->client?->full_name, 'unit' => $t->unit?->title, 'date' => Fmt::date($t->created_at),
            ])->all()];
    }
}
