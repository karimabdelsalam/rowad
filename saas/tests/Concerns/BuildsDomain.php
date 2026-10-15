<?php

namespace Tests\Concerns;

use App\Models\Client;
use App\Models\Owner;
use App\Models\RentContract;
use App\Models\SaleContract;
use App\Models\Unit;

trait BuildsDomain
{
    /** @param list<array{0: float, 1: float}> $owners [share_percent, expenses_percent] */
    protected function makeUnit(array $owners = [[100, 100]], array $attributes = []): Unit
    {
        $unit = Unit::create($attributes + ['title' => 'شقة 101', 'offer_type' => 'rent']);
        foreach ($owners as $i => [$share, $expenses]) {
            $owner = Owner::create(['first_name' => 'مالك', 'family_name' => (string) ($i + 1)]);
            $unit->owners()->attach($owner, ['share_percent' => $share, 'expenses_percent' => $expenses]);
        }

        return $unit;
    }

    protected function makeClient(): Client
    {
        return Client::create(['first_name' => 'عميل', 'family_name' => 'تجريبي']);
    }

    protected function makeRentContract(array $attributes = [], ?Unit $unit = null): RentContract
    {
        $contract = RentContract::create($attributes + [
            'unit_id' => ($unit ?? $this->makeUnit())->id,
            'calendar' => 'gregorian',
            'contract_date' => '2026-01-01',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'period_unit' => 'year',
            'period_count' => 1,
            'payment_cycle' => 'month',
            'rent_amount' => 1000,
        ]);
        $contract->clients()->attach($this->makeClient(), ['share_percent' => 100]);

        return $contract;
    }

    protected function makeSaleContract(array $attributes, ?Unit $unit = null): SaleContract
    {
        return SaleContract::create($attributes + [
            'unit_id' => ($unit ?? $this->makeUnit())->id,
            'calendar' => 'gregorian',
            'contract_date' => '2026-03-01',
        ]);
    }
}
