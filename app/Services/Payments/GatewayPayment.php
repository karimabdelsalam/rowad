<?php

namespace App\Services\Payments;

/** What a gateway reports about one payment, normalised across providers. */
final class GatewayPayment
{
    public function __construct(
        public readonly string $id,
        public readonly string $status,      // paid, failed, initiated, authorized, refunded…
        public readonly int $amountMinor,    // halalas
        public readonly string $currency,
        public readonly ?string $source,
        public readonly ?string $failure,
        public readonly array $raw,
    ) {}

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
