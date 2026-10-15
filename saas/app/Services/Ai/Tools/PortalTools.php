<?php

namespace App\Services\Ai\Tools;

use App\Enums\ContractStatus;
use App\Models\Client;
use App\Models\Installment;
use App\Models\Owner;
use App\Models\OwnerTransaction;
use App\Models\RentContract;
use App\Models\SaleContract;
use App\Models\Unit;
use App\Models\User;
use App\Services\Reports\DashboardStats;
use Illuminate\Database\Eloquent\Builder;

/**
 * Owner and tenant portals: every query starts from the signed-in person's own record,
 * so the assistant can never reach another owner's or tenant's data whatever it is asked.
 */
final class PortalTools
{
    private const LIMIT = ['type' => 'integer', 'description' => 'Max rows (default 20, max 50)'];

    /** @return list<Tool> */
    public static function owner(): array
    {
        $me = fn (User $u): ?Owner => $u->owner;

        return [
            Tool::make('my_overview', 'The owner\'s headline figures: units, rented, balance owed to them, received this year, overdue rent on their units.',
                [], null, fn (array $in, User $u) => ($o = $me($u)) ? [
                    ...app(DashboardStats::class)->owner($o),
                    'overdue_on_my_units' => Fmt::money(self::ownerInstallments($o)->where('is_collected', false)->whereDate('due_date', '<', today())->get()->sum(fn ($i) => $i->remainingAmount())),
                    'today' => today()->toDateString(),
                ] : ['error' => 'no_owner']),

            Tool::make('my_units', 'The owner\'s units with status, share, rent, current tenant and contract end.',
                ['limit' => self::LIMIT], null, fn (array $in, User $u) => ($o = $me($u)) ? ['units' => $o->units()->with(['building:id,title', 'currentContract'])
                    ->limit(Fmt::limit($in))->get()->map(fn (Unit $unit) => [
                        'title' => $unit->title, 'building' => $unit->building?->title, 'status' => Fmt::label($unit->status),
                        'my_share_percent' => (float) $unit->pivot->share_percent, 'rent_amount' => $unit->rent_amount ? Fmt::money($unit->rent_amount) : null,
                        'contract' => $unit->currentContract instanceof RentContract ? [
                            'number' => $unit->currentContract->number, 'end' => Fmt::date($unit->currentContract->end_date),
                            'tenants' => $unit->currentContract->clients()->pluck('full_name')->all(),
                        ] : null,
                    ])->all()] : ['error' => 'no_owner']),

            Tool::make('my_collections', 'Rent on the owner\'s units: overdue, upcoming (next 60 days) or collected (this year).',
                ['status' => ['type' => 'string', 'enum' => ['overdue', 'upcoming', 'collected']], 'limit' => self::LIMIT], null,
                fn (array $in, User $u) => ($o = $me($u)) ? self::rows(self::ownerInstallments($o), $in) : ['error' => 'no_owner']),

            Tool::make('my_statement', 'The owner\'s account statement: latest entries (owed to owner = debit, deducted = credit), settled or not.',
                ['limit' => self::LIMIT], null, fn (array $in, User $u) => ($o = $me($u)) ? [
                    'unsettled_balance' => $o->outstandingBalance(),
                    'entries' => OwnerTransaction::with(['type:id,title', 'unit:id,title'])->where('owner_id', $o->id)
                        ->latest('due_date')->limit(Fmt::limit($in))->get()->map(fn ($t) => [
                            'date' => Fmt::date($t->due_date), 'type' => $t->type?->title, 'unit' => $t->unit?->title,
                            'direction' => $t->direction instanceof \BackedEnum ? $t->direction->value : $t->direction,
                            'amount' => Fmt::money($t->amount), 'settled' => (bool) $t->is_settled,
                        ])->all(),
                ] : ['error' => 'no_owner']),
        ];
    }

