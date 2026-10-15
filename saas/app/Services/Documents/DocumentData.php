<?php

namespace App\Services\Documents;

use App\Models\Invoice;
use App\Models\Owner;
use App\Models\OwnerTransaction;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\RentContract;
use App\Models\SaleContract;
use App\Support\AmountInWords;
use App\Support\Hijri;
use chillerlan\QRCode\QRCode;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\URL;

/** Variables for each printable document (legacy rent/sell/statementsin/statementsout/invoices printable). */
class DocumentData
{
    public function __construct(private readonly DocumentRenderer $renderer)
    {
    }

    public function rentContract(RentContract $c): string
    {
        $c->loadMissing(['unit.owners', 'unit.city', 'unit.district', 'clients', 'terms']);

        return $this->renderer->render('rent_contract', [
            'number' => $c->number,
            'date' => $this->date($c->contract_date, $c->calendar?->value),
            'owners' => $this->people($c->unit->owners, 'share_percent'),
            'renters' => $this->people($c->clients, 'share_percent'),
            'unit' => e($c->unit->title),
            'location' => e(trim(($c->unit->city?->name ?? '').' '.($c->unit->district?->name ?? '').' '.($c->unit->address ?? ''))),
            'period' => $c->period_count.' '.$c->period_unit?->getLabel(),
            'start_date' => $this->date($c->start_date, $c->calendar?->value),
            'end_date' => $this->date($c->end_date, $c->calendar?->value),
            'rent_amount' => $this->money($c->rent_amount),
            'rent_amount_words' => e(AmountInWords::format((float) $c->rent_amount)),
            'payment_cycle' => $c->payment_cycle?->getLabel(),
            'vat_amount' => $this->money($c->vat_amount),
            'terms' => $this->terms($c),
            'qrcode_owner' => $this->qr($this->verifyUrl($c)),
            'qrcode_renter' => '',
            'installments_table' => $this->installmentsTable($c),
        ]);
    }

    public function saleContract(SaleContract $c): string
    {
        $c->loadMissing(['unit.owners', 'clients', 'terms']);
        $plan = $c->payment_plan;

        return $this->renderer->render('sale_contract', [
            'number' => $c->number,
            'date' => $this->date($c->contract_date, $c->calendar?->value),
            'owners' => $this->people($c->unit->owners, 'share_percent'),
            'buyers' => $this->people($c->clients, 'share_percent'),
            'unit' => e($c->unit->title),
            'total_amount' => $this->money($c->total_amount),
            'total_amount_words' => e(AmountInWords::format((float) $c->total_amount)),
            'payment_type' => $c->payment_type?->getLabel(),
            'first_payment' => $this->money($plan['first_payment'] ?? 0),
            'installments_table' => $this->installmentsTable($c),
            'terms' => $this->terms($c),
            'qrcode' => $this->qr($this->verifyUrl($c)),
        ]);
    }

    public function voucher(ReceiptVoucher|PaymentVoucher $v): string
    {
        $v->loadMissing(['party', 'unit']);

        return $this->renderer->render($v instanceof ReceiptVoucher ? 'receipt_voucher' : 'payment_voucher', [
            'number' => $v->number,
            'date' => $this->date($v->issued_at, $v->calendar?->value),
            'party' => e($v->party_name ?: ($v->party?->full_name ?? $v->party?->name ?? $v->party?->title ?? '')),
            'amount' => $this->money($v->amount),
            'amount_words' => e(AmountInWords::format((float) $v->amount)),
            'reason' => e((string) $v->reason),
            'payment_method' => $v->payment_method?->getLabel().($v->cheque_number ? ' — '.__('شيك رقم').' '.$v->cheque_number : ''),
            'unit' => e((string) $v->unit?->title),
            'vat_amount' => $this->money($v->vat_amount),
            'notes' => e((string) $v->notes),
        ]);
    }

