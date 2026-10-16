<?php

namespace App\Services\Zatca\Phase2;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMXPath;
use RuntimeException;

/**
 * Signs a UBL document the way ZATCA verifies it: SHA-256 of the canonical invoice (without the signature
 * extension, the cac:Signature reference and the QR), an ECDSA (secp256k1) signature of that hash, XAdES
 * signed properties naming the CSID, and the phase-2 QR (TLV tags 1–9) built from all of the above.
 */
final class Signer
{
    private const NS = [
        'ubl' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
        'cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
        'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
        'ext' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2',
    ];

    public function __construct(private readonly XmlBuilder $builder = new XmlBuilder) {}

    public function sign(InvoiceDocument $doc, Certificate $certificate, string $privateKeyPem, ?DateTimeImmutable $signingTime = null): SignedInvoice
    {
        $unsigned = $this->builder->build($doc);
        $hash = self::invoiceHash(str_replace([XmlBuilder::SIGNATURE_PLACEHOLDER, XmlBuilder::QR_PLACEHOLDER], '', $unsigned));

        $key = openssl_pkey_get_private($privateKeyPem);
        if ($key === false || ! openssl_sign(base64_decode($hash), $raw, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign the invoice with the unit\'s private key.');
        }
        $signature = base64_encode($raw);

        $time = ($signingTime ?? new DateTimeImmutable('now', new DateTimeZone('Asia/Riyadh')))->format('Y-m-d\TH:i:s');
        $signedProperties = self::signedProperties($time, $certificate);
        $propertiesHash = base64_encode(hash('sha256', $signedProperties));

        $t = $doc->totals();
        $qr = Qr::encode([
            1 => $doc->seller['name'],
            2 => $doc->seller['vat'],
            3 => $doc->issuedAt->format('Y-m-d\TH:i:s'),
            4 => number_format($t['total'], 2, '.', ''),
            5 => number_format($t['vat'], 2, '.', ''),
            6 => $hash,
            7 => $signature,
            8 => $certificate->publicKeyDer(),
            9 => $certificate->signatureBytes(),
        ]);

        $xml = str_replace(
            [XmlBuilder::SIGNATURE_PLACEHOLDER, XmlBuilder::QR_PLACEHOLDER],
            [self::extension($hash, $propertiesHash, $signature, $certificate, $signedProperties), self::qrReference($qr)],
            $unsigned,
        );

        if (self::invoiceHash($xml) !== $hash) { // the verifier strips these nodes and must land on the same bytes
            throw new RuntimeException('Invoice hash is not reproducible from the signed XML.');
        }

        return new SignedInvoice($xml, $hash, $qr, $doc->uuid);
    }

    /** base64 SHA-256 of the canonical invoice without UBLExtensions, cac:Signature and the QR reference. */
    public static function invoiceHash(string $xml): string
    {
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = true;
        if (! $dom->loadXML($xml)) {
            throw new RuntimeException('Invalid invoice XML.');
        }
        $xpath = new DOMXPath($dom);
        foreach (self::NS as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }
        foreach (['/ubl:Invoice/ext:UBLExtensions', '/ubl:Invoice/cac:Signature', "/ubl:Invoice/cac:AdditionalDocumentReference[cbc:ID='QR']"] as $path) {
            foreach (iterator_to_array($xpath->query($path)) as $node) {
                $node->parentNode->removeChild($node);
            }
        }

        return base64_encode(hash('sha256', $dom->documentElement->C14N(false, false), true));
    }

    /**
     * The XAdES SignedProperties as ZATCA canonicalises them: the SDK's indentation, with the ds namespace
     * declared on each element that uses it (exclusive canonical form of the block inside the document).
     */
    private static function signedProperties(string $time, Certificate $certificate): string
    {
        $ds = 'xmlns:ds="http://www.w3.org/2000/09/xmldsig#"';
        $i = fn (int $n) => "\n".str_repeat(' ', $n);

        return '<xades:SignedProperties xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" Id="xadesSignedProperties">'
            .$i(36).'<xades:SignedSignatureProperties>'
            .$i(40).'<xades:SigningTime>'.$time.'</xades:SigningTime>'
            .$i(40).'<xades:SigningCertificate>'
            .$i(44).'<xades:Cert>'
            .$i(48).'<xades:CertDigest>'
            .$i(52).'<ds:DigestMethod '.$ds.' Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"></ds:DigestMethod>'
            .$i(52).'<ds:DigestValue '.$ds.'>'.$certificate->hash().'</ds:DigestValue>'
            .$i(48).'</xades:CertDigest>'
            .$i(48).'<xades:IssuerSerial>'
            .$i(52).'<ds:X509IssuerName '.$ds.'>'.htmlspecialchars($certificate->issuerName(), ENT_XML1).'</ds:X509IssuerName>'
            .$i(52).'<ds:X509SerialNumber '.$ds.'>'.$certificate->serialNumber().'</ds:X509SerialNumber>'
            .$i(48).'</xades:IssuerSerial>'
            .$i(44).'</xades:Cert>'
            .$i(40).'</xades:SigningCertificate>'
            .$i(36).'</xades:SignedSignatureProperties>'
            .$i(32).'</xades:SignedProperties>';
    }

