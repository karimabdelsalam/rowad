<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/** Development aid (ROWAD_PERF_LOG=true): logs queries and time per request to storage/logs/perf.log. */
class LogQueryCount
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('rowad.perf_log')) {
            return $next($request);
        }

        $queries = 0;
        $queryMs = 0.0;
        DB::listen(function ($query) use (&$queries, &$queryMs) {
            $queries++;
            $queryMs += $query->time;
        });
        $start = microtime(true);
        $response = $next($request);

        $components = collect($request->input('components', []))
            ->map(fn ($c) => json_decode($c['snapshot'] ?? '{}', true)['memo']['name'] ?? '?')->implode(',');

        Log::build(['driver' => 'single', 'path' => storage_path('logs/perf.log')])->info(sprintf('%s %s%s %s queries=%d query_ms=%.1f total_ms=%.1f',
            $request->method(), $request->getHost(), $request->getRequestUri(), $components, $queries, $queryMs, (microtime(true) - $start) * 1000));

        return $response;
    }
}
