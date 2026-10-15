<?php

namespace App\Services\Documents;

use App\Enums\LetterType;
use App\Models\City;
use App\Models\Client;
use App\Models\District;
use App\Models\Invoice;
use App\Models\Letter;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\RentContract;
use App\Models\SaleContract;
use App\Models\Unit;
use App\Models\UnitPlot;
use App\Support\AmountInWords;
use App\Support\Hijri;
use App\Support\Settings;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The variable names and repeating blocks the legacy printable actions filled (rent/sell/statementsin/
 * statementsout/invoices/letters controllers), so templates offices designed in the old system print
 * unchanged after import. Values follow the legacy controllers line by line.
 *
 * @phpstan-type Doc array{vars: array<string, mixed>, blocks: array<string, string>}
 */
class LegacyTemplateVariables
{
    /** @return Doc */
    public function rentContract(RentContract $c, string $qr): array
    {
        $c->loadMissing(['unit.owners', 'unit.building', 'unit.city', 'unit.district', 'unit.category', 'unit.meters.meterType', 'unit.deeds.deedType', 'clients', 'terms', 'installments']);
        $cal = $c->calendar?->value;
        $installments = $c->installments;
        $allTotal = (float) $installments->sum('amount');
        $paid = (float) $installments->sum(fn ($i) => $i->is_collected ? (float) $i->amount : (float) $i->paid_amount);
        $commission = (float) $installments->sum(fn ($i) => (float) $i->seller_commission + (float) $i->buyer_commission) ?: (float) $c->commission;

        return [
            'vars' => $this->unitVars($c->unit) + [
                'number' => $c->number, 'serial' => $c->number,
                'date' => $this->date($c->contract_date, $cal), 'day' => $this->day($c->contract_date),
                'period' => $c->period_count.' '.$c->period_unit?->getLabel(),
                'startdate' => $this->date($c->start_date, $cal), 'enddate' => $this->date($c->end_date, $cal),
                'purpose' => $c->usage?->getLabel() ?? '', 'tax' => $this->n($c->vat_amount),
                'rent' => $this->n((float) $c->rent_amount - (float) $c->vat_amount),
                'total_rent' => $this->n($c->rent_amount), 'total_rent_text' => AmountInWords::words((float) $c->rent_amount),
                'all_total_rent' => $this->n($allTotal), 'all_total_rent_text' => AmountInWords::words($allTotal),
                'pay_method' => $c->payment_cycle?->getLabel() ?? '',
                'remain_amount' => $this->n($allTotal - $paid), 'paid' => $this->n($paid), 'paid_text' => AmountInWords::words($paid),
                'commission' => $this->n($commission), 'commission_text' => AmountInWords::words($commission),
                'qrcode_owner' => $qr, 'qrcode_customer' => $qr,
            ],
            'blocks' => $this->unitBlocks($c->unit, (array) ($c->meter_readings ?? [])) + [
                'buyers' => $this->clientRows($c->clients),
                'rights' => $this->rights($c),
            ],
        ];
    }

    /** @return Doc */
    public function saleContract(SaleContract $c, string $qr): array
    {
        $c->loadMissing(['unit.owners', 'unit.building', 'unit.city', 'unit.district', 'unit.category', 'unit.meters.meterType', 'unit.deeds.deedType', 'unit.landPlots', 'clients', 'terms']);
        $plan = (array) $c->payment_plan;
        $total = (float) $c->total_amount;
        [$commission, $details, $first] = $this->salePayment($c->payment_type?->value ?? 'once', $plan, $total, $c->calendar?->value);

        return [
            'vars' => $this->unitVars($c->unit) + [
                'number' => $c->number, 'serial' => $c->number,
                'date' => $this->date($c->contract_date, $c->calendar?->value), 'day' => $this->day($c->contract_date),
                'commission' => $this->n($commission), 'amount' => $this->n($total),
                'total' => $this->n($total + $commission), 'total_str' => AmountInWords::words($total + $commission),
                'pay_method' => $c->payment_type?->getLabel() ?? '', 'pay_details' => $details, 'firstvalue' => $this->n($first),
                'qrcode_owner' => $qr, 'qrcode_buyer' => $qr,
            ],
            'blocks' => $this->unitBlocks($c->unit) + [
                'buyers' => $this->clientRows($c->clients),
                'rights' => $this->rights($c),
            ],
        ];
    }

