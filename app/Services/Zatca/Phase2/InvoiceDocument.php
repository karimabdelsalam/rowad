<?php

namespace App\Services\Zatca\Phase2;

use DateTimeImmutable;

/**
 * Everything ZATCA needs to know about one invoice, credit note or debit note, independent of how the
 * office stored it. Amounts are SAR; lines carry their own VAT rate (15% standard, 0% = exempt rent).
 */
final class InvoiceDocument
{
    public const SIMPLIFIED = 'simplified'; // B2C — reported within 24 hours

    public const STANDARD = 'standard';     // B2B — cleared by ZATCA before it reaches the buyer

    public const TYPE_CODES = ['invoice' => '388', 'debit' => '383', 'credit' => '381'];

    /**
     * @param  array{name: string, vat: string, crn?: ?string, street: string, building: string, district: string, city: string, postal: string}  $seller
     * @param  array{name?: ?string, vat?: ?string, crn?: ?string, street?: ?string, building?: ?string, district?: ?string, city?: ?string, postal?: ?string}|null  $buyer
     * @param  list<array{name: string, quantity: float, unit_price: float, vat_percent: float}>  $lines
     */
    public function __construct(
        public readonly string $number,
        public readonly string $uuid,
        public readonly DateTimeImmutable $issuedAt,
        public readonly string $type,
        public readonly string $kind,
        public readonly int $counter,
        public readonly string $previousHash,
        public readonly array $seller,
        public readonly ?array $buyer,
        public readonly array $lines,
        public readonly ?string $billingReference = null,
        public readonly ?string $reason = null,
        public readonly string $paymentMeansCode = '10',
    ) {}

    /** The hash chain starts from SHA-256("0") (hex, base64) — ZATCA's value for the first document of a unit. */
    public const FIRST_PREVIOUS_HASH = 'NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==';

    public function isSimplified(): bool
    {
        return $this->type === self::SIMPLIFIED;
    }

    /** @return array{net: float, vat: float, total: float, groups: array<string, array{category: string, percent: float, taxable: float, vat: float}>} */
    public function totals(): array
    {
        $groups = [];
        $net = 0.0;
        foreach ($this->lines as $line) {
            $amount = round($line['quantity'] * $line['unit_price'], 2);
            $percent = round((float) $line['vat_percent'], 2);
            $key = number_format($percent, 2, '.', '');
            $groups[$key] ??= ['category' => $percent > 0 ? 'S' : 'E', 'percent' => $percent, 'taxable' => 0.0, 'vat' => 0.0];
            $groups[$key]['taxable'] = round($groups[$key]['taxable'] + $amount, 2);
            $net = round($net + $amount, 2);
        }
        $vat = 0.0;
        foreach ($groups as $key => $group) {
            $groups[$key]['vat'] = round($group['taxable'] * $group['percent'] / 100, 2);
            $vat = round($vat + $groups[$key]['vat'], 2);
        }

        return ['net' => $net, 'vat' => $vat, 'total' => round($net + $vat, 2), 'groups' => $groups];
    }
}
