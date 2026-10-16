<?php

namespace App\Support;

use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Storage;

/**
 * The office's own identity inside its panels and login pages: logo, name, colour.
 * Everything falls back gracefully so a fresh office looks good before it uploads anything.
 */
final class Branding
{
    /** Panel id => what the page is, shown under the office name. */
    public const PANEL_TITLES = [
        'admin' => 'لوحة الإدارة',
        'owner' => 'بوابة الملاك',
        'client' => 'بوابة المستأجرين',
    ];

    public static function name(): string
    {
        return tenancy()->initialized ? Website::name() : (string) config('app.name');
    }

    /** The monogram shown when there is no logo: one Arabic letter (after "ال"), or two Latin initials. */
    public static function initials(): string
    {
        $words = preg_split('/\s+/u', trim(self::name())) ?: [];
        $words = array_values(array_filter($words, fn ($w) => ! in_array($w, ['مؤسسة', 'شركة', 'مكتب', 'مجموعة', 'Co', 'Co.', 'Ltd'], true)));
        $words = array_map(fn ($w) => preg_replace('/^ال/u', '', $w) ?: $w, $words ?: ['عقار']);
        $arabic = preg_match('/\p{Arabic}/u', $words[0]) === 1;

        return mb_strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), array_slice($words, 0, $arabic ? 1 : 2))));
    }

    /** Signed-free URL of the uploaded logo (route on the office's own domain), or null. */
    public static function logoUrl(): ?string
    {
        if (! tenancy()->initialized) {
            return null;
        }
        $path = (string) Settings::get('logo');
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return route('site.logo', ['v' => substr(md5($path.Storage::disk('public')->lastModified($path)), 0, 8)]);
    }

    /** The office's chosen brand colour as a validated hex, or null for the Aqar Nova teal. */
    public static function color(): ?string
    {
        $hex = strtolower(trim((string) (tenancy()->initialized ? Settings::get('brand_color') : '')));

        return preg_match('/^#[0-9a-f]{6}$/', $hex) ? $hex : null;
    }

    /** Filament palette (50…950, oklch strings) for the chosen colour, or null. */
    public static function palette(): ?array
    {
        $hex = self::color();

        return $hex ? Color::hex($hex) : null;
    }

    public static function panelTitle(?string $panelId): string
    {
        return __(self::PANEL_TITLES[$panelId] ?? 'لوحة التحكم');
    }

    /** CSS custom properties that re-skin the panel in the office's colour; empty when none is set. */
    public static function css(): string
    {
        $palette = self::palette();
        if ($palette === null) {
            return '';
        }
        $vars = '';
        foreach ($palette as $step => $value) {
            $vars .= "--primary-{$step}:{$value};";
        }
        $vars .= '--rowad-brand:'.self::color().';--rowad-brand-dark:'.$palette[700].';--rowad-brand-soft:'.$palette[50].';';
        $vars .= '--rowad-brand-line:'.$palette[200].';--rowad-brand-light:'.$palette[400].';';
        $vars .= '--rowad-side:color-mix(in oklab, '.$palette[950].' 80%, #000);--rowad-side-2:color-mix(in oklab, '.$palette[900].' 75%, #000);';
        $vars .= '--rowad-side-ink:'.$palette[100].';--rowad-side-muted:color-mix(in oklab, '.$palette[300].' 70%, transparent);';

        return ':root{'.$vars.'}';
    }
}
