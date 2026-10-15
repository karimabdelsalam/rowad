<?php

namespace App\Services\Licensing;

use RuntimeException;

/**
 * Signed license tokens: base64url(json payload) . "." . base64url(Ed25519 signature).
 * The platform signs with its secret key; installations verify with the public key only,
 * so a token cannot be forged or edited (e.g. to extend the expiry) without the platform.
 */
final class LicenseToken
{
    public static function sign(array $payload, string $secretKeyBase64): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = sodium_crypto_sign_detached($json, self::decodeKey($secretKeyBase64, SODIUM_CRYPTO_SIGN_SECRETKEYBYTES));

        return self::b64($json).'.'.self::b64($signature);
    }

    /** @return array<string, mixed>|null the payload when the signature is valid */
    public static function verify(string $token, string $publicKeyBase64): ?array
    {
        $parts = explode('.', trim($token));
        if (count($parts) !== 2) {
            return null;
        }
        [$json, $signature] = [self::unb64($parts[0]), self::unb64($parts[1])];
        try {
            $key = self::decodeKey($publicKeyBase64, SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES);
            if ($json === null || $signature === null || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
                || ! sodium_crypto_sign_verify_detached($signature, $json, $key)) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        $payload = json_decode($json, true);

        return is_array($payload) ? $payload : null;
    }

    /** @return array{secret: string, public: string} base64 */
    public static function generateKeyPair(): array
    {
        $pair = sodium_crypto_sign_keypair();

        return [
            'secret' => base64_encode(sodium_crypto_sign_secretkey($pair)),
            'public' => base64_encode(sodium_crypto_sign_publickey($pair)),
        ];
    }

    public static function publicKeyFromSecret(string $secretKeyBase64): string
    {
        return base64_encode(sodium_crypto_sign_publickey_from_secretkey(self::decodeKey($secretKeyBase64, SODIUM_CRYPTO_SIGN_SECRETKEYBYTES)));
    }

    private static function decodeKey(string $base64, int $length): string
    {
        $key = base64_decode($base64, true);
        if ($key === false || strlen($key) !== $length) {
            throw new RuntimeException('Invalid license key material.');
        }

        return $key;
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function unb64(string $text): ?string
    {
        $raw = base64_decode(strtr($text, '-_', '+/'), true);

        return $raw === false ? null : $raw;
    }
}
