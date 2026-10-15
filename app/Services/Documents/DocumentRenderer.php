<?php

namespace App\Services\Documents;

use App\Models\PrintTemplate;
use App\Support\Hijri;
use App\Support\Settings;

/**
 * Port of legacy systemcore::processDoc: fills an office print template.
 * Placeholders: {org_*}, {$}, {today}, {today_date}, {number}/{serial}, {doc_date}, any {key},
 * wildcard keys like "item_*" filled from arrays, and blocks <!--{name}-->...<!--{name}-->.
 */
class DocumentRenderer
{
    /**
     * @param  array<string, mixed>  $variables
     * @param  array<string, string>  $blocks  block name => replacement HTML
     */
    public function render(string $templateKey, array $variables, array $blocks = []): string
    {
        $template = PrintTemplate::where('key', $templateKey)->first();
        $html = $template ? ($template->header ?? '').$template->content.($template->footer ?? '') : '';

        // Repeating blocks first: their markers (<!--{owners}-->) contain the same names as plain variables.
        foreach ($blocks as $name => $replacement) {
            $quoted = preg_quote($name, '/');
            $html = preg_replace('/<!--\{'.$quoted.'\}-->(.*?)<!--\{'.$quoted.'\}-->/su', str_replace(['\\', '$'], ['\\\\', '\$'], $replacement), $html) ?? $html;
        }

        $html = strtr($html, $this->organisationVariables());

        if (filled($variables['number'] ?? null)) {
            $variables['serial'] ??= $variables['number'];
            $variables['number'] = __('رقــم المستند').': '.$variables['number'];
        }
        if (filled($variables['date'] ?? null)) {
            $variables['doc_date'] ??= __('تاريخ المستند').': '.$variables['date'];
        }

        foreach ($variables as $key => $value) {
            if (str_contains($key, '*') && is_array($value)) {
                $pattern = '/\{('.str_replace('\*', '([A-Za-z0-9_]+)', preg_quote($key, '/')).')\}/u';
                $html = preg_replace_callback($pattern, fn ($m) => (string) ($value[$m[2]] ?? ''), $html);

                continue;
            }
            $html = str_replace('{'.$key.'}', is_scalar($value) || $value === null ? (string) $value : '', $html);
        }


        return $html;
    }

    /** @return array<string, string> */
    public function organisationVariables(): array
    {
        return [
            '{$}' => (string) Settings::get('currency', 'SAR'),
            '{org_arname}' => (string) Settings::get('site_name', tenant('name')),
            '{org_enname}' => (string) Settings::get('site_name_en'),
            '{org_logo}' => Settings::get('logo') ? route('site.logo') : '',
            '{org_city}' => (string) Settings::get('address1'),
            '{org_address}' => (string) Settings::get('address2'),
            '{org_mobile}' => (string) Settings::get('mobile'),
            '{org_phone}' => (string) Settings::get('phone'),
            '{org_fax}' => (string) Settings::get('fax'),
            '{org_email}' => (string) Settings::get('email'),
            '{org_vat_number}' => (string) Settings::get('vat_number'),
            '{vat_number}' => (string) Settings::get('vat_number'),
            '{today}' => Hijri::format(now(), 'EEEE', app()->getLocale() === 'ar' ? 'ar_SA@calendar=islamic-umalqura' : 'en@calendar=islamic-umalqura'),
            '{today_date}' => now()->format('d-m-Y'),
            '{today_hijri}' => Hijri::toString(now()),
        ];
    }
}
