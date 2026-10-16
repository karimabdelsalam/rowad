<?php

namespace App\Services\Finance;

use App\Exceptions\BusinessRuleException;
use App\Models\Client;
use App\Models\Installment;
use App\Models\Invoice;
use App\Services\DocumentNumberGenerator;
use App\Services\Zatca\ZatcaQrCode;
use App\Support\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Port of legacy invoices::create / taxbill with a ZATCA phase-1 QR. */
class InvoiceService
{
    public function __construct(private readonly DocumentNumberGenerator $numbers)
    {
    }

    /** @param Collection<int, Installment>|list<int> $installments */
    public function fromInstallments(Collection|array $installments, array $attributes = []): Invoice
    {
        $installments = $installments instanceof Collection ? $installments : Installment::with(['unit', 'contract.clients'])->whereKey($installments)->get();
        if ($installments->isEmpty()) {
            throw new BusinessRuleException(__('اختر قسطًا واحدًا على الأقل'));
        }
        if ($already = $installments->firstWhere('invoice_id', '!=', null)) {
            throw new BusinessRuleException(__('القسط رقم :id صدرت له فاتورة من قبل', ['id' => $already->id]));
        }

        $client = $installments->first()->contract?->clients->first();
        $items = $installments->map(fn (Installment $i) => [
            'description' => $i->type->getLabel().' — '.$i->unit->title.' — '.$i->due_date->toDateString(),
            'quantity' => 1,
            'unit_price' => round((float) $i->amount - (float) $i->vat_amount, 2),
            'vat_percent' => (float) $i->vat_percent,
            'vat_amount' => (float) $i->vat_amount,
            'total' => (float) $i->amount,
        ])->values()->all();

        return DB::transaction(function () use ($items, $installments, $client, $attributes) {
            $invoice = $this->create($items, $attributes + [
                'client_id' => $client?->id,
                'customer_name' => $client?->displayName(),
                'customer_vat_number' => $client?->vat_number,
                'paid_amount' => $installments->sum(fn (Installment $i) => $i->paidTotal()),
            ]);
            Installment::whereKey($installments->modelKeys())->update(['invoice_id' => $invoice->id]);

            return $invoice;
        });
    }

    /** @param list<array{description:string, quantity:float|int, unit_price:float, vat_percent?:float}> $items */
    public function create(array $items, array $attributes = []): Invoice
    {
        $items = array_map(function (array $item) {
            $net = round((float) $item['unit_price'] * (float) ($item['quantity'] ?? 1), 2);
            $vat = $item['vat_amount'] ?? round($net * (float) ($item['vat_percent'] ?? 0) / 100, 2);

            return $item + ['vat_amount' => $vat, 'total' => round($net + $vat, 2)];
        }, $items);

        $subtotal = round(array_sum(array_map(fn ($i) => (float) $i['unit_price'] * (float) ($i['quantity'] ?? 1), $items)), 2);
        $vatTotal = round(array_sum(array_column($items, 'vat_amount')), 2);
        $total = round($subtotal + $vatTotal, 2);
        $issued = now();
        $vatNumber = Settings::get('vat_number');

        $invoice = Invoice::create($attributes + [
            'number' => $this->numbers->next('invoice'),
            'type' => filled($attributes['customer_vat_number'] ?? null) ? 'standard' : 'simplified',
            'issue_date' => $issued->toDateString(),
            'items' => $items,
            'subtotal' => $subtotal,
            'vat_total' => $vatTotal,
            'total' => $total,
            'zatca_uuid' => (string) Str::uuid(),
            'zatca_qr' => filled($vatNumber) && preg_match('/^3\d{13}3$/', (string) $vatNumber)
                ? ZatcaQrCode::payload((string) Settings::get('site_name', tenant('name')), (string) $vatNumber, $issued, $total, $vatTotal)
                : null,
            'created_by' => auth()->id(),
            'issued_at' => $issued,
        ]);

        // integration phase: sign now on the office's chain, report/clear once the transaction commits
        if (\App\Services\Zatca\Phase2\ZatcaInvoicing::enabled()) {
            app(\App\Services\Zatca\Phase2\ZatcaInvoicing::class)->sign($invoice);
            \App\Jobs\SubmitZatcaInvoice::dispatch($invoice->id)->afterCommit();
        }

        return $invoice;
    }

    /** Credit note (or debit note) against an issued invoice, same lines unless given, with the reason ZATCA requires. */
    public function adjustment(Invoice $original, string $kind, string $reason, ?array $items = null): Invoice
    {
        if ($original->kind !== 'invoice') {
            throw new BusinessRuleException(__('يصدر الإشعار على فاتورة فقط'));
        }
        $items = $items ?? array_map(fn (array $i) => array_intersect_key($i, array_flip(['description', 'quantity', 'unit_price', 'vat_percent'])), (array) $original->items);

        return DB::transaction(fn () => $this->create($items, [
            'kind' => $kind,
            'type' => $original->type,
            'billing_reference' => $original->number,
            'reason' => $reason,
            'client_id' => $original->client_id,
            'customer_name' => $original->customer_name,
            'customer_vat_number' => $original->customer_vat_number,
        ]));
    }
}
