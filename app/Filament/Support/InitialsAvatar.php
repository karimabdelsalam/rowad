<?php

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/** Initials drawn locally as an inline SVG — no request to a third-party avatar service on every page. */
class InitialsAvatar implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $initials = str(Filament::getNameForDefaultAvatar($record))->trim()->explode(' ')
            ->map(fn (string $part) => mb_substr((string) preg_replace('/^[^\p{L}\p{N}]+/u', '', $part), 0, 1))
            ->filter()->take(2)->join(' ');
        $color = \App\Support\Branding::color() ?: '#0f766e';
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="'.e($color).'"/>'
            .'<text x="50%" y="50%" dy=".36em" text-anchor="middle" font-family="Cairo,Tahoma,sans-serif" font-size="24" font-weight="700" fill="#fff">'
            .e($initials).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
