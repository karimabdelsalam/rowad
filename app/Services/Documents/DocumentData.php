<?php

namespace App\Services\Documents;

use App\Filament\Admin\Resources\JournalEntries\JournalEntryResource;
use App\Models\ContractSignature;
use App\Models\EndOfServiceSettlement;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Letter;
use App\Models\MaintenanceRequest;
use App\Models\Owner;
use App\Models\OwnerPayout;
use App\Models\OwnerTransaction;
use App\Models\PaymentVoucher;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\PrintTemplate;
use App\Models\ReceiptVoucher;
use App\Models\RentContract;
use App\Models\SaleContract;
use App\Models\Unit;
use App\Support\AmountInWords;
use App\Support\Hijri;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use chillerlan\QRCode\QRCode;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/** Variables for each printable document (legacy rent/sell/statementsin/statementsout/invoices printable). */
class DocumentData
{
    public function __construct(private readonly DocumentRenderer $renderer, private readonly LegacyTemplateVariables $legacy) {}

    /**
     * Templates imported from the legacy system (legacy_syntax) get the legacy meaning of every name — e.g. {total}
     * was the pre-tax amount on invoices; templates written for the new system keep the new names, with legacy-only
     * names available as extras. Repeating blocks (<!--{owners}-->) are filled for both.
     *
     * @param  array{vars: array<string, mixed>, blocks: array<string, string>}  $legacy
     */
    private function document(string $key, array $vars, array $legacy): string
    {
        $legacySyntax = (bool) PrintTemplate::where('key', $key)->value('legacy_syntax');

        return $this->renderer->render($key, $legacySyntax ? array_merge($vars, $legacy['vars']) : $vars + $legacy['vars'], $legacy['blocks']);
    }

    public function letter(Letter $letter): string
    {
        return $this->document($letter->type->templateKey(), ['number' => (string) $letter->id], $this->legacy->letter($letter));
    }

    /** Legacy building/printable: a unit's data sheet with the organisation's document header. */
    /** The office header/footer the HR sheets share with the unit sheet. */
    /** The office letterhead (header, footer, currency) that every generated sheet and report shares. */
    public function letterhead(): array
    {
        return $this->sheetFrame();
    }

    private function sheetFrame(): array
    {
        $template = PrintTemplate::where('key', 'rent_contract')->first();
        $dir = resource_path('print-templates');
        $org = $this->renderer->organisationVariables();

        return [
            'header' => strtr($template?->header ?: (string) file_get_contents("{$dir}/_header.html"), $org),
            'footer' => strtr($template?->footer ?: (string) file_get_contents("{$dir}/_footer.html"), $org),
            'currency' => $org['{$}'],
        ];
    }

    public function payslip(PayrollItem $item): string
    {
        $item->loadMissing(['employee.bank', 'run', 'paymentVoucher']);

        return view('print.payslip', ['item' => $item, 'run' => $item->run, 'employee' => $item->employee] + $this->sheetFrame())->render();
    }

    public function payroll(PayrollRun $run): string
    {
        $run->loadMissing(['items.employee', 'approver']);

        return view('print.payroll', ['run' => $run] + $this->sheetFrame())->render();
    }

    public function endOfService(EndOfServiceSettlement $s): string
    {
        $s->loadMissing(['employee', 'paymentVoucher']);

        return view('print.end-of-service', ['s' => $s, 'employee' => $s->employee] + $this->sheetFrame())->render();
    }

    public function journalEntry(JournalEntry $entry): string
    {
        $lines = $entry->lines()->with(['account', 'owner'])->orderByDesc('debit')->orderBy('id')->get();
        $entry->loadMissing('creator');

        return view('print.journal-entry', ['entry' => $entry, 'lines' => $lines,
            'source' => JournalEntryResource::sourceLabel($entry)] + $this->sheetFrame())->render();
    }

    public function ownerPayout(OwnerPayout $payout): string
    {
        $payout->loadMissing(['items.owner', 'bankAccount']);

        return view('print.owner-payout', ['payout' => $payout] + $this->sheetFrame())->render();
    }

    public function maintenanceOrder(MaintenanceRequest $request): string
    {
        $request->loadMissing(['unit.building', 'client']);

        return view('print.maintenance-order', ['request' => $request] + $this->sheetFrame())->render();
    }

