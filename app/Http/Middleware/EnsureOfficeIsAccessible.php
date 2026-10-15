<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Suspended, cancelled or expired-trial offices cannot use their panels or website. */
class EnsureOfficeIsAccessible
{
    public function handle(Request $request, Closure $next): Response
    {
        if (tenancy()->initialized && ! tenant()->isAccessible()) {
            return $request->is('api/*') || $request->expectsJson()
                ? response()->json(['message' => __('حساب المؤسسة موقوف أو انتهت فترته التجريبية')], 402)
                : response()->view('errors.office-unavailable', ['tenant' => tenant()], 402);
        }

        return $next($request);
    }
}