    private static function extension(string $hash, string $propertiesHash, string $signature, Certificate $certificate, string $signedProperties): string
    {
        // inside the document ds/xades are declared on ancestors, so the properties are written without the per-element declarations
        $properties = str_replace([' xmlns:ds="http://www.w3.org/2000/09/xmldsig#"', ' xmlns:xades="http://uri.etsi.org/01903/v1.3.2#"'], '', $signedProperties);
        $xpath = fn (string $expr) => '<ds:Transform Algorithm="http://www.w3.org/TR/1999/REC-xpath-19991116"><ds:XPath>'.$expr.'</ds:XPath></ds:Transform>';

        return '<ext:UBLExtensions><ext:UBLExtension>'
            .'<ext:ExtensionURI>urn:oasis:names:specification:ubl:dsig:enveloped:xades</ext:ExtensionURI>'
            .'<ext:ExtensionContent>'
            .'<sig:UBLDocumentSignatures xmlns:sig="urn:oasis:names:specification:ubl:schema:xsd:CommonSignatureComponents-2" xmlns:sac="urn:oasis:names:specification:ubl:schema:xsd:SignatureAggregateComponents-2" xmlns:sbc="urn:oasis:names:specification:ubl:schema:xsd:SignatureBasicComponents-2">'
            .'<sac:SignatureInformation>'
            .'<cbc:ID>urn:oasis:names:specification:ubl:signature:1</cbc:ID>'
            .'<sbc:ReferencedSignatureID>urn:oasis:names:specification:ubl:signature:Invoice</sbc:ReferencedSignatureID>'
            .'<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" Id="signature">'
            .'<ds:SignedInfo>'
            .'<ds:CanonicalizationMethod Algorithm="http://www.w3.org/2006/12/xml-c14n11"></ds:CanonicalizationMethod>'
            .'<ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#ecdsa-sha256"></ds:SignatureMethod>'
            .'<ds:Reference Id="invoiceSignedData" URI="">'
            .'<ds:Transforms>'
            .$xpath('not(//ancestor-or-self::ext:UBLExtensions)')
            .$xpath('not(//ancestor-or-self::cac:Signature)')
            .$xpath("not(//ancestor-or-self::cac:AdditionalDocumentReference[cbc:ID='QR'])")
            .'<ds:Transform Algorithm="http://www.w3.org/2006/12/xml-c14n11"></ds:Transform>'
            .'</ds:Transforms>'
            .'<ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"></ds:DigestMethod>'
            .'<ds:DigestValue>'.$hash.'</ds:DigestValue>'
            .'</ds:Reference>'
            .'<ds:Reference Type="http://www.w3.org/2000/09/xmldsig#SignatureProperties" URI="#xadesSignedProperties">'
            .'<ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"></ds:DigestMethod>'
            .'<ds:DigestValue>'.$propertiesHash.'</ds:DigestValue>'
            .'</ds:Reference>'
            .'</ds:SignedInfo>'
            .'<ds:SignatureValue>'.$signature.'</ds:SignatureValue>'
            .'<ds:KeyInfo><ds:X509Data><ds:X509Certificate>'.$certificate->base64().'</ds:X509Certificate></ds:X509Data></ds:KeyInfo>'
            .'<ds:Object><xades:QualifyingProperties xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" Target="signature">'.$properties.'</xades:QualifyingProperties></ds:Object>'
            .'</ds:Signature>'
            .'</sac:SignatureInformation>'
            .'</sig:UBLDocumentSignatures>'
            .'</ext:ExtensionContent>'
            .'</ext:UBLExtension></ext:UBLExtensions>';
    }

    private static function qrReference(string $qr): string
    {
        return '<cac:AdditionalDocumentReference><cbc:ID>QR</cbc:ID><cac:Attachment><cbc:EmbeddedDocumentBinaryObject mimeCode="text/plain">'.$qr.'</cbc:EmbeddedDocumentBinaryObject></cac:Attachment></cac:AdditionalDocumentReference>';
    }
}
