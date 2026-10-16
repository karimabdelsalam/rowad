<?php

namespace App\Services\Zatca\Phase2;

use App\Models\Invoice;
use App\Models\ZatcaDevice;
use App\Support\PlanLimits;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

/**
 * Integration phase for live invoices. Each document is signed when issued — taking the next counter and
 * chaining the previous document's hash, under a lock so two cashiers never fork the chain — then sent:
 * simplified (B2C) documents are reported, standard (B2B) documents are cleared and ZATCA's stamped XML
 * and QR replace ours.
 */
class ZatcaInvoicing
{
    public const STATUSES = [
        'pending' => 'بانتظار الإرسال',
        'reported' => 'تم الإبلاغ',
        'cleared' => 'معتمدة من زاتكا',
        'rejected' => 'مرفوضة',
        'failed' => 'تعذّر الإرسال — ستُعاد المحاولة',
    ];

    /** Give up automatic retries after this many network/server failures; staff can still resend. */
    public const MAX_ATTEMPTS = 20;

    public function __construct(private readonly Signer $signer = new Signer) {}

    public static function enabled(): bool
    {
        return tenancy()->initialized && PlanLimits::allows('zatca') && (ZatcaDevice::active()?->isReady() ?? false);
    }

    /** Sign the invoice on the unit's chain; does nothing if it is already signed or phase 2 is off. */
    public function sign(Invoice $invoice): Invoice
    {
        if (filled($invoice->zatca_xml) || ! self::enabled()) {
            return $invoice;
        }

        return DB::transaction(function () use ($invoice) {
            /** @var ZatcaDevice $device */
            $device = ZatcaDevice::query()->whereKey(ZatcaDevice::active()->id)->lockForUpdate()->firstOrFail();
            $counter = $device->counter + 1;
            $document = self::document($invoice->loadMissing('client'), $counter, $device->last_hash ?: InvoiceDocument::FIRST_PREVIOUS_HASH);
            $signed = $this->signer->sign($document, $device->certificate(), $device->private_key);

            $invoice->forceFill([
                'zatca_device_id' => $device->id,
                'zatca_icv' => $counter,
                'zatca_xml' => $signed->xml,
                'zatca_hash' => $signed->hash,
                'zatca_qr' => $signed->qr,
                'zatca_status' => 'pending',
                'issued_at' => $invoice->issued_at ?? now(),
            ])->save();
            $device->update(['counter' => $counter, 'last_hash' => $signed->hash]);

            return $invoice;
        });
    }

    /** Report or clear a signed invoice and record ZATCA's answer. */
    public function submit(Invoice $invoice): Invoice
    {
        if (blank($invoice->zatca_xml) || in_array($invoice->zatca_status, ['reported', 'cleared', 'rejected'], true)) {
            return $invoice;
        }
        $device = ZatcaDevice::find($invoice->zatca_device_id);
        if ($device === null || ! $device->isReady()) {
            return $invoice;
        }
        $signed = new SignedInvoice($invoice->zatca_xml, $invoice->zatca_hash, (string) $invoice->zatca_qr, $invoice->zatca_uuid);
        [$token, $secret] = $device->productionCredentials();
        $fields = ['zatca_attempts' => $invoice->zatca_attempts + 1, 'zatca_submitted_at' => now()];

        try {
            if ($device->isSandbox()) {
                $result = $device->api()->complianceCheck($token, $secret, $signed);
                if (! in_array($result['validationResults']['status'] ?? null, ['PASS', 'WARNING'], true)) {
                    throw new ZatcaException(__('رفضت منصة فاتورة الطلب (:code).', ['code' => 400]), FatooraApi::messages($result), 400, $result);
                }
                $fields['zatca_status'] = $invoice->type === InvoiceDocument::STANDARD ? 'cleared' : 'reported';
            } elseif ($invoice->type === InvoiceDocument::STANDARD) {
                $result = $device->api()->clear($token, $secret, $signed);
                $fields['zatca_status'] = 'cleared';
                if (filled($result['clearedInvoice'] ?? null)) {
                    $cleared = (string) base64_decode($result['clearedInvoice']);
                    $fields['zatca_xml'] = $cleared;
                    $fields['zatca_qr'] = self::qrFrom($cleared) ?? $invoice->zatca_qr;
                }
            } else {
                $result = $device->api()->report($token, $secret, $signed);
                $fields['zatca_status'] = 'reported';
            }
            $fields['zatca_messages'] = FatooraApi::messages($result) ?: null;
        } catch (ZatcaException $e) {
            // 400 = ZATCA rejected the document (fix with a credit note and a new invoice); 409 = already accepted
            $fields['zatca_status'] = match ($e->status) {
                400 => 'rejected',
                409 => $invoice->type === InvoiceDocument::STANDARD ? 'cleared' : 'reported',
                default => 'failed',
            };
            $fields['zatca_messages'] = $e->messages ?: [$e->getMessage()];
        }
        $invoice->forceFill($fields)->save();

        return $invoice;
    }

    public static function document(Invoice $invoice, int $counter, string $previousHash): InvoiceDocument
    {
        $client = $invoice->client;
        $address = (array) ($client?->national_address ?? []);
        $issued = DateTimeImmutable::createFromInterface($invoice->issued_at ?? $invoice->created_at ?? now())->setTimezone(new DateTimeZone('Asia/Riyadh'));

        return new InvoiceDocument(
            number: $invoice->number,
            uuid: $invoice->zatca_uuid,
            issuedAt: $issued,
            type: $invoice->type === InvoiceDocument::STANDARD ? InvoiceDocument::STANDARD : InvoiceDocument::SIMPLIFIED,
            kind: $invoice->kind ?: 'invoice',
            counter: $counter,
            previousHash: $previousHash,
            seller: SellerProfile::current(),
            buyer: array_filter([
                'name' => $invoice->customer_name ?: $client?->displayName(),
                'vat' => $invoice->customer_vat_number,
                'crn' => $client?->company_registration_number,
                'street' => $address['street'] ?? null,
                'building' => $address['building'] ?? null,
                'district' => $address['district'] ?? null,
                'city' => $address['city'] ?? null,
                'postal' => $address['postal'] ?? null,
            ], fn ($v) => filled($v)),
            lines: array_map(fn (array $item) => [
                'name' => (string) ($item['description'] ?? ''),
                'quantity' => (float) ($item['quantity'] ?? 1),
                'unit_price' => (float) $item['unit_price'],
                'vat_percent' => (float) ($item['vat_percent'] ?? self::percentOf($item)),
            ], array_values((array) $invoice->items)),
            billingReference: $invoice->billing_reference,
            reason: $invoice->reason,
        );
    }

    /** The QR ZATCA stamped into a cleared invoice. */
    public static function qrFrom(string $xml): ?string
    {
        return preg_match('#<cbc:ID>QR</cbc:ID>\s*<cac:Attachment>\s*<cbc:EmbeddedDocumentBinaryObject[^>]*>([^<]+)</cbc:EmbeddedDocumentBinaryObject>#', $xml, $m) ? trim($m[1]) : null;
    }

    private static function percentOf(array $item): float
    {
        $net = (float) $item['unit_price'] * (float) ($item['quantity'] ?? 1);

        return $net > 0 ? round((float) ($item['vat_amount'] ?? 0) / $net * 100) : 0.0;
    }
}
