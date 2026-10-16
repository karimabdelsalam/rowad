<?php

namespace App\Services\Zatca\Phase2;

use RuntimeException;

/**
 * Key pair and certificate signing request for one e-invoicing unit (EGS), in ZATCA's required shape:
 * secp256k1, the certificate template name for the target environment, and the EGS details in the
 * subject alternative name (serial, VAT number, invoice types, address, business category).
 */
final class Csr
{
    public const TEMPLATES = [
        'developer-portal' => 'TSTZATCA-Code-Signing',
        'simulation' => 'PREZATCA-Code-Signing',
        'core' => 'ZATCA-Code-Signing',
    ];

    /**
     * @param  array{vat: string, organization: string, unit: string, serial: string, address: string, category: string, types?: string}  $egs
     * @return array{private_key: string, csr: string}
     */
    public static function generate(string $environment, array $egs): array
    {
        $template = self::TEMPLATES[$environment] ?? throw new RuntimeException("Unknown ZATCA environment {$environment}.");
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'secp256k1']);
        if ($key === false || ! openssl_pkey_export($key, $privateKey)) {
            throw new RuntimeException('Could not create the secp256k1 key: '.openssl_error_string());
        }

        $v = fn (string $s) => str_replace(["\n", "\r", '"'], ' ', trim($s));
        $config = implode("\n", [
            'oid_section = OIDs',
            '[ OIDs ]',
            'certificateTemplateName = 1.3.6.1.4.1.311.20.2',
            '[ req ]',
            'prompt = no',
            'default_md = sha256',
            'req_extensions = req_ext',
            'distinguished_name = dn',
            '[ dn ]',
            'C = SA',
            '[ req_ext ]',
            'certificateTemplateName = ASN1:PRINTABLESTRING:'.$template,
            'subjectAltName = dirName:alt_names',
            '[ alt_names ]',
            'SN = '.$v($egs['serial']),
            'UID = '.$v($egs['vat']),
            'title = '.($egs['types'] ?? '1100'),
            'registeredAddress = '.$v($egs['address']),
            'businessCategory = '.$v($egs['category']),
        ])."\n";
        $file = tempnam(sys_get_temp_dir(), 'zatca-csr');
        file_put_contents($file, $config);
        try {
            $csr = openssl_csr_new(
                ['countryName' => 'SA', 'organizationalUnitName' => $v($egs['unit']), 'organizationName' => $v($egs['organization']), 'commonName' => $v($egs['unit']).'-'.$v($egs['vat'])],
                $key,
                ['config' => $file, 'req_extensions' => 'req_ext', 'digest_alg' => 'sha256'],
            );
            if ($csr === false || ! openssl_csr_export($csr, $csrPem)) {
                throw new RuntimeException('Could not create the CSR: '.openssl_error_string());
            }
        } finally {
            @unlink($file);
        }

        return ['private_key' => $privateKey, 'csr' => $csrPem];
    }
}