    /** @return Doc legacy statementsin/statementsout printable */
    public function voucher(ReceiptVoucher|PaymentVoucher $v): array
    {
        $v->loadMissing(['party', 'unit']);
        $method = $v->payment_method?->value;
        $cell = 'style="background:#000;color:#fff;padding:8px"';
        $details = match ($method) {
            'cheque' => '<table dir="rtl" align="center" style="font-weight:bold;width:100%;border-collapse:collapse;border:solid 1px #ccc;text-align:center">'
                .'<tr><td '.$cell.'>'.__('رقم الشيك').'</td><td '.$cell.'>'.__('اسم البنك').'</td><td '.$cell.'>'.__('تاريخه').'</td></tr>'
                .'<tr><td style="padding:5px">'.e((string) $v->cheque_number).'</td><td>'.e((string) $v->bank_name).'</td><td>'.$this->date($v->cheque_date, $v->calendar?->value).'</td></tr></table>',
            'transfer' => '<table dir="rtl" align="center" style="font-weight:bold;width:100%;border-collapse:collapse;border:solid 1px #ccc;text-align:center">'
                .'<tr><td '.$cell.'>'.__('اسم البنك').'</td><td '.$cell.'>'.__('تاريخه').'</td></tr>'
                .'<tr><td style="padding:5px">'.e((string) $v->bank_name).'</td><td>'.$this->date($v->issued_at, $v->calendar?->value).'</td></tr></table>',
            default => '',
        };
        $partyTypes = ['owner' => __('مالك'), 'client' => __('عميل'), 'employee' => __('موظف'), 'citizen' => __('فرد'),
            'government_organization' => __('جهة حكومية'), 'trade_organization' => __('جهة تجارية')];

        return ['vars' => [
            'number' => $v->number, 'date' => $this->date($v->issued_at, $v->calendar?->value),
            'from_type' => $partyTypes[$v->party_type] ?? __('أخرى'), 'build' => e((string) $v->unit?->title),
            'amount' => $this->n($v->amount), 'amount_text' => AmountInWords::words((float) $v->amount),
            'name' => e($v->party_name ?: ($v->party?->full_name ?? $v->party?->name ?? $v->party?->title ?? '')),
            'pay_method' => $v->payment_method?->getLabel() ?? '', 'pay_details' => $details,
            'reason' => e((string) $v->reason), 'notes' => e((string) $v->notes),
        ], 'blocks' => []];
    }

    /** @return Doc legacy invoices printable (template 12 simple, 13 tax invoice) */
    public function invoice(Invoice $invoice, string $qr): array
    {
        $invoice->loadMissing('client');
        $currency = ' '.Settings::get('currency', 'SAR');
        $items = collect($invoice->items);
        $tax = (float) $invoice->vat_total > 0;
        $rows = $items->map(function (array $i) use ($tax) {
            $amount = (float) ($i['total'] ?? 0);
            $vat = (float) ($i['vat_amount'] ?? 0);
            $title = e((string) ($i['description'] ?? ''));

            return $tax
                ? '<tr><td style="text-align:center">'.$this->n($amount).'</td><td style="text-align:center">'.$this->n($vat).'</td><td style="text-align:center">'
                    .($amount - $vat > 0 ? round($vat / ($amount - $vat) * 100, 2) : 0).'%</td><td style="text-align:center">0.00</td><td style="text-align:center">'
                    .$this->n($amount - $vat).'</td><td style="text-align:center">'.e((string) ($i['quantity'] ?? 1)).'</td><td style="text-align:center">'
                    .$this->n($amount - $vat).'</td><td style="text-align:right">'.$title.'</td></tr>'
                : '<tr><td style="text-align:right">'.$title.'</td><td style="text-align:right"></td><td style="text-align:center">'.$this->n($vat)
                    .'</td><td style="text-align:center">0.00</td><td style="text-align:center">'.$this->n($amount).'</td></tr>';
        })->join('');
        $paid = (float) $invoice->paid_amount;

        return ['vars' => [
            'fullname' => e((string) ($invoice->client?->full_name ?? $invoice->customer_name)), 'clientno' => '',
            'created_date' => $this->date($invoice->issue_date, $invoice->calendar?->value), 'no' => $invoice->number,
            'total' => $this->n($invoice->subtotal).$currency, 'taxs' => $this->n($invoice->vat_total).$currency,
            'total_wtaxes' => $this->n($invoice->total).$currency, 'paid' => $this->n(0).$currency,
            'collected' => $this->n($paid).$currency, 'remain' => $this->n((float) $invoice->total - $paid).$currency,
            'notes' => e((string) $invoice->notes), 'customer_country' => e((string) ($invoice->client?->nationality ?? '')),
            'seller_country' => __('المملكة العربية السعودية'), 'qrcode' => $qr,
        ], 'blocks' => ['items' => $rows]];
    }

