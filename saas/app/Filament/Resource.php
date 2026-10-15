<?php

namespace App\Filament;

use Filament\Resources\Resource as FilamentResource;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Base resource: labels and navigation groups are written in Arabic and passed
 * through the translator, so lang/en.json can provide the English UI.
 */
abstract class Resource extends FilamentResource
{
    /** Permission prefix in config/rowad.php, e.g. "units". Null disables the permission gate. */
    protected static ?string $permission = null;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $group = parent::getNavigationGroup();

        return is_string($group) ? __($group) : $group;
    }

    public static function getModelLabel(): string
    {
        return __(static::$modelLabel ?? parent::getModelLabel());
    }

    public static function getPluralModelLabel(): string
    {
        return __(static::$pluralModelLabel ?? parent::getPluralModelLabel());
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        if (static::$permission === null) {
            return parent::getAuthorizationResponse($action, $record);
        }

        $user = auth()->user();
        if ($user === null) {
            return Response::deny();
        }
        if ($user->is_super_admin) {
            return Response::allow();
        }

        $action = $action instanceof \BackedEnum ? $action->value : ($action instanceof UnitEnum ? $action->name : $action);

        $ability = match ($action) {
            'viewAny' => 'view_any',
            'view' => 'view',
            'create' => 'create',
            'update', 'reorder' => 'update',
            'delete', 'deleteAny', 'forceDelete', 'forceDeleteAny', 'restore', 'restoreAny', 'replicate' => 'delete',
            default => \Illuminate\Support\Str::snake($action),
        };

        return $user->can(static::$permission.'.'.$ability) ? Response::allow() : Response::deny();
    }
}
