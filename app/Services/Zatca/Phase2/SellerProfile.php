<?php

namespace App\Services\Zatca\Phase2;

use App\Support\Settings;

/** The office as ZATCA must see it on every document: legal name, VAT, CR and national address. */
final class SellerProfile
{
    /** setting key => label shown when it is missing */
    public const REQUIRED = [
        'site_name' => 'اسم المؤسسة',
        'vat_number' => 'الرقم الضريبي',
        'commercial_register' => 'رقم السجل التجاري',
        'zatca_street' => 'اسم الشارع',
        'zatca_building_number' => 'رقم المبنى (4 أرقام)',
        'zatca_district' => 'الحي',
        'address1' => 'المدينة',
        'zatca_postal_code' => 'الرمز البريدي (5 أرقام)',
    ];

    /** @return array{name: string, vat: string, crn: ?string, street: string, building: string, district: string, city: string, postal: string} */
    public static function current(): array
    {
        return [
            'name' => (string) Settings::get('site_name', tenancy()->initialized ? tenant('name') : ''),
            'vat' => (string) Settings::get('vat_number'),
            'crn' => Settings::get('commercial_register'),
            'street' => (string) Settings::get('zatca_street'),
            'building' => (string) Settings::get('zatca_building_number'),
            'district' => (string) Settings::get('zatca_district'),
            'city' => (string) Settings::get('address1'),
            'postal' => (string) Settings::get('zatca_postal_code'),
        ];
    }

    /** @return list<string> labels of what is missing or malformed */
    public static function problems(): array
    {
        $problems = [];
        foreach (self::REQUIRED as $key => $label) {
            if (blank(Settings::get($key))) {
                $problems[] = __($label);
            }
        }
        if (filled($vat = Settings::get('vat_number')) && ! preg_match('/^3\d{13}3$/', (string) $vat)) {
            $problems[] = __('الرقم الضريبي يجب أن يكون 15 رقمًا يبدأ وينتهي بـ 3');
        }
        if (filled($b = Settings::get('zatca_building_number')) && ! preg_match('/^\d{4}$/', (string) $b)) {
            $problems[] = __('رقم المبنى يجب أن يكون 4 أرقام');
        }
        if (filled($p = Settings::get('zatca_postal_code')) && ! preg_match('/^\d{5}$/', (string) $p)) {
            $problems[] = __('الرمز البريدي يجب أن يكون 5 أرقام');
        }

        return array_values(array_unique($problems));
    }
}