    /** @return Doc legacy letters printable, per letter type */
    public function letter(Letter $letter): array
    {
        $letter->loadMissing(['client', 'unit.category', 'unit.currentContract']);
        $data = (array) ($letter->data ?? []);
        $contract = $letter->unit?->currentContract instanceof RentContract ? $letter->unit->currentContract : null;
        $toName = function (array $part, bool $withDesc = false) {
            return ($part['sendto'] ?? 'random') === 'person'
                ? trim(($withDesc && filled($part['sendto_desc'] ?? null) ? $part['sendto_desc'].': ' : '').($part['sendto_name'] ?? '').' '.($part['sendto_title'] ?? ''))
                : __('إلى من يهمه الأمر');
        };
        $date = $this->date($letter->letter_date, $letter->calendar?->value);
        $renter = e((string) $letter->client?->full_name);
        $endDate = $contract ? $this->date($contract->end_date, $contract->calendar?->value) : '';

        $vars = match ($letter->type) {
            LetterType::Official => ['content' => (string) ($data['official']['content'] ?? ''), 'toname' => e($toName($data['official'] ?? []))],
            LetterType::Clearance => [
                'renter' => $renter, 'nationality' => e((string) $letter->client?->nationality), 'id_number' => e((string) $letter->client?->id_number),
                'build_type' => e((string) $letter->unit?->category?->title),
                'next_payment' => $contract ? $this->date($contract->next_payment_date, $contract->calendar?->value) : '',
                'toname' => e($toName($data['cancel'] ?? [], true)),
            ],
            LetterType::RentChange => [
                'date' => $date, 'renter' => $renter, 'build' => e((string) $letter->unit?->title), 'contract_no' => $contract?->number ?? '',
                'new_rent' => $this->n($letter->rent_raise_amount), 'new_rent_text' => AmountInWords::words((float) $letter->rent_raise_amount), 'end_date' => $endDate,
            ],
            LetterType::Handover => [
                'date' => $date, 'renter' => $renter, 'build' => e((string) $letter->unit?->title), 'contract_no' => $contract?->number ?? '', 'end_date' => $endDate,
            ],
            LetterType::Evaluation => $this->evaluation((array) ($data['review'] ?? [])),
            default => [],
        };

        return ['vars' => $vars + ['date' => $date], 'blocks' => []];
    }

    // ---------------------------------------------------------------- shared pieces

    /** @return array<string, string> legacy building fields */
    private function unitVars(Unit $u): array
    {
        return [
            'title' => e($u->title), 'parent_build' => e((string) $u->building?->title), 'address' => e((string) $u->address_details),
            'city' => e((string) $u->city?->name), 'district' => e((string) $u->district?->name),
            'type' => $u->offer_type?->getLabel() ?? '', 'category' => e((string) $u->category?->title), 'desc' => e((string) $u->details),
            'buildno' => e((string) $u->number), 'planno' => e((string) $u->plan_number), 'platno' => e((string) $u->plot_number),
            'size' => blank($u->area) ? '--' : (float) $u->area.' '.__('م2'), 'zone' => e((string) $u->zone), 'street' => e((string) $u->street),
            'floor' => blank($u->floor) ? '--' : e((string) $u->floor), 'rooms' => blank($u->rooms) ? '--' : (string) $u->rooms,
            'furnished' => $u->is_furnished ? __('مفروشة') : __('غير مفروشة'),
        ];
    }

