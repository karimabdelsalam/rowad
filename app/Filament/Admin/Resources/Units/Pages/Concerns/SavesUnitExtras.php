<?php

namespace App\Filament\Admin\Resources\Units\Pages\Concerns;

use App\Exceptions\BusinessRuleException;
use App\Models\Unit;
use App\Services\Units\UnitOwnership;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\DB;

/** Owners and custom fields are stored outside the units table. */
trait SavesUnitExtras
{
    /** @var array{owners: array, custom: array} */
    protected array $unitExtras = ['owners' => [], 'custom' => []];

    protected function pullUnitExtras(array $data): array
    {
        $this->unitExtras = ['owners' => $data['owner_shares'] ?? [], 'custom' => $data['custom_fields'] ?? []];
        unset($data['owner_shares'], $data['custom_fields']);

        return $data;
    }

    protected function saveUnit(callable $persist): Unit
    {
        try {
            return DB::transaction(function () use ($persist) {
                $unit = $persist();
                app(UnitOwnership::class)->syncOwners($unit, $this->unitExtras['owners']);
                app(UnitOwnership::class)->syncCustomFields($unit, $this->unitExtras['custom']);

                return $unit;
            });
        } catch (BusinessRuleException $e) {
            Notification::make()->title($e->getMessage())->danger()->persistent()->send();

            throw new Halt;
        }
    }
}