    public function unitSheet(Unit $unit): string
    {
        $unit->loadMissing(['building', 'category', 'city', 'district', 'owners', 'deeds.deedType', 'meters.meterType', 'currentContract']);
        if ($unit->currentContract instanceof RentContract) {
            $unit->currentContract->loadMissing('clients');
        }
        $template = PrintTemplate::where('key', 'rent_contract')->first();
        $dir = resource_path('print-templates');
        $org = $this->renderer->organisationVariables();

        return view('print.unit-sheet', [
            'unit' => $unit,
            'header' => strtr($template?->header ?: (string) file_get_contents("{$dir}/_header.html"), $org),
            'footer' => strtr($template?->footer ?: (string) file_get_contents("{$dir}/_footer.html"), $org),
            'currency' => $org['{$}'],
        ])->render();
    }

    public function rentContract(RentContract $c): string
    {
        $c->loadMissing(['unit.owners', 'unit.city', 'unit.district', 'clients', 'terms']);

        $qr = $this->qr($this->verifyUrl($c));

        return $this->document('rent_contract', [
            'number' => $c->number,
            'date' => $this->date($c->contract_date, $c->calendar?->value),
            'day' => $this->dayName($c->contract_date), // legacy {day}: weekday of the contract date
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
            'qrcode_owner' => $qr,
            'qrcode_renter' => '',
            'owner_signature' => $this->signatures($c, 'lessor'),
            'renter_signature' => $this->signatures($c, 'lessee'),
            'installments_table' => $this->installmentsTable($c),
        ], $this->legacy->rentContract($c, $qr));
    }

    /** Electronic signatures of one side of the contract, drawn by the parties (see ContractSigning). */
    private function signatures(RentContract $c, string $role): string
    {
        return $c->signatures()->where('role', $role)->where('status', ContractSignature::SIGNED)->orderBy('id')->get()
            ->map(fn (ContractSignature $s) => '<div class="esign"><img src="'.e((string) $s->signature).'" alt="">'
                .'<small>'.e($s->name).' — '.e(__('وُقّع إلكترونيًا :at', ['at' => $s->signed_at->format('Y-m-d H:i')])).'<br>'
                .e(__('بصمة العقد: :hash', ['hash' => substr((string) $s->document_hash, 0, 16)])).'</small></div>')
            ->join('');
    }

    public function saleContract(SaleContract $c): string
    {
        $c->loadMissing(['unit.owners', 'clients', 'terms']);
        $plan = $c->payment_plan;

        $qr = $this->qr($this->verifyUrl($c));

        return $this->document('sale_contract', [
            'number' => $c->number,
            'date' => $this->date($c->contract_date, $c->calendar?->value),
            'day' => $this->dayName($c->contract_date), // legacy {day}: weekday of the contract date
            'owners' => $this->people($c->unit->owners, 'share_percent'),
            'buyers' => $this->people($c->clients, 'share_percent'),
            'unit' => e($c->unit->title),
            'total_amount' => $this->money($c->total_amount),
            'total_amount_words' => e(AmountInWords::format((float) $c->total_amount)),
            'payment_type' => $c->payment_type?->getLabel(),
            'first_payment' => $this->money($plan['first_payment'] ?? 0),
            'installments_table' => $this->installmentsTable($c),
            'terms' => $this->terms($c),
            'qrcode' => $qr,
        ], $this->legacy->saleContract($c, $qr));
    }

    public function voucher(ReceiptVoucher|PaymentVoucher $v): string
    {
        $v->loadMissing(['party', 'unit']);

        return $this->document($v instanceof ReceiptVoucher ? 'receipt_voucher' : 'payment_voucher', [
            'number' => $v->number,
            'date' => $this->date($v->issued_at, $v->calendar?->value),
            'party' => e($v->party_name ?: ($v->party?->full_name ?? $v->party?->name ?? $v->party?->title ?? '')),
            'amount' => $this->money($v->amount),
            'amount_words' => e(AmountInWords::format((float) $v->amount)),
            'reason' => e((string) $v->reason),
            'payment_method' => e($v->payment_method?->getLabel().($v->cheque_number ? ' — '.__('شيك رقم').' '.$v->cheque_number : '')),
            'unit' => e((string) $v->unit?->title),
            'vat_amount' => $this->money($v->vat_amount),
            'notes' => e((string) $v->notes),
            'pay_details' => $this->paymentDetails($v),
        ], $this->legacy->voucher($v));
    }

