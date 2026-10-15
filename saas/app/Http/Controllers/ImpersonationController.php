<?php

namespace App\Http\Controllers;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Legacy "loginas" / "masklogout": staff open an owner's or client's portal. */
class ImpersonationController
{
    public function start(Request $request, User $user, string $panel): RedirectResponse
    {
        $staff = $request->user();
        abort_unless($staff !== null && $staff->type === UserType::Staff, 403);
        $expected = ['owner' => UserType::Owner, 'client' => UserType::Client][$panel] ?? abort(404);
        abort_unless($user->type === $expected && $user->is_active, 404);
        // the link is signed, but re-check the right here: a copied link must not work for staff without it
        abort_unless($staff->is_super_admin || $staff->can(($panel === 'owner' ? 'owners' : 'clients').'.login_as'), 403);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('impersonator_id', $staff->getKey());
        activity()->causedBy($staff)->performedOn($user)->log('impersonation.start');

        return redirect("/{$panel}");
    }

    public function stop(Request $request): RedirectResponse
    {
        $staffId = $request->session()->pull('impersonator_id');
        $staff = $staffId === null ? null : User::find($staffId);
        abort_unless($staff !== null && $staff->type === UserType::Staff && $staff->is_active, 404);

        Auth::login($staff);
        $request->session()->regenerate();

        return redirect('/admin');
    }
}
