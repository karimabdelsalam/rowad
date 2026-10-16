<?php

namespace App\Services\Insights;

use App\Enums\ContractStatus;
use App\Models\Installment;
use App\Models\Owner;
use App\Models\OwnerTransaction;
use App\Models\RentContract;
use App\Models\Unit;
use App\Support\Portal;
use Carbon\CarbonImmutable;

/**
 * An owner's portfolio as an investor sees it: what each property brought in this year after the
 * office's deductions, how many days it was let, what is still owed, and the yield on its value.
 */
class OwnerPerformance
{
    /** Ledger kinds taken off the owner's gross share. */
    public const DEDUCTIONS = ['tax', 'commission', 'owner_tax', 'mancost', 'outgoings'];

    /** @return array{year: int, units: list<array>, totals: array<string, float|int|null>} */
    public function forOwner(Owner $owner, ?int $year = null): array
    {
        $year ??= (int) now()->year;
        $from = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $to = $year === (int) now()->year ? CarbonImmutable::today() : $from->endOfYear()->startOfDay();
        $days = max(1, (int) $from->diffInDays($to) + 1);

        $units = Portal::ownerUnits($owner)->with(['owners' => fn ($q) => $q->whereKey($owner->getKey())])->orderBy('title')->get();
        $entries = OwnerTransaction::query()->with('type:id,code')->where('owner_id', $owner->id)
            ->whereIn('unit_id', $units->modelKeys())->whereBetween('due_date', [$from, $to])
            ->get(['id', 'unit_id', 'transaction_type_id', 'amount']);

        $rows = $units->map(function (Unit $unit) use ($entries, $from, $to, $days, $year) {
            $share = (float) ($unit->owners->first()?->pivot->share_percent ?? 100);
            $mine = $entries->where('unit_id', $unit->id);
            $gross = round((float) $mine->filter(fn ($e) => $e->type?->code === 'payment')->sum('amount'), 2);
            $deductions = round((float) $mine->filter(fn ($e) => in_array($e->type?->code, self::DEDUCTIONS, true))->sum('amount'), 2);
            $net = round($gross - $deductions, 2);
            $occupied = $this->occupiedDays($unit, $from, $to);
            $arrears = round((float) Installment::query()->where('unit_id', $unit->id)->where('is_collected', false)
                ->where('type', '!=', 'expenses')->whereDate('due_date', '<=', $to)->get(['amount', 'paid_amount'])
                ->sum(fn ($i) => (float) $i->amount - (float) $i->paid_amount) * $share / 100, 2);
            $value = (float) $unit->market_value * $share / 100;
            $annualNet = $net / $days * 365;
            $advice = app(RentAdvisor::class)->forUnit($unit);

            return [
                'unit' => $unit,
                'share' => $share,
                'gross' => $gross,
                'deductions' => $deductions,
                'net' => $net,
                'occupancy' => round($occupied / $days * 100, 1),
                'vacant_days' => $days - $occupied,
                'arrears' => $arrears,
                'value' => $value > 0 ? round($value, 2) : null,
                'yield' => $value > 0 ? round($annualNet / $value * 100, 2) : null,
                'market_gap' => $advice['gap_percent'] ?? null,
                'year' => $year,
            ];
        })->values();

        $value = (float) $rows->sum(fn ($r) => $r['value'] ?? 0);
        $net = round((float) $rows->sum('net'), 2);

        return [
            'year' => $year,
            'units' => $rows->all(),
            'totals' => [
                'gross' => round((float) $rows->sum('gross'), 2),
                'deductions' => round((float) $rows->sum('deductions'), 2),
                'net' => $net,
                'arrears' => round((float) $rows->sum('arrears'), 2),
                'occupancy' => $rows->isEmpty() ? 0.0 : round((float) $rows->avg('occupancy'), 1),
                'yield' => $value > 0 ? round($net / $days * 365 / $value * 100, 2) : null,
                'units' => $rows->count(),
            ],
        ];
    }

    /** Days in the window covered by a rent contract that was not cancelled. */
    private function occupiedDays(Unit $unit, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $covered = [];
        RentContract::query()->where('unit_id', $unit->id)->whereNull('cancelled_at')
            ->where('status', '!=', ContractStatus::Cancelled)
            ->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)
            ->get(['start_date', 'end_date', 'ended_at'])
            ->each(function (RentContract $c) use (&$covered, $from, $to) {
                $start = CarbonImmutable::parse(max($c->start_date->toDateString(), $from->toDateString()));
                $endDate = $c->ended_at && $c->ended_at->lt($c->end_date) ? $c->ended_at : $c->end_date;
                $end = CarbonImmutable::parse(min($endDate->toDateString(), $to->toDateString()));
                for ($d = $start; $d->lte($end); $d = $d->addDay()) {
                    $covered[$d->toDateString()] = true;
                }
            });

        return count($covered);
    }
}
