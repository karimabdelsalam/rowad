<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\Zatca\Phase2\ZatcaInvoicing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Reports or clears one signed invoice with ZATCA (runs in the office's context). */
class SubmitZatcaInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1; // retries are the scheduler's job (rowad:zatca), so the chain order stays visible

    public function __construct(public int $invoiceId) {}

    public function handle(ZatcaInvoicing $zatca): void
    {
        if ($invoice = Invoice::find($this->invoiceId)) {
            $zatca->submit($invoice);
        }
    }
}
