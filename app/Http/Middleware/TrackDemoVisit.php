<?php

namespace App\Http\Middleware;

use App\Support\Demo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Notes that someone is using the public demo office, so it is not rebuilt under them. */
class TrackDemoVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        Demo::seen();

        return $next($request);
    }
}
