<?php

namespace App\Http\Controllers\Site;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Mail\OfficeCancelled;
use App\Models\Tenant;
use App\Services\Platform\SubscriptionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/** Brings a cancelled office back inside its retention window, from the signed link in the cancellation email. */
class ReactivationController extends Controller
{
    public function show(Request $request): View
    {
        $tenant = $this->tenant($request);

        return view('site.reactivate', ['tenant' => $tenant, 'canReactivate' => $tenant->canBeReactivated()]);
    }

    public function store(Request $request, SubscriptionManager $subscriptions): RedirectResponse
    {
        $tenant = $this->tenant($request);
        try {
            $subscriptions->reactivate($tenant);
        } catch (BusinessRuleException $e) {
            return back()->withErrors(['reactivate' => $e->getMessage()]);
        }

        return redirect('/admin/login')->with('status', __('عادت مؤسستك كما كانت. سجّل دخولك للمتابعة.'));
    }

    /** From the closed-office page: send the signed link again to the office email (never shown on screen). */
    public function resend(): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        if ($tenant->canBeReactivated() && filled($tenant->email)) {
            Mail::to($tenant->email)->send(new OfficeCancelled($tenant, 'confirmed'));
        }

        return back()->with('resent', true);
    }

    private function tenant(Request $request): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        abort_unless($request->query('tenant') === $tenant->id, 404);

        return $tenant;
    }
}
