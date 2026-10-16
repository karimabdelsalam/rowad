<?php

namespace App\Services\Zatca\Phase2;

/** A signed, ready-to-send document: the XML, its hash (the next document's PIH) and the QR payload. */
final class SignedInvoice
{
    public function __construct(
        public readonly string $xml,
        public readonly string $hash,
        public readonly string $qr,
        public readonly string $uuid,
    ) {}
}
