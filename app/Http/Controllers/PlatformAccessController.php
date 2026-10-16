<?php

namespace App\Http\Controllers;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Open as the office admin" from the platform console: a signed, two-minute link on the office's own
 * domain that signs the platform operator in as one of the office's staff accounts (for support).
 */
class PlatformAccessController
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->type === UserType::Staff && $user->is_active, 404);
        Auth::logout();
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('platform_access', true);
        activity()->performedOn($user)->withProperties(['ip' => $request->ip()])->log('platform.access');

        return redirect('/admin');
    }
}
