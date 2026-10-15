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
                'paid_amount' => $installments->sum(fn (Installment $i) => (float) $i->paid_amount + ($i->is_collected ? $i->remainingAmount() : 0)),
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

        return Invoice::create($attributes + [
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
        ]);
    }
}
