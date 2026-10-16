<?php

namespace App\Services\Zatca\Phase2;

use InvalidArgumentException;

/** ZATCA QR payload: TLV (one-byte tag, one-byte length, value), base64. Phase 2 uses tags 1–9. */
final class Qr
{
    /** @param array<int, string> $fields */
    public static function encode(array $fields): string
    {
        $tlv = '';
        foreach ($fields as $tag => $value) {
            if (strlen($value) > 255) {
                throw new InvalidArgumentException("QR tag {$tag} is longer than 255 bytes.");
            }
            $tlv .= chr($tag).chr(strlen($value)).$value;
        }

        return base64_encode($tlv);
    }

    /** @return array<int, string> */
    public static function decode(string $payload): array
    {
        $bytes = (string) base64_decode($payload, true);
        $fields = [];
        for ($i = 0; $i + 1 < strlen($bytes);) {
            $length = ord($bytes[$i + 1]);
            $fields[ord($bytes[$i])] = substr($bytes, $i + 2, $length);
            $i += 2 + $length;
        }

        return $fields;
    }
}
