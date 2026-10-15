<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** API route groups per account type: staff, owner or client (token ability matches the type). */
class EnsureUserType
{
    public function handle(Request $request, Closure $next, string $type): Response
    {
        $user = $request->user();
        abort_unless($user !== null && $user->is_active && $user->type?->value === $type && $user->tokenCan($type), 403, __('ليس لديك صلاحية لهذا الإجراء'));

        return $next($request);
    }
}
