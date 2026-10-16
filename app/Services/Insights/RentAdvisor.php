<?php

namespace App\Services\Insights;

use App\Models\RentContract;
use App\Models\Unit;
use Illuminate\Support\Collection;

/**
 * A fair yearly rent for a unit from what comparable units in this office actually rented for —
 * same district, then city, then the whole office — with the range and how many contracts it rests on.
 */
class RentAdvisor
{
    /** Contracts older than this no longer say much about today's market. */
    public const LOOKBACK_MONTHS = 24;

    /** @return array{suggested: float, low: float, high: float, per_m2: ?float, sample: int, confidence: string, basis: string, current: ?float, gap_percent: ?float, comparables: list<array>}|null */
    public function forUnit(Unit $unit): ?array
    {
        if ($unit->offer_type === \App\Enums\OfferType::Sale) {
            return null; // offered for sale only: there is no rent to suggest
        }
        $market = $this->market($unit->getKey());
        if ($market->isEmpty()) {
            return null;
        }

        $area = (float) $unit->area;
        $rooms = (int) $unit->rooms ?: null; // shops and offices store 0
        // like for like: same category; flats (with rooms) against flats of ±1 room, shops/offices (no rooms) against
        // each other; and a comparable size (half to double), so a villa is never priced from apartments
        $similar = fn (Collection $rows) => $rows->filter(fn ($r) => $r['category'] === $unit->property_category_id
            && ($rooms === null ? $r['rooms'] === null : ($r['rooms'] !== null && abs($r['rooms'] - $rooms) <= 1))
            && ($area <= 0 || $r['area'] <= 0 || ($r['area'] >= $area / 2 && $r['area'] <= $area * 2)));
        $tiers = [
            'district' => $unit->district_id ? $similar($market->where('district', $unit->district_id)) : collect(),
            'city' => $unit->city_id ? $similar($market->where('city', $unit->city_id)) : collect(),
            'office' => $similar($market),
        ];
        $basis = collect($tiers)->keys()->first(fn ($k) => $tiers[$k]->count() >= 3)
            ?? collect($tiers)->sortByDesc(fn ($rows) => $rows->count())->keys()->first();
        $rows = $tiers[$basis];
        if ($rows->count() < 2) {
            return null; // one contract is an anecdote, not a market
        }

        $perM2Rows = $rows->filter(fn ($r) => $r['area'] > 0);
        if ($area > 0 && $perM2Rows->count() >= max(2, (int) ceil($rows->count() / 2))) {
            $rates = $perM2Rows->map(fn ($r) => $r['yearly'] / $r['area'])->sort()->values();
            $perM2 = self::quantile($rates, .5);
            [$suggested, $low, $high] = [$perM2 * $area, self::quantile($rates, .25) * $area, self::quantile($rates, .75) * $area];
        } else {
            $values = $rows->pluck('yearly')->sort()->values();
            $perM2 = null;
            [$suggested, $low, $high] = [self::quantile($values, .5), self::quantile($values, .25), self::quantile($values, .75)];
        }

        $current = $this->currentYearly($unit) ?? ((float) $unit->rent_amount ?: null);

        return [
            'suggested' => self::round100($suggested),
            'low' => self::round100($low),
            'high' => self::round100($high),
            'per_m2' => $perM2 ? round($perM2, 2) : null,
            'sample' => $rows->count(),
            'confidence' => $rows->count() >= 5 ? 'high' : ($rows->count() >= 3 ? 'medium' : 'low'),
            'basis' => $basis,
            'current' => $current,
            'gap_percent' => $current ? round(($current - $suggested) / $suggested * 100, 1) : null,
            'comparables' => $rows->sortByDesc('since')->take(5)->map(fn ($r) => ['unit' => $r['title'], 'yearly' => round($r['yearly'], 2), 'rooms' => $r['rooms'], 'area' => $r['area'] ?: null])->values()->all(),
        ];
    }

    public static function basisLabel(string $basis): string
    {
        return __(['district' => 'وحدات مماثلة في نفس الحي', 'city' => 'وحدات مماثلة في نفس المدينة', 'office' => 'وحدات مماثلة لدى المؤسسة'][$basis] ?? $basis);
    }

    /** Annual rent of every recent contract, one row per unit (its latest contract), excluding the unit asked about. */
    private function market(int $exceptUnitId): Collection
    {
        return RentContract::query()->with('unit:id,title,property_category_id,rooms,area,city_id,district_id')
            ->where('unit_id', '!=', $exceptUnitId)
            ->whereDate('start_date', '>=', now()->subMonths(self::LOOKBACK_MONTHS))
            ->whereNull('cancelled_at')
            ->latest('start_date')->get()
            ->unique('unit_id')
            ->map(fn (RentContract $c) => ['yearly' => $this->annualise($c), 'since' => $c->start_date, 'title' => $c->unit?->title,
                'category' => $c->unit?->property_category_id, 'rooms' => (int) $c->unit?->rooms ?: null, 'area' => (float) $c->unit?->area,
                'city' => $c->unit?->city_id, 'district' => $c->unit?->district_id])
            ->filter(fn ($r) => $r['yearly'] > 0)
            ->values();
    }

    private function currentYearly(Unit $unit): ?float
    {
        $contract = $unit->loadMissing('currentContract')->currentContract;

        return $contract instanceof RentContract ? ($this->annualise($contract) ?: null) : null;
    }

    /** The stated yearly rent, else the contract's rent instalments spread over its length. */
    public function annualise(RentContract $contract): float
    {
        if ((float) $contract->yearly_rent > 0) {
            return (float) $contract->yearly_rent;
        }
        $days = $contract->start_date && $contract->end_date ? max(1, $contract->start_date->diffInDays($contract->end_date) + 1) : 0;
        if ($days < 28) {
            return 0.0;
        }
        $total = (float) $contract->installments()->where('type', '!=', 'expenses')->sum('amount');

        return round($total / $days * 365, 2);
    }

    private static function quantile(Collection $sorted, float $q): float
    {
        $n = $sorted->count();
        if ($n === 1) {
            return (float) $sorted[0];
        }
        $pos = ($n - 1) * $q;
        $lo = (int) floor($pos);
        $hi = (int) ceil($pos);

        return (float) $sorted[$lo] + ((float) $sorted[$hi] - (float) $sorted[$lo]) * ($pos - $lo);
    }

    private static function round100(float $value): float
    {
        return round($value / 100) * 100;
    }
}
