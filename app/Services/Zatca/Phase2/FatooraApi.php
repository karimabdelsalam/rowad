<?php

namespace App\Services\Zatca\Phase2;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/** ZATCA's e-invoicing (Fatoora) gateway: onboarding, compliance checks, reporting and clearance. */
class FatooraApi
{
    public const BASE = 'https://gw-fatoora.zatca.gov.sa/e-invoicing/';

    public const ENVIRONMENTS = [
        'developer-portal' => 'بيئة المطورين (تجريبية)',
        'simulation' => 'بيئة المحاكاة',
        'core' => 'البيئة الفعلية (الإنتاج)',
    ];

    public function __construct(private readonly string $environment) {}

    /** @return array{requestID: int|string, binarySecurityToken: string, secret: string} */
    public function complianceCsid(string $csrPem, string $otp): array
    {
        return $this->send('post', 'compliance', ['csr' => base64_encode($csrPem)], ['OTP' => $otp]);
    }

    public function complianceCheck(string $token, string $secret, SignedInvoice $invoice): array
    {
        return $this->send('post', 'compliance/invoices', self::payload($invoice), [], [$token, $secret]);
    }

    /** @return array{requestID: int|string, binarySecurityToken: string, secret: string} */
    public function productionCsid(string $token, string $secret, string $complianceRequestId): array
    {
        return $this->send('post', 'production/csids', ['compliance_request_id' => $complianceRequestId], [], [$token, $secret]);
    }

    public function report(string $token, string $secret, SignedInvoice $invoice): array
    {
        return $this->send('post', 'invoices/reporting/single', self::payload($invoice), ['Clearance-Status' => '0'], [$token, $secret]);
    }

    public function clear(string $token, string $secret, SignedInvoice $invoice): array
    {
        return $this->send('post', 'invoices/clearance/single', self::payload($invoice), ['Clearance-Status' => '1'], [$token, $secret]);
    }

    private static function payload(SignedInvoice $invoice): array
    {
        return ['invoiceHash' => $invoice->hash, 'uuid' => $invoice->uuid, 'invoice' => base64_encode($invoice->xml)];
    }

    /** @param array{0: string, 1: string}|null $auth binarySecurityToken and secret */
    private function send(string $method, string $path, array $body, array $headers = [], ?array $auth = null): array
    {
        $request = Http::acceptJson()->asJson()->timeout(30)
            ->withHeaders(['Accept-Version' => 'V2', 'Accept-Language' => app()->getLocale() === 'ar' ? 'ar' : 'en'] + $headers);
        if ($auth !== null) {
            $request = $request->withBasicAuth($auth[0], $auth[1]);
        }
        try {
            $response = $request->{$method}(self::BASE.$this->environment.'/'.$path, $body);
        } catch (ConnectionException $e) {
            throw new ZatcaException(__('تعذّر الاتصال بمنصة فاتورة: :error', ['error' => $e->getMessage()]));
        }

        $data = (array) $response->json();
        // 202 = accepted with warnings; 400 = rejected with validation errors (still a JSON body)
        if ($response->successful()) {
            return $data + ['_status' => $response->status()];
        }

        throw new ZatcaException(self::summary($response, $data), self::messages($data), $response->status(), $data);
    }

    /** @return list<string> */
    public static function messages(array $data): array
    {
        $messages = [];
        foreach (['errorMessages', 'warningMessages'] as $kind) {
            foreach ($data['validationResults'][$kind] ?? [] as $m) {
                $messages[] = trim(($m['code'] ?? '').': '.($m['message'] ?? ''), ': ');
            }
        }
        foreach ($data['errors'] ?? [] as $m) {
            $messages[] = is_array($m) ? trim(($m['code'] ?? '').': '.($m['message'] ?? ''), ': ') : (string) $m;
        }

        return $messages;
    }

    private static function summary(Response $response, array $data): string
    {
        return match (true) {
            $response->status() === 401 => __('رفضت منصة فاتورة بيانات الاعتماد (الشهادة أو الرمز السري).'),
            $response->status() >= 500 => __('منصة فاتورة غير متاحة حاليًا (:code).', ['code' => $response->status()]),
            default => (self::messages($data)[0] ?? ($data['message'] ?? __('رفضت منصة فاتورة الطلب (:code).', ['code' => $response->status()]))),
        };
    }
}