    /** @return array<string, string> owners, meters, meters2, deeds and position (plots) row blocks */
    private function unitBlocks(Unit $u, array $readings = []): array
    {
        $td = fn ($v, string $align = 'center', bool $ltr = false) => '<td><p'.($ltr ? ' dir="LTR"' : '').' style="text-align:'.$align.'">'.e((string) $v).'</p></td>';
        $ownership = ['private' => __('خاص'), 'shared' => __('مشترك')];
        $meters = $u->meters->values();

        return [
            'owners' => $u->owners->map(fn ($o) => '<tr><td>'.e($o->full_name).'</td><td style="text-align:center">'.e((string) $o->nationality)
                .'</td><td style="text-align:center">'.e((string) $o->id_number).'</td><td style="text-align:center">'.e((string) $o->mobile).'</td></tr>')->join(''),
            'meters' => $meters->map(fn ($m) => '<tr>'.$td($m->meterType?->title).$td($ownership[$m->ownership] ?? '').$td($m->number, 'center', true)
                .$td($readings[$m->number] ?? '', 'center', true).$td($m->payment_account, 'center', true).$td($m->payment_code, 'center', true).'</tr>')->join(''),
            'meters2' => '<tr>'.$td($meters[0]->number ?? '', 'center', true).$td($meters[1]->number ?? '', 'center', true)
                .$td($readings[$meters[0]->number ?? ''] ?? '', 'center', true).$td($readings[$meters[1]->number ?? ''] ?? '', 'center', true)
                .$td($meters[0]->payment_account ?? '', 'center', true).$td($meters[1]->payment_account ?? '', 'center', true).'</tr>',
            'deeds' => $u->deeds->map(fn ($d) => '<tr>'.$td($d->deedType?->title).$td($d->number).$td($d->source, 'center', true).$td($d->issue_date, 'center', true).'</tr>')->join(''),
            'position' => $u->relationLoaded('landPlots') ? $u->landPlots->map(fn (UnitPlot $p) => '<tr><td>'.e((string) $p->number).'</td><td>'.e((string) ($p->area !== null ? (float) $p->area : ''))
                .'</td>'.collect(['north', 'south', 'east', 'west'])->map(fn ($side) => '<td>'.e($p->describeSide($side)).' '.__('بطول').' ('.e((string) ($p->sides[$side]['length'] ?? '')).') '.__('م').'</td>')->join('').'</tr>')->join('') : '',
        ];
    }

    /** Legacy buyers rows: companies show their registration number and the person representing them. */
    private function clientRows(Collection $clients): string
    {
        return $clients->map(function (Client $c) {
            $row = '<tr><td>'.e($c->company_name ?: $c->full_name).'</td>';
            if ($c->kind?->value === 'company') {
                $row .= '<td></td><td style="text-align:center">'.e((string) $c->company_registration_number).'</td><td style="text-align:center">'.e((string) $c->company_mobile)
                    .'</td></tr><tr><td>'.__('ويمثلها').': '.e($c->full_name).'</td>';
            }

            return $row.'<td style="text-align:center">'.e((string) $c->nationality).'</td><td style="text-align:center">'.e((string) $c->id_number)
                .'</td><td style="text-align:center">'.e((string) $c->mobile).'</td></tr>';
        })->join('');
    }

    private function rights(RentContract|SaleContract $c): string
    {
        return $c->terms->isEmpty() ? '' : '<li>'.$c->terms->map(fn ($t) => $t->content)->implode('</li><li>').'</li>';
    }