    public function invoice(Invoice $invoice): string
    {
        $rows = collect($invoice->items)->map(fn ($i) => '<tr><td>'.e($i['description']).'</td><td>'.e((string) ($i['quantity'] ?? 1)).'</td><td>'
            .$this->money($i['unit_price']).'</td><td>'.$this->money($i['vat_amount'] ?? 0).'</td><td>'.$this->money($i['total']).'</td></tr>')->join('');
        $table = '<table class="grid"><thead><tr><th>'.__('البيان').'</th><th>'.__('الكمية').'</th><th>'.__('السعر').'</th><th>'.__('الضريبة').'</th><th>'.__('الإجمالي').'</th></tr></thead><tbody>'.$rows.'</tbody></table>';

        return $this->renderer->render($invoice->vat_total > 0 ? 'tax_invoice' : 'invoice', [
            'number' => $invoice->number,
            'date' => $invoice->issue_date->toDateString(),
            'customer' => e((string) $invoice->customer_name),
            'customer_vat_number' => e((string) $invoice->customer_vat_number),
            'items_table' => $table,
            'subtotal' => $this->money($invoice->subtotal),
            'vat_total' => $this->money($invoice->vat_total),
            'total' => $this->money($invoice->total),
            'total_words' => e(AmountInWords::format((float) $invoice->total)),
            'qrcode' => $invoice->zatca_qr ? $this->qr($invoice->zatca_qr) : '',
        ]);
    }

    public function ownerStatement(Owner $owner, ?string $from = null, ?string $to = null): string
    {
        $entries = OwnerTransaction::with(['type', 'unit'])->where('owner_id', $owner->id)
            ->when($from, fn ($q) => $q->whereDate('due_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('due_date', '<=', $to))
            ->orderBy('due_date')->orderBy('id')->get();
        $running = 0.0;
        $rows = $entries->map(function (OwnerTransaction $t) use (&$running) {
            $signed = $t->direction->value === 'debit' ? (float) $t->amount : -(float) $t->amount;
            $running += $signed;

            return '<tr><td>'.$t->due_date->toDateString().'</td><td>'.e($t->type->title).'</td><td>'.e((string) $t->unit?->title).'</td><td>'
                .($signed > 0 ? $this->money($signed) : '').'</td><td>'.($signed < 0 ? $this->money(-$signed) : '').'</td><td>'.$this->money($running).'</td></tr>';
        })->join('');
        $table = '<table class="grid"><thead><tr><th>'.__('التاريخ').'</th><th>'.__('البيان').'</th><th>'.__('الوحدة').'</th><th>'.__('له').'</th><th>'.__('عليه').'</th><th>'.__('الرصيد').'</th></tr></thead><tbody>'.$rows.'</tbody></table>';

        return $this->renderer->render('owner_statement', [
            'owner' => e($owner->full_name),
            'from_date' => $from ?? ($entries->first()?->due_date?->toDateString() ?? ''),
            'to_date' => $to ?? today()->toDateString(),
            'transactions_table' => $table,
            'balance' => $this->money($running),
        ]);
    }

    /** Signed, non-expiring link printed as a QR so anyone can check the contract is genuine (legacy contract QR). */
    public function verifyUrl(RentContract|SaleContract $contract): string
    {
        return URL::signedRoute('documents.verify', ['type' => $contract->getMorphClass(), 'id' => $contract->id]);
    }

    private function installmentsTable(RentContract|SaleContract $c): string
    {
        $rows = $c->installments()->orderBy('due_date')->get()->map(fn ($i) => '<tr><td>'.$this->date($i->due_date, $c->calendar?->value).'</td><td>'.$this->money($i->amount).'</td></tr>')->join('');

        return '<table class="grid"><thead><tr><th>'.__('تاريخ الاستحقاق').'</th><th>'.__('المبلغ').'</th></tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    private function terms(RentContract|SaleContract $c): string
    {
        $list = $c->terms->map(fn ($t) => '<li><strong>'.e($t->title).'</strong>: '.$t->content.'</li>')->join('');

        return ($list ? '<ol>'.$list.'</ol>' : '').($c->extra_terms ?? '');
    }

    private function people($people, string $pivot): string
    {
        return e($people->map(fn ($p) => ($p->full_name).(isset($p->pivot) && (float) $p->pivot->{$pivot} < 100 ? ' ('.(float) $p->pivot->{$pivot}.'%)' : ''))->join('، '));
    }

    private function qr(string $data): string
    {
        return '<img src="'.(new QRCode)->render($data).'" width="140" height="140" alt="QR">';
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2);
    }

    private function date(?CarbonInterface $date, ?string $calendar): string
    {
        if ($date === null) {
            return '';
        }

        return $calendar === 'hijri' ? Hijri::toString($date).' هـ' : $date->toDateString();
    }
}
