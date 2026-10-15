<?php

namespace App\Filament;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Owner/client portal resources: read-only views of records that belong to the
 * signed-in owner or client. Each subclass scopes getEloquentQuery().
 */
abstract class PortalResource extends Resource
{
    /** Portal users may open tickets; everything else is read-only. */
    protected static bool $portalCanCreate = false;

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $action = $action instanceof \BackedEnum ? $action->value : ($action instanceof UnitEnum ? $action->name : $action);

        return match ($action) {
            'viewAny', 'view' => Response::allow(),
            'create' => static::$portalCanCreate ? Response::allow() : Response::deny(),
            default => Response::deny(),
        };
    }
}