    /** @return array{0: float, 1: string, 2: float} commission, payment text, first payment — legacy sell printable wording */
    private function salePayment(string $type, array $plan, float $total, ?string $cal): array
    {
        $cur = Settings::get('currency', 'SAR');
        $money = fn (float $v) => $this->n($v).' '.$cur.' '.__('فقط').' '.AmountInWords::words($v).' '.$cur.' '.__('لا غير');
        $payer = ['buyer' => __('ويتحمل الطرف الثاني قيمة عمولة المكتب وقدره'), 'seller' => __('ويتحمل الطرف الأول قيمة عمولة المكتب وقدره'),
            'split' => __('ويتحمل كل طرف مناصفة قيمة عمولة المكتب وقدره')];

        if ($type === 'parts') {
            $parts = (array) ($plan['parts'] ?? []);
            $text = __('يتم تسديد مبلغ وقدره').' '.$money($total).' '.__('مقسمة على').' '.count($parts).' '.__('دفعة').'<br><br>';
            $commission = 0.0;
            foreach (array_values($parts) as $i => $part) {
                $date = $part['deferred'] ?? false ? ($part['due_date'] ?? null) : ($part['received_date'] ?? null);
                $text .= __('دفعة').' '.($i + 1).': '.__('يتم تسديد مبلغ وقدره').' '.$money((float) ($part['amount'] ?? 0)).' '.__('في تاريخ').' '
                    .($date ? $this->date(\Carbon\CarbonImmutable::parse($date), $cal) : '');
                if ((float) ($part['commission'] ?? 0) > 0) {
                    $commission += (float) $part['commission'];
                    $text .= '<br>'.($payer[$plan['commission_payer'] ?? ''] ?? '').': '.$money((float) $part['commission']);
                }
                $text .= '<br><br>';
            }

            return [$commission, $text.__('وعلى الطرف الثاني أن يلتزم بالسداد على حسب طريقة الدفع المحددة أعلاه'), 0.0];
        }
        if ($type === 'installment') {
            $first = (float) ($plan['first_payment'] ?? 0);
            $cycle = \App\Enums\PaymentCycle::tryFrom((string) ($plan['cycle'] ?? 'month'))?->getLabel() ?? '';
            $text = __('يتم تسديد مبلغ وقدره').' '.$money($total).' '.__('على هيئة أقساط').' '.$cycle.'<br>'
                .__('عدد الأقساط').' '.(int) ($plan['count'] ?? 0).' '.__('تبدأ من تاريخ').' '.($plan['start_date'] ?? null ? $this->date(\Carbon\CarbonImmutable::parse($plan['start_date']), $cal) : '').'<br>'
                .__('تم تسديد مبلغ وقدره').' '.$money($first).' '.__('كدفعة أولى').'<br>'.__('وعلى الطرف الثاني أن يلتزم بالسداد على حسب طريقة الدفع المحددة أعلاه');

            return [0.0, $text, $first];
        }
        $rate = (float) ($plan['commission'] ?? 0);
        $commission = ($plan['commission_type'] ?? 'money') === 'percent' ? round($total * $rate / 100, 2) : $rate;
        $text = __('يتم تسديد مبلغ وقدره').' '.$money($total).' '.__('دفعة واحدة');
        if ($commission > 0) {
            $text .= '<br>'.($payer[$plan['commission_payer'] ?? ''] ?? '').': '.$money($commission);
        }

        return [$commission, $text, 0.0];
    }

    /** @return array<string, string> legacy review (property evaluation) letter */
    private function evaluation(array $r): array
    {
        $plot = new UnitPlot(['sides' => collect(UnitPlot::SIDES)->mapWithKeys(fn ($s) => [$s => [
            'type' => $r['plot']["{$s}_type"] ?? null, 'width' => $r['plot']["{$s}_width"] ?? null, 'plot_number' => $r['plot']["{$s}_plotno"] ?? null,
        ]])->all()]);
        $vars = [
            'name' => e((string) ($r['provider'] ?? '')),
            'provider' => ($r['provider_title'] ?? 'owner') === 'agent' ? __('وكيل شرعي لـ').e((string) ($r['agent_name'] ?? '')) : __('المالك'),
            'desc' => e((string) ($r['about'] ?? '')), 'city' => e((string) City::find($r['city_id'] ?? null)?->name),
            'district' => e((string) District::find($r['district_id'] ?? null)?->name), 'plotno' => e((string) ($r['plotno'] ?? '')),
            'planno' => e((string) ($r['planno'] ?? '')), 'size' => e((string) ($r['size'] ?? '')),
            'amount' => $this->n($r['amount'] ?? 0), 'amount_text' => AmountInWords::words((float) ($r['amount'] ?? 0)),
        ];
        foreach (UnitPlot::SIDES as $side) {
            $vars["{$side}_long"] = e((string) ($r['plot']["{$side}_length"] ?? '')).' '.__('م');
            $vars["{$side}_type"] = $plot->describeSide($side, true);
        }

        return $vars;
    }

    private function n(mixed $amount): string
    {
        return number_format((float) $amount, 2);
    }

    private function date(?CarbonInterface $date, ?string $calendar): string
    {
        return $date === null ? '' : ($calendar === 'hijri' ? Hijri::toString($date) : $date->toDateString());
    }

    private function day(?CarbonInterface $date): string
    {
        return $date === null ? '' : \Carbon\Carbon::instance($date)->locale('ar')->translatedFormat('l');
    }
}
