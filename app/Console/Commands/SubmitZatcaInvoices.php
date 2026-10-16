<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\Zatca\Phase2\ZatcaInvoicing;
use Illuminate\Console\Command;

/** Sends every signed invoice ZATCA has not yet accepted (simplified invoices must be reported within 24 hours). */
class SubmitZatcaInvoices extends Command
{
    protected $signature = 'rowad:zatca {--tenant=* : Limit to these office ids}';

    protected $description = 'Report/clear pending ZATCA invoices for every office on the integration phase';

    public function handle(): int
    {
        $tenants = Tenant::query()->when($this->option('tenant'), fn ($q, $ids) => $q->whereKey($ids))->get()
            ->filter(fn (Tenant $t) => $t->isAccessible());

        foreach ($tenants as $tenant) {
            try {
                $sent = $tenant->run(function () {
                    if (! ZatcaInvoicing::enabled()) {
                        return null;
                    }
                    $zatca = app(ZatcaInvoicing::class);
                    $invoices = Invoice::query()->whereIn('zatca_status', ['pending', 'failed'])
                        ->where('zatca_attempts', '<', ZatcaInvoicing::MAX_ATTEMPTS)->orderBy('zatca_icv')->limit(200)->get();
                    foreach ($invoices as $invoice) {
                        $zatca->submit($invoice);
                    }

                    return $invoices->count();
                });
                if ($sent !== null) {
                    $this->line("{$tenant->name}: {$sent}");
                }
            } catch (\Throwable $e) {
                report($e);
                $this->error("{$tenant->name}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
