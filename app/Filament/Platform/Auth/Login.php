<?php

namespace App\Filament\Platform\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;

/** Sign-in to the platform console (the operator team), with its own heading beside the Aqar Nova panel. */
class Login extends BaseLogin
{
    public function getHeading(): string|Htmlable
    {
        return __('دخول إدارة المنصة');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('لفريق تشغيل عقار نوفا فقط');
    }
}
