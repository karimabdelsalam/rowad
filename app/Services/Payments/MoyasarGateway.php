<?php

namespace App\Services\Payments;

use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Moyasar (Saudi gateway: mada, Visa/Mastercard, Apple Pay, STC Pay). Keys live in the office's settings. */
class MoyasarGateway implements PaymentGateway
{
    public const API = 'https://api.moyasar.com/v1';

    public function name(): string
    {
        return 'moyasar';
    }

    public function fetch(string $gatewayId): GatewayPayment
    {
        $secret = (string) Settings::get('moyasar_secret_key');
        if ($secret === '' || ! preg_match('/^[A-Za-z0-9_]{1,80}$/', $gatewayId)) {
            throw new RuntimeException('Moyasar is not configured or the payment id is malformed.');
        }
        $response = Http::withBasicAuth($secret, '')->acceptJson()->timeout(15)->get(self::API.'/payments/'.$gatewayId);
        if (! $response->successful()) {
            throw new RuntimeException('Moyasar answered '.$response->status());
        }
        $data = $response->json();
        $source = $data['source'] ?? [];

        return new GatewayPayment(
            id: (string) $data['id'],
            status: (string) ($data['status'] ?? 'unknown'),
            amountMinor: (int) ($data['amount'] ?? 0),
            currency: strtoupper((string) ($data['currency'] ?? 'SAR')),
            source: trim(($source['type'] ?? '').' '.($source['company'] ?? '')) ?: null,
            failure: $source['message'] ?? null,
            raw: $data,
        );
    }
}