    public function invoice(Invoice $invoice): string
    {
        $rows = collect($invoice->items)->map(fn ($i) => '<tr><td>'.e($i['description']).'</td><td>'.e((string) ($i['quantity'] ?? 1)).'</td><td>'
            .$this->money($i['unit_price']).'</td><td>'.$this->money($i['vat_amount'] ?? 0).'</td><td>'.$this->money($i['total']).'</td></tr>')->join('');
        $table = '<table class="grid"><thead><tr><th>'.__('البيان').'</th><th>'.__('الكمية').'</th><th>'.__('السعر').'</th><th>'.__('الضريبة').'</th><th>'.__('الإجمالي').'</th></tr></thead><tbody>'.$rows.'</tbody></table>';

        $qr = $invoice->zatca_qr ? $this->qr($invoice->zatca_qr) : '';
        $note = $invoice->kind !== 'invoice' && filled($invoice->billing_reference)
            ? __('مرجع الفاتورة: :number — السبب: :reason', ['number' => $invoice->billing_reference, 'reason' => (string) $invoice->reason]).' '
            : '';

        $html = $this->document($invoice->vat_total > 0 ? 'tax_invoice' : 'invoice', [
            'number' => $invoice->number,
            'date' => $invoice->issue_date->toDateString(),
            'customer' => e((string) $invoice->customer_name),
            'customer_vat_number' => e((string) $invoice->customer_vat_number),
            'items_table' => $table,
            'subtotal' => $this->money($invoice->subtotal),
            'vat_total' => $this->money($invoice->vat_total),
            'total' => $this->money($invoice->total),
            'total_words' => e(AmountInWords::format((float) $invoice->total)),
            'qrcode' => $qr,
            'notes' => e($note.$invoice->notes),
        ], $this->legacy->invoice($invoice, $qr));

        // credit/debit notes reuse the invoice design under their own title
        $title = ['credit' => __('إشعار دائن'), 'debit' => __('إشعار مدين')][$invoice->kind] ?? null;

        return $title === null ? $html : str_replace(['<h1>فاتورة ضريبية</h1>', '<h1>فاتورة</h1>', 'TAX INVOICE<', '>INVOICE<'],
            ["<h1>{$title}</h1>", "<h1>{$title}</h1>", strtoupper($invoice->kind).' NOTE<', '>'.strtoupper($invoice->kind).' NOTE<'], $html);
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

    /** Cheque / transfer details as a styled table (legacy templates keep their own pay_details). */
    private function paymentDetails(ReceiptVoucher|PaymentVoucher $v): string
    {
        $rows = array_filter([
            __('رقم الشيك') => $v->cheque_number, __('اسم البنك') => $v->bank_name,
            __('تاريخ الشيك') => $v->cheque_date ? $this->date($v->cheque_date, $v->calendar?->value) : null,
        ], fn ($value) => filled($value));

        return $rows === [] ? '' : '<table class="data"><thead><tr>'.collect(array_keys($rows))->map(fn ($h) => '<th>'.e($h).'</th>')->join('')
            .'</tr></thead><tbody><tr>'.collect($rows)->map(fn ($v) => '<td>'.e((string) $v).'</td>')->join('').'</tr></tbody></table>';
    }

    private function installmentsTable(RentContract|SaleContract $c): string
    {
        $rows = $c->installments()->orderBy('due_date')->get()->values()->map(fn ($i, $n) => '<tr><td>'.($n + 1).'</td><td>'.$this->date($i->due_date, $c->calendar?->value)
            .'</td><td>'.e((string) $i->type?->getLabel()).'</td><td class="num">'.$this->money($i->amount).'</td><td>'
            .($i->is_collected ? '<span class="badge">'.__('مسدد').'</span>' : ((float) $i->paid_amount > 0 ? __('مسدد جزئيًا') : __('مستحق'))).'</td></tr>')->join('');

        return '<table class="grid"><thead><tr><th>#</th><th>'.__('تاريخ الاستحقاق').'</th><th>'.__('النوع').'</th><th>'.__('المبلغ').'</th><th>'.__('الحالة').'</th></tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    private function terms(RentContract|SaleContract $c): string
    {
        $list = $c->terms->map(fn ($t) => '<li><strong>'.e($t->title).'</strong>: '.self::safeHtml($t->content).'</li>')->join('');

        return ($list ? '<ol>'.$list.'</ol>' : '').self::safeHtml($c->extra_terms);
    }

    /** Staff-written text or rich text, printed as HTML: scripts, handlers and unknown tags removed, line breaks kept. */
    public static function safeHtml(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        return strip_tags($html) === $html
            ? nl2br(e($html), false)
            : Str::sanitizeHtml($html);
    }

    private function people($people, string $pivot): string
    {
        return e($people->map(fn ($p) => ($p->full_name).(isset($p->pivot) && (float) $p->pivot->{$pivot} < 100 ? ' ('.(float) $p->pivot->{$pivot}.'%)' : ''))->join('، '));
    }

    private function dayName(?\DateTimeInterface $date): string
    {
        return $date === null ? '' : Carbon::instance($date)->locale(app()->getLocale())->translatedFormat('l');
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
