<?php

namespace App\Http\Controllers;

use App\Console\Commands\CreateDemoOffice;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * One-click entry to the public demo office from the landing page (demo.aqarnova.com/demo/admin|owner|client).
 * Only the office created by `rowad:demo` answers; on any real office this is a 404.
 */
class DemoLoginController extends Controller
{
    public function __invoke(string $role): RedirectResponse
    {
        abort_unless(tenancy()->initialized && tenant()->isDemo(), 404);
        [$email, $panel] = CreateDemoOffice::ACCOUNTS[$role] ?? abort(404);

        Auth::guard('web')->login(User::where('email', $email)->firstOrFail());
        session()->regenerate();

        return redirect()->to('/'.$panel);
    }
}
