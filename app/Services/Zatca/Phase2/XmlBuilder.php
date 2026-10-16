<?php

namespace App\Services\Zatca\Phase2;

/**
 * UBL 2.1 XML for ZATCA's integration phase, written compact (no whitespace between elements) so the
 * invoice hash is identical however a verifier strips the signature nodes. Placeholders mark where the
 * signer inserts the XAdES signature and the QR.
 */
final class XmlBuilder
{
    public const SIGNATURE_PLACEHOLDER = '<!--ZATCA-SIGNATURE-->';

    public const QR_PLACEHOLDER = '<!--ZATCA-QR-->';

    /** Residential rent is exempt (VAT Regulations art. 30); the exemption must be stated with its code. */
    public const EXEMPTION = ['VATEX-SA-30', 'Real estate transactions mentioned in Article 30 of the VAT Regulations'];

    public function build(InvoiceDocument $doc): string
    {
        $t = $doc->totals();
        $money = fn (float $v) => number_format($v, 2, '.', '');
        $cur = 'currencyID="SAR"';
        $x = fn (?string $v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2">';
        $xml .= self::SIGNATURE_PLACEHOLDER;
        $xml .= '<cbc:ProfileID>reporting:1.0</cbc:ProfileID>';
        $xml .= '<cbc:ID>'.$x($doc->number).'</cbc:ID>';
        $xml .= '<cbc:UUID>'.$x($doc->uuid).'</cbc:UUID>';
        $xml .= '<cbc:IssueDate>'.$doc->issuedAt->format('Y-m-d').'</cbc:IssueDate>';
        $xml .= '<cbc:IssueTime>'.$doc->issuedAt->format('H:i:s').'</cbc:IssueTime>';
        $xml .= '<cbc:InvoiceTypeCode name="'.($doc->isSimplified() ? '0200000' : '0100000').'">'.InvoiceDocument::TYPE_CODES[$doc->kind].'</cbc:InvoiceTypeCode>';
        $xml .= '<cbc:DocumentCurrencyCode>SAR</cbc:DocumentCurrencyCode>';
        $xml .= '<cbc:TaxCurrencyCode>SAR</cbc:TaxCurrencyCode>';
        if ($doc->kind !== 'invoice') {
            $xml .= '<cac:BillingReference><cac:InvoiceDocumentReference><cbc:ID>'.$x($doc->billingReference).'</cbc:ID></cac:InvoiceDocumentReference></cac:BillingReference>';
        }
        $xml .= '<cac:AdditionalDocumentReference><cbc:ID>ICV</cbc:ID><cbc:UUID>'.$doc->counter.'</cbc:UUID></cac:AdditionalDocumentReference>';
        $xml .= '<cac:AdditionalDocumentReference><cbc:ID>PIH</cbc:ID><cac:Attachment><cbc:EmbeddedDocumentBinaryObject mimeCode="text/plain">'.$doc->previousHash.'</cbc:EmbeddedDocumentBinaryObject></cac:Attachment></cac:AdditionalDocumentReference>';
        $xml .= self::QR_PLACEHOLDER;
        $xml .= '<cac:Signature><cbc:ID>urn:oasis:names:specification:ubl:signature:Invoice</cbc:ID><cbc:SignatureMethod>urn:oasis:names:specification:ubl:dsig:enveloped:xades</cbc:SignatureMethod></cac:Signature>';

        $s = $doc->seller;
        $xml .= '<cac:AccountingSupplierParty><cac:Party>';
        if (filled_value($s['crn'] ?? null)) {
            $xml .= '<cac:PartyIdentification><cbc:ID schemeID="CRN">'.$x($s['crn']).'</cbc:ID></cac:PartyIdentification>';
        }
        $xml .= self::address($s, $x);
        $xml .= '<cac:PartyTaxScheme><cbc:CompanyID>'.$x($s['vat']).'</cbc:CompanyID><cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:PartyTaxScheme>';
        $xml .= '<cac:PartyLegalEntity><cbc:RegistrationName>'.$x($s['name']).'</cbc:RegistrationName></cac:PartyLegalEntity>';
        $xml .= '</cac:Party></cac:AccountingSupplierParty>';

        $b = $doc->buyer ?? [];
        $xml .= '<cac:AccountingCustomerParty><cac:Party>';
        if (filled_value($b['crn'] ?? null)) {
            $xml .= '<cac:PartyIdentification><cbc:ID schemeID="CRN">'.$x($b['crn']).'</cbc:ID></cac:PartyIdentification>';
        }
        if (filled_value($b['street'] ?? null) || ! $doc->isSimplified()) {
            $xml .= self::address($b, $x);
        }
        if (filled_value($b['vat'] ?? null)) {
            $xml .= '<cac:PartyTaxScheme><cbc:CompanyID>'.$x($b['vat']).'</cbc:CompanyID><cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:PartyTaxScheme>';
        }
        if (filled_value($b['name'] ?? null)) {
            $xml .= '<cac:PartyLegalEntity><cbc:RegistrationName>'.$x($b['name']).'</cbc:RegistrationName></cac:PartyLegalEntity>';
        }
        $xml .= '</cac:Party></cac:AccountingCustomerParty>';

        if (! $doc->isSimplified()) {
            $xml .= '<cac:Delivery><cbc:ActualDeliveryDate>'.$doc->issuedAt->format('Y-m-d').'</cbc:ActualDeliveryDate></cac:Delivery>';
        }
        $xml .= '<cac:PaymentMeans><cbc:PaymentMeansCode>'.$x($doc->paymentMeansCode).'</cbc:PaymentMeansCode>';
        if ($doc->kind !== 'invoice') {
            $xml .= '<cbc:InstructionNote>'.$x($doc->reason ?: 'Adjustment').'</cbc:InstructionNote>';
        }
        $xml .= '</cac:PaymentMeans>';

        // VAT once in the tax currency without breakdown, once with the breakdown per category
        $xml .= '<cac:TaxTotal><cbc:TaxAmount '.$cur.'>'.$money($t['vat']).'</cbc:TaxAmount></cac:TaxTotal>';
        $xml .= '<cac:TaxTotal><cbc:TaxAmount '.$cur.'>'.$money($t['vat']).'</cbc:TaxAmount>';
        foreach ($t['groups'] as $group) {
            $xml .= '<cac:TaxSubtotal><cbc:TaxableAmount '.$cur.'>'.$money($group['taxable']).'</cbc:TaxableAmount><cbc:TaxAmount '.$cur.'>'.$money($group['vat']).'</cbc:TaxAmount>';
            $xml .= self::taxCategory('TaxCategory', $group['category'], $group['percent'], true);
            $xml .= '</cac:TaxSubtotal>';
        }
        $xml .= '</cac:TaxTotal>';

        $xml .= '<cac:LegalMonetaryTotal>';
        $xml .= '<cbc:LineExtensionAmount '.$cur.'>'.$money($t['net']).'</cbc:LineExtensionAmount>';
        $xml .= '<cbc:TaxExclusiveAmount '.$cur.'>'.$money($t['net']).'</cbc:TaxExclusiveAmount>';
        $xml .= '<cbc:TaxInclusiveAmount '.$cur.'>'.$money($t['total']).'</cbc:TaxInclusiveAmount>';
        $xml .= '<cbc:AllowanceTotalAmount '.$cur.'>0.00</cbc:AllowanceTotalAmount>';
        $xml .= '<cbc:PrepaidAmount '.$cur.'>0.00</cbc:PrepaidAmount>';
        $xml .= '<cbc:PayableAmount '.$cur.'>'.$money($t['total']).'</cbc:PayableAmount>';
        $xml .= '</cac:LegalMonetaryTotal>';

        foreach (array_values($doc->lines) as $i => $line) {
            $amount = round($line['quantity'] * $line['unit_price'], 2);
            $percent = round((float) $line['vat_percent'], 2);
            $vat = round($amount * $percent / 100, 2);
            $xml .= '<cac:InvoiceLine><cbc:ID>'.($i + 1).'</cbc:ID>';
            $xml .= '<cbc:InvoicedQuantity unitCode="PCE">'.number_format($line['quantity'], 6, '.', '').'</cbc:InvoicedQuantity>';
            $xml .= '<cbc:LineExtensionAmount '.$cur.'>'.$money($amount).'</cbc:LineExtensionAmount>';
            $xml .= '<cac:TaxTotal><cbc:TaxAmount '.$cur.'>'.$money($vat).'</cbc:TaxAmount><cbc:RoundingAmount '.$cur.'>'.$money($amount + $vat).'</cbc:RoundingAmount></cac:TaxTotal>';
            $xml .= '<cac:Item><cbc:Name>'.$x(mb_substr($line['name'], 0, 1000)).'</cbc:Name>'.self::taxCategory('ClassifiedTaxCategory', $percent > 0 ? 'S' : 'E', $percent, false).'</cac:Item>';
            $xml .= '<cac:Price><cbc:PriceAmount '.$cur.'>'.$money($line['unit_price']).'</cbc:PriceAmount></cac:Price>';
            $xml .= '</cac:InvoiceLine>';
        }

        return $xml.'</Invoice>';
    }

    private static function address(array $a, \Closure $x): string
    {
        return '<cac:PostalAddress>'
            .'<cbc:StreetName>'.$x($a['street'] ?? '').'</cbc:StreetName>'
            .'<cbc:BuildingNumber>'.$x($a['building'] ?? '').'</cbc:BuildingNumber>'
            .'<cbc:CitySubdivisionName>'.$x($a['district'] ?? '').'</cbc:CitySubdivisionName>'
            .'<cbc:CityName>'.$x($a['city'] ?? '').'</cbc:CityName>'
            .'<cbc:PostalZone>'.$x($a['postal'] ?? '').'</cbc:PostalZone>'
            .'<cac:Country><cbc:IdentificationCode>SA</cbc:IdentificationCode></cac:Country>'
            .'</cac:PostalAddress>';
    }

    private static function taxCategory(string $element, string $category, float $percent, bool $withReason): string
    {
        $xml = '<cac:'.$element.'><cbc:ID>'.$category.'</cbc:ID><cbc:Percent>'.number_format($percent, 2, '.', '').'</cbc:Percent>';
        if ($withReason && $category === 'E') {
            $xml .= '<cbc:TaxExemptionReasonCode>'.self::EXEMPTION[0].'</cbc:TaxExemptionReasonCode><cbc:TaxExemptionReason>'.self::EXEMPTION[1].'</cbc:TaxExemptionReason>';
        }

        return $xml.'<cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:'.$element.'>';
    }
}

if (! function_exists(__NAMESPACE__.'\filled_value')) {
    function filled_value(mixed $value): bool
    {
        return $value !== null && trim((string) $value) !== '';
    }
}
