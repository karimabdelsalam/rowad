<?php

namespace App\Http\Middleware;

use App\Services\Licensing\LicenseClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Self-hosted installations only: without a valid license (or past the offline grace period) every page
 * shows the license screen, except what is needed to fix it (platform sign-in and its license page).
 */
class EnsureLicensed
{
    private const ALWAYS_OPEN = ['up', 'api/license/*', 'system-cron/*', 'platform/login', 'platform/logout', 'platform/license', 'livewire*', 'locale/*', 'css/*', 'js/*', 'fonts/*', 'images/*', 'favicon*'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! LicenseClient::selfHosted() || $request->is(...self::ALWAYS_OPEN)) {
            return $next($request);
        }
        $state = app(LicenseClient::class)->state();
        if ($state['state'] === LicenseClient::INVALID) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => LicenseClient::reasonText($state['reason']), 'code' => 'license_'.$state['reason']], 402);
            }

            return response()->view('errors.license', ['reason' => LicenseClient::reasonText($state['reason'])], 402);
        }

        return $next($request);
    }
}
