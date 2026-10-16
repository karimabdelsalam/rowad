<?php

namespace App\Services\Zatca\Phase2;

use InvalidArgumentException;

/**
 * The CSID (X.509) ZATCA issued to this office's e-invoicing unit, in the shapes the signature needs:
 * issuer name and serial for XAdES, a hash of its base64 text, its public key and its own signature for the QR.
 */
final class Certificate
{
    private function __construct(private readonly string $base64) {}

    /** @param string $base64 the certificate body as ZATCA returns it once binarySecurityToken is base64-decoded ("MII…") */
    public static function fromBase64(string $base64): self
    {
        $base64 = preg_replace('/\s+|-----[^-]+-----/', '', $base64);
        if (base64_decode($base64, true) === false || openssl_x509_read(self::toPem($base64)) === false) {
            throw new InvalidArgumentException('Not a valid certificate.');
        }

        return new self($base64);
    }

    /** ZATCA's binarySecurityToken is the base64 of the certificate's base64 text. */
    public static function fromBinarySecurityToken(string $token): self
    {
        return self::fromBase64((string) base64_decode($token, true));
    }

    public function base64(): string
    {
        return $this->base64;
    }

    public function pem(): string
    {
        return self::toPem($this->base64);
    }

    /** base64 of the hex SHA-256 of the certificate's base64 text (ZATCA's CertDigest). */
    public function hash(): string
    {
        return base64_encode(hash('sha256', $this->base64));
    }

    /** "CN=…, DC=…" — most specific RDN first, as ZATCA expects in X509IssuerName. */
    public function issuerName(): string
    {
        $issuer = openssl_x509_parse($this->pem(), false)['issuer'] ?? [];
        $short = ['commonName' => 'CN', 'domainComponent' => 'DC', 'organizationName' => 'O', 'organizationalUnitName' => 'OU', 'countryName' => 'C', 'localityName' => 'L', 'stateOrProvinceName' => 'ST'];
        $parts = [];
        foreach ($issuer as $name => $value) {
            foreach ((array) $value as $v) {
                $parts[] = ($short[$name] ?? $name).'='.$v;
            }
        }

        return implode(', ', array_reverse($parts));
    }

    /** Decimal serial number — ZATCA's CA issues serials far larger than a PHP integer. */
    public function serialNumber(): string
    {
        $hex = (string) (openssl_x509_parse($this->pem())['serialNumberHex'] ?? '');

        return self::hexToDecimal($hex);
    }

    /** Arbitrary-precision base conversion without bcmath/gmp. */
    private static function hexToDecimal(string $hex): string
    {
        $digits = [0]; // little-endian base-10 digits
        foreach (str_split(ltrim(strtolower($hex), '0') ?: '0') as $char) {
            $carry = hexdec($char);
            foreach ($digits as $i => $digit) {
                $value = $digit * 16 + $carry;
                $digits[$i] = $value % 10;
                $carry = intdiv($value, 10);
            }
            while ($carry > 0) {
                $digits[] = $carry % 10;
                $carry = intdiv($carry, 10);
            }
        }

        return implode('', array_reverse($digits));
    }

    /** SubjectPublicKeyInfo in DER (QR tag 8). */
    public function publicKeyDer(): string
    {
        $pem = openssl_pkey_get_details(openssl_pkey_get_public($this->pem()))['key'];

        return base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $pem));
    }

    /** The issuing CA's signature over this certificate, raw DER bytes (QR tag 9). */
    public function signatureBytes(): string
    {
        $der = base64_decode($this->base64);
        // Certificate ::= SEQUENCE { tbsCertificate, signatureAlgorithm, signatureValue BIT STRING }
        [, $offset] = self::header($der, 0);              // outer SEQUENCE: step inside
        [$len, $start] = self::header($der, $offset);      // tbsCertificate
        $offset = $start + $len;
        [$len, $start] = self::header($der, $offset);      // signatureAlgorithm
        $offset = $start + $len;
        [$len, $start] = self::header($der, $offset);      // signatureValue BIT STRING

        return substr($der, $start + 1, $len - 1);         // drop the "unused bits" byte
    }

    /** @return array{int, int} length and offset of the value of the TLV at $offset */
    private static function header(string $der, int $offset): array
    {
        $length = ord($der[$offset + 1]);
        $pos = $offset + 2;
        if ($length & 0x80) {
            $bytes = $length & 0x7F;
            $length = 0;
            for ($i = 0; $i < $bytes; $i++) {
                $length = ($length << 8) | ord($der[$pos + $i]);
            }
            $pos += $bytes;
        }

        return [$length, $pos];
    }

    private static function toPem(string $base64): string
    {
        return "-----BEGIN CERTIFICATE-----\n".chunk_split($base64, 64, "\n")."-----END CERTIFICATE-----\n";
    }
}
