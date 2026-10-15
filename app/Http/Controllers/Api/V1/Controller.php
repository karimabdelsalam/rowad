<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;

abstract class Controller
{
    /** Same permission names as the admin panel (config/rowad.php). */
    protected function authorizePermission(Request $request, string $permission): void
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->is_super_admin || $user->can($permission), 403, __('ليس لديك صلاحية لهذا الإجراء'));
    }

    protected function perPage(Request $request): int
    {
        return max(1, min(100, (int) $request->integer('per_page', 25)));
    }
}
