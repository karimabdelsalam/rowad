<?php

namespace App\Services\Zatca\Phase2;

use App\Exceptions\BusinessRuleException;
use App\Models\ZatcaDevice;
use App\Support\Settings;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Registers the office's e-invoicing unit with ZATCA end to end: key and CSR, compliance CSID (with the
 * OTP from the Fatoora portal), the six compliance documents ZATCA requires (standard and simplified
 * invoice, credit and debit note), then the production CSID that live invoices are signed with.
 */
class Onboarding
{
    public function __construct(private readonly Signer $signer = new Signer) {}

    public function run(string $environment, string $otp): ZatcaDevice
    {
        if (! array_key_exists($environment, FatooraApi::ENVIRONMENTS)) {
            throw new BusinessRuleException(__('بيئة غير معروفة'));
        }
        if (! preg_match('/^\d{6}$/', trim($otp))) {
            throw new BusinessRuleException(__('رمز التحقق (OTP) من بوابة فاتورة مكوّن من 6 أرقام.'));
        }
        if ($problems = SellerProfile::problems()) {
            throw new BusinessRuleException(__('أكمل بيانات المنشأة أولًا: :list', ['list' => implode('، ', $problems)]));
        }

        $seller = SellerProfile::current();
        $keys = Csr::generate($environment, [
            'vat' => $seller['vat'],
            'organization' => Str::ascii((string) (Settings::get('site_name_en') ?: $seller['name'])) ?: 'Aqar Nova Office',
            'unit' => 'AqarNova',
            'serial' => '1-AqarNova|2-1.0|3-'.Str::uuid(),
            'address' => Str::ascii($seller['city'].' '.$seller['district']) ?: 'Saudi Arabia',
            'category' => 'Real Estate',
        ]);
        $device = ZatcaDevice::create([
            'environment' => $environment,
            'serial' => '1-AqarNova|2-1.0',
            'private_key' => $keys['private_key'],
            'csr' => $keys['csr'],
            'status' => ZatcaDevice::STATUS_NEW,
        ]);
        $log = [];

        $api = $device->api();
        $compliance = $api->complianceCsid($keys['csr'], trim($otp));
        $device->update([
            'compliance_token' => $compliance['binarySecurityToken'],
            'compliance_secret' => $compliance['secret'],
            'compliance_request_id' => (string) $compliance['requestID'],
            'status' => ZatcaDevice::STATUS_COMPLIANCE,
        ]);
        $log[] = ['step' => 'compliance_csid', 'result' => $compliance['dispositionMessage'] ?? 'ISSUED'];

        // the six sample documents run on their own short chain; live invoices start again at ICV 1
        $certificate = $device->certificate();
        $previous = InvoiceDocument::FIRST_PREVIOUS_HASH;
        foreach (self::samples($seller) as $i => $doc) {
            $doc = new InvoiceDocument($doc->number, (string) Str::uuid(), $doc->issuedAt, $doc->type, $doc->kind, $i + 1, $previous,
                $doc->seller, $doc->buyer, $doc->lines, $doc->billingReference, $doc->reason);
            $signed = $this->signer->sign($doc, $certificate, $device->private_key);
            $result = $api->complianceCheck($device->compliance_token, $device->compliance_secret, $signed);
            $status = $result['validationResults']['status'] ?? 'ERROR';
            $log[] = ['step' => $doc->type.'_'.$doc->kind, 'result' => $status, 'messages' => FatooraApi::messages($result)];
            if (! in_array($status, ['PASS', 'WARNING'], true)) {
                $device->update(['onboarding_log' => $log]);
                throw new ZatcaException(__('لم يجتز نموذج :doc فحص الامتثال.', ['doc' => $doc->number]), FatooraApi::messages($result));
            }
            $previous = $signed->hash;
        }

        $production = $api->productionCsid($device->compliance_token, $device->compliance_secret, $device->compliance_request_id);
        $expires = openssl_x509_parse(Certificate::fromBinarySecurityToken($production['binarySecurityToken'])->pem())['validTo_time_t'] ?? null;

        DB::transaction(function () use ($device, $production, $expires, $log) {
            ZatcaDevice::query()->where('status', ZatcaDevice::STATUS_READY)->whereKeyNot($device->id)->update(['status' => ZatcaDevice::STATUS_REVOKED]);
            $device->update([
                'production_token' => $production['binarySecurityToken'],
                'production_secret' => $production['secret'],
                'certificate_expires_at' => $expires ? Carbon::createFromTimestamp($expires) : null,
                'status' => ZatcaDevice::STATUS_READY,
                'counter' => 0,
                'last_hash' => InvoiceDocument::FIRST_PREVIOUS_HASH,
                'onboarded_at' => now(),
                'onboarding_log' => [...$log, ['step' => 'production_csid', 'result' => $production['dispositionMessage'] ?? 'ISSUED']],
            ]);
        });

        return $device->fresh();
    }

    /** @return list<InvoiceDocument> standard and simplified invoice, credit and debit note */
    public static function samples(array $seller): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Riyadh'));
        $business = ['name' => 'Sample Buyer Co', 'vat' => '399999999800003', 'street' => 'Prince Sultan St', 'building' => '5678', 'district' => 'Al Rawdah', 'city' => 'Jeddah', 'postal' => '23435'];
        $person = ['name' => 'Sample Tenant'];
        $lines = [
            ['name' => 'Commercial rent', 'quantity' => 1, 'unit_price' => 1000, 'vat_percent' => 15],
            ['name' => 'Residential rent', 'quantity' => 1, 'unit_price' => 1000, 'vat_percent' => 0],
        ];
        $docs = [];
        foreach ([InvoiceDocument::STANDARD => $business, InvoiceDocument::SIMPLIFIED => $person] as $type => $buyer) {
            foreach (['invoice', 'credit', 'debit'] as $kind) {
                $docs[] = new InvoiceDocument(strtoupper(substr($type, 0, 3)).'-'.strtoupper($kind).'-1', '', $now, $type, $kind, 0, '',
                    $seller, $buyer, $lines, $kind === 'invoice' ? null : strtoupper(substr($type, 0, 3)).'-INVOICE-1', $kind === 'invoice' ? null : 'Rent adjustment');
            }
        }

        return $docs;
    }
}