    /** @return list<Tool> */
    public static function client(): array
    {
        $me = fn (User $u): ?Client => $u->client;

        return [
            Tool::make('my_overview', 'The tenant\'s headline figures: active contracts, outstanding and overdue amounts, next payment.',
                [], null, function (array $in, User $u) use ($me) {
                    if (! $c = $me($u)) {
                        return ['error' => 'no_client'];
                    }
                    $s = app(DashboardStats::class)->client($c);

                    return [
                        'today' => today()->toDateString(), 'contracts' => $s['contracts'], 'outstanding' => $s['outstanding'], 'overdue' => $s['overdue'],
                        'next_payment' => $s['next'] ? ['due' => Fmt::date($s['next']->due_date), 'amount' => Fmt::money($s['next']->remainingAmount())] : null,
                    ];
                }),

            Tool::make('my_contracts', 'The tenant\'s rent and purchase contracts with unit, dates, rent and status.',
                [], null, fn (array $in, User $u) => ($c = $me($u)) ? [
                    'rent' => $c->rentContracts()->with('unit:id,title')->get()->map(fn (RentContract $r) => [
                        'number' => $r->number, 'unit' => $r->unit?->title, 'start' => Fmt::date($r->start_date), 'end' => Fmt::date($r->end_date),
                        'status' => Fmt::label($r->status), 'rent_per_payment' => Fmt::money($r->rent_amount), 'payment_cycle' => Fmt::label($r->payment_cycle),
                    ])->all(),
                    'purchase' => $c->saleContracts()->with('unit:id,title')->get()->map(fn (SaleContract $s) => [
                        'number' => $s->number, 'unit' => $s->unit?->title, 'date' => Fmt::date($s->contract_date),
                        'status' => Fmt::label($s->status), 'total' => Fmt::money($s->total_amount),
                    ])->all(),
                ] : ['error' => 'no_client']),

            Tool::make('my_payments', 'The tenant\'s installments: overdue, upcoming (next 60 days) or paid (this year).',
                ['status' => ['type' => 'string', 'enum' => ['overdue', 'upcoming', 'collected']], 'limit' => self::LIMIT], null,
                fn (array $in, User $u) => ($c = $me($u)) ? self::rows(Installment::whereHasMorph('contract', [RentContract::class, SaleContract::class],
                    fn (Builder $q) => $q->whereHas('clients', fn (Builder $q) => $q->whereKey($c->getKey()))), $in) : ['error' => 'no_client']),

            Tool::make('my_tickets', 'The tenant\'s support tickets and their status.',
                [], null, fn (array $in, User $u) => ($c = $me($u)) ? ['tickets' => $c->supportTickets()->latest()->limit(20)->get()->map(fn ($t) => [
                    'code' => $t->code, 'subject' => $t->subject, 'status' => Fmt::label($t->status), 'date' => Fmt::date($t->created_at),
                ])->all()] : ['error' => 'no_client']),
        ];
    }

    private static function ownerInstallments(Owner $owner): Builder
    {
        return Installment::query()->where('type', '!=', 'expenses')
            ->whereIn('unit_id', $owner->units()->select('units.id'));
    }

    private static function rows(Builder $query, array $in): array
    {
        $status = $in['status'] ?? 'upcoming';
        $query->with('unit:id,title');
        match ($status) {
            'collected' => $query->where('is_collected', true)->whereYear('collected_at', now()->year)->orderByDesc('collected_at'),
            'overdue' => $query->where('is_collected', false)->whereDate('due_date', '<', today())->orderBy('due_date'),
            default => $query->where('is_collected', false)->whereBetween('due_date', [today(), today()->addDays(60)])->orderBy('due_date'),
        };
        $rows = $query->limit(Fmt::limit($in))->get();

        return ['status' => $status, 'total' => Fmt::money($rows->sum(fn (Installment $i) => $status === 'collected' ? (float) $i->amount : $i->remainingAmount())),
            'rows' => $rows->map(fn (Installment $i) => [
                'due' => Fmt::date($i->due_date), 'unit' => $i->unit?->title, 'amount' => Fmt::money($i->amount),
                'remaining' => Fmt::money($i->remainingAmount()), 'collected_at' => Fmt::date($i->collected_at),
            ])->all()];
    }
}
