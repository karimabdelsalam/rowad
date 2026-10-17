<?php

namespace App\Console\Commands;

use App\Enums\EjarStatus;
use App\Models\RentContract;
use App\Models\Tenant;
use App\Services\Ejar\EjarApi;
use App\Services\Ejar\EjarException;
use App\Services\Ejar\EjarRegistration;
use Illuminate\Console\Command;

/**
 * Follows contracts each office sent to Ejar: once every party approves and Ejar registers the contract it
 * is marked documented and Ejar's PDF is attached. Offices without an Ejar connection are skipped.
 */
class SyncEjarContracts extends Command
{
    protected $signature = 'rowad:ejar:sync {--tenant=* : Limit to these office ids}';

    protected $description = 'Refresh the Ejar state of contracts awaiting the parties\' approval';

    public function handle(): int
    {
        $tenants = Tenant::query()->when($this->option('tenant'), fn ($q, $ids) => $q->whereKey($ids))->get()
            ->filter(fn (Tenant $t) => $t->isAccessible());

        foreach ($tenants as $tenant) {
            try {
                $result = $tenant->run(function () {
                    if (! EjarApi::connected()) {
                        return null;
                    }
                    $registration = EjarRegistration::make();
                    $contracts = RentContract::query()->where('ejar_status', EjarStatus::AwaitingParties)
                        ->whereNotNull('ejar_number')->whereNotNull('ejar_contract_id')
                        ->orderBy('ejar_synced_at')->limit(200)->get();
                    $documented = 0;
                    foreach ($contracts as $contract) {
                        try {
                            $registration->refresh($contract);
                            $documented += $contract->ejar_status === EjarStatus::Documented ? 1 : 0;
                        } catch (EjarException $e) {
                            if (in_array($e->status, [401, 403], true)) {
                                throw $e; // the office's key stopped working: no point asking about the rest
                            }
                        }
                    }

                    return [$contracts->count(), $documented];
                });
                if ($result !== null) {
                    $this->line("{$tenant->name}: {$result[0]} checked, {$result[1]} documented");
                }
            } catch (\Throwable $e) {
                report($e);
                $this->error("{$tenant->name}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
