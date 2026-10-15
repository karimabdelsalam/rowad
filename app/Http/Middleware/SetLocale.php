<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Arabic is the primary language, English the secondary one.
 * Order: ?lang= → session → signed-in user's preference → office default → app default.
 */
class SetLocale
{
    public const SUPPORTED = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);
        app()->setLocale($locale);
        \Carbon\Carbon::setLocale($locale);

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        $candidates = [
            $request->query('lang'),
            $request->hasSession() ? $request->session()->get('locale') : null,
            $request->user()?->locale,
            $request->headers->has('Accept-Language') && $request->is('api/*') ? $request->getPreferredLanguage(self::SUPPORTED) : null,
            tenancy()->initialized ? Settings::get('locale') : null,
        ];

        foreach ($candidates as $candidate) {
            if (in_array($candidate, self::SUPPORTED, true)) {
                if ($candidate === $request->query('lang') && $request->hasSession()) {
                    $request->session()->put('locale', $candidate);
                }

                return $candidate;
            }
        }

        return config('app.locale', 'ar');
    }
}
