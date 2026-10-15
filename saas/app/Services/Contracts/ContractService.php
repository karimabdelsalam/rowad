<?php

namespace App\Services\Contracts;

use App\Enums\ContractStatus;
use App\Enums\InstallmentType;
use App\Enums\UnitStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\RentContract;
use App\Models\SaleContract;
use App\Models\Unit;
use App\Services\DocumentNumberGenerator;
use App\Services\Installments\InstallmentScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Contract lifecycle. Port of legacy rent/sell modules:
 * addrecord + rentContractSetup, checkAvailability, updateBuildContract,
 * markAsEnded, markAsCancelled, markArhcive.
 */
class ContractService
{
    public function __construct(
        private readonly InstallmentScheduler $scheduler,
        private readonly DocumentNumberGenerator $numbers,
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, float>  $parties  client_id => share_percent
     * @param  list<int>  $termIds
     */
    public function createRent(array $attributes, array $parties, array $termIds = []): RentContract
    {
        $this->assertParties($parties);
        $this->assertUnitAvailable((int) $attributes['unit_id'], $attributes['start_date'], $attributes['end_date']);

        return DB::transaction(function () use ($attributes, $parties, $termIds) {
            $contract = RentContract::create($attributes + [
                'number' => $this->numbers->next('rent_contract'),
                'status' => ContractStatus::Active,
                'created_by' => auth()->id(),
            ]);
            $this->syncParties($contract, $parties, $termIds);
            $this->scheduler->schedule($contract);
            $this->refreshNextPayment($contract);
            $this->occupyUnitIfStarted($contract);

            return $contract->refresh();
        });
    }

    /** Edit terms and regenerate the schedule (refused once money was collected). */
    public function updateRent(RentContract $contract, array $attributes, array $parties, array $termIds = []): RentContract
    {
        $this->assertParties($parties);
        $this->scheduler->ensureEditable($contract);
        $this->assertUnitAvailable((int) ($attributes['unit_id'] ?? $contract->unit_id),
            $attributes['start_date'] ?? $contract->start_date, $attributes['end_date'] ?? $contract->end_date, $contract->id);

        return DB::transaction(function () use ($contract, $attributes, $parties, $termIds) {
            $contract->update($attributes);
            $this->syncParties($contract, $parties, $termIds);
            $this->scheduler->schedule($contract);
            $this->refreshNextPayment($contract);
            $this->occupyUnitIfStarted($contract);

            return $contract->refresh();
        });
    }

    /** Legacy markAsEnded: only when every installment has been collected. */
    public function end(RentContract $contract): void
    {
        if ($contract->installments()->where('is_collected', false)->exists()) {
            throw new BusinessRuleException(__('هذا العقد مرتبط به بعض المبالغ الغير مرحلة'));
        }
        DB::transaction(function () use ($contract) {
            $contract->update(['status' => ContractStatus::Ended, 'ended_at' => now()]);
            $this->releaseUnit($contract);
        });
    }

    /** Legacy markAsCancelled: drop unpaid installments (expenses are kept). */
    public function cancel(RentContract $contract): void
    {
        DB::transaction(function () use ($contract) {
            $contract->installments()->where('is_collected', false)->where('type', '!=', InstallmentType::Expenses)->delete();
            $contract->update(['status' => ContractStatus::Cancelled, 'cancelled_at' => now()]);
            $this->releaseUnit($contract);
        });
    }

    public function archive(RentContract|SaleContract $contract, bool $archived = true): void
    {
        $contract->update(['archived_at' => $archived ? now() : null]);
    }

    /**
     * Mark units as rented when a future contract's start month arrives
     * (legacy updateBuildContract, run daily by the scheduler).
     */
    public function activateStartedContracts(): int
    {
        $count = 0;
        RentContract::query()
            ->where('status', ContractStatus::Active)
            ->whereDate('start_date', '<=', today()->endOfMonth())
            ->whereDate('end_date', '>=', today())
            ->whereHas('unit', fn ($q) => $q->where(fn ($q) => $q->whereNull('current_contract_id')
                ->orWhere('current_contract_type', '!=', 'rent_contract')
                ->orWhereColumn('current_contract_id', '!=', 'rent_contracts.id')))
            ->each(function (RentContract $contract) use (&$count) {
                $this->occupyUnitIfStarted($contract);
                $count++;
            });

        return $count;
    }

    /** @param array<int, float> $parties client_id => share_percent */
    public function createSale(array $attributes, array $parties, array $termIds = []): SaleContract
    {
        $this->assertParties($parties);
        $unit = Unit::findOrFail($attributes['unit_id']);
        if ($unit->status === UnitStatus::Sold) {
            throw new BusinessRuleException(__('الوحدة مباعة مسبقًا'));
        }

        return DB::transaction(function () use ($attributes, $parties, $termIds, $unit) {
            $contract = SaleContract::create($attributes + [
                'number' => $this->numbers->next('sale_contract'),
                'status' => ContractStatus::Active,
                'created_by' => auth()->id(),
            ]);
            $this->syncParties($contract, $parties, $termIds);
            $this->scheduler->schedule($contract);
            $unit->forceFill(['status' => UnitStatus::Sold])->currentContract()->associate($contract)->save();

            return $contract->refresh();
        });
    }

    public function updateSale(SaleContract $contract, array $attributes, array $parties, array $termIds = []): SaleContract
    {
        $this->assertParties($parties);
        $this->scheduler->ensureEditable($contract);

        return DB::transaction(function () use ($contract, $attributes, $parties, $termIds) {
            $contract->update($attributes);
            $this->syncParties($contract, $parties, $termIds);
            $this->scheduler->schedule($contract);

            return $contract->refresh();
        });
    }

    private function assertUnitAvailable(int $unitId, mixed $start, mixed $end, ?int $exceptContractId = null): void
    {
        $start = CarbonImmutable::parse($start)->toDateString();
        $end = CarbonImmutable::parse($end)->toDateString();
        if ($end < $start) {
            throw new BusinessRuleException(__('تاريخ نهاية العقد قبل تاريخ بدايته'));
        }

        $overlap = RentContract::where('unit_id', $unitId)
            ->where('status', ContractStatus::Active)
            ->when($exceptContractId, fn ($q) => $q->whereKeyNot($exceptContractId))
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->exists();

        if ($overlap) {
            throw new BusinessRuleException(__('الوحدة مؤجرة بعقد ساري خلال هذه الفترة'));
        }
    }

    /** @param array<int, float> $parties */
    private function assertParties(array $parties): void
    {
        if ($parties === []) {
            throw new BusinessRuleException(__('يجب اختيار طرف واحد على الأقل في العقد'));
        }
        if (abs(array_sum($parties) - 100) > 0.01) {
            throw new BusinessRuleException(__('مجموع نسب أطراف العقد يجب أن يساوي 100%'));
        }
    }

    private function syncParties(RentContract|SaleContract $contract, array $parties, array $termIds): void
    {
        $contract->clients()->sync(collect($parties)->mapWithKeys(fn ($share, $clientId) => [$clientId => ['share_percent' => $share]])->all());
        $contract->terms()->sync($termIds);
    }

    private function refreshNextPayment(RentContract $contract): void
    {
        $next = $contract->installments()->where('is_collected', false)->orderBy('due_date')->first();
        $contract->update(['next_payment_date' => $next?->due_date, 'next_payment_amount' => $next?->amount]);
    }

    private function occupyUnitIfStarted(RentContract $contract): void
    {
        if (CarbonImmutable::parse($contract->start_date)->greaterThan(today()->endOfMonth())) {
            return; // future contract, activated later by activateStartedContracts()
        }
        $contract->unit->forceFill(['status' => UnitStatus::Rented])->currentContract()->associate($contract)->save();
    }

    private function releaseUnit(RentContract $contract): void
    {
        $unit = $contract->unit;
        if ($unit->current_contract_type === $contract->getMorphClass() && (int) $unit->current_contract_id === $contract->id) {
            $unit->forceFill(['status' => UnitStatus::Available, 'current_contract_type' => null, 'current_contract_id' => null])->save();
        }
    }
}
