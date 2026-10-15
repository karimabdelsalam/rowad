<?php

namespace App\Http\Middleware;

use App\Support\PlanLimits;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Blocks a portal or the API when the organisation's plan does not include it (usage: EnsurePlanFeature::class.':owner_portal'). */
class EnsurePlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (PlanLimits::allows($feature)) {
            return $next($request);
        }
        $message = __('هذه الخدمة غير متاحة في باقة المؤسسة الحالية.');

        return $request->expectsJson() || $request->is('api/*')
            ? response()->json(['message' => $message], 403)
            : response()->view('errors.feature-unavailable', ['message' => $message], 403);
    }
}
