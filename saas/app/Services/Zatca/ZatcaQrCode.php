<?php

namespace App\Services\Zatca;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * ZATCA (Fatoora) phase-1 QR payload: TLV-encoded fields, base64.
 * Replaces the legacy Salla\ZATCA\GenerateQrCode usage in invoices::taxbill.
 *
 * Tags: 1 seller name, 2 VAT number, 3 timestamp (ISO 8601), 4 total incl. VAT, 5 VAT total.
 */
final class ZatcaQrCode
{
    public static function payload(string $sellerName, string $vatNumber, DateTimeInterface $issuedAt, float $total, float $vatTotal): string
    {
        if (! preg_match('/^3\d{13}3$/', $vatNumber)) {
            throw new InvalidArgumentException('VAT number must be 15 digits starting and ending with 3.');
        }

        $fields = [
            1 => $sellerName,
            2 => $vatNumber,
            3 => $issuedAt->format('Y-m-d\TH:i:s\Z'),
            4 => number_format($total, 2, '.', ''),
            5 => number_format($vatTotal, 2, '.', ''),
        ];

        $tlv = '';
        foreach ($fields as $tag => $value) {
            $length = strlen($value);
            if ($length > 255) {
                throw new InvalidArgumentException("ZATCA field {$tag} is too long.");
            }
            $tlv .= chr($tag).chr($length).$value;
        }

        return base64_encode($tlv);
    }

    /** @return array<int, string> */
    public static function decode(string $payload): array
    {
        $bytes = base64_decode($payload, true);
        $fields = [];
        for ($i = 0; $i < strlen($bytes);) {
            $tag = ord($bytes[$i]);
            $length = ord($bytes[$i + 1]);
            $fields[$tag] = substr($bytes, $i + 2, $length);
            $i += 2 + $length;
        }

        return $fields;
    }
}
