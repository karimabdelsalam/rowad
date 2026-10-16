<?php

namespace App\Filament;

use Filament\Resources\Resource as FilamentResource;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Base resource: labels and navigation groups are written in Arabic and passed
 * through the translator, so lang/en.json can provide the English UI.
 */
abstract class Resource extends FilamentResource
{
    /** The top search box (Ctrl+K) shows a few matches per section; the list page has the rest. */
    protected static int $globalSearchResultsLimit = 5;

    /** Relations the search result lines read, loaded with the matches. @var list<string> */
    protected static array $globalSearchWith = [];

    /** Permission prefix in config/rowad.php, e.g. "units". Null disables the permission gate. */
    protected static ?string $permission = null;

    public static function getPermissionPrefix(): ?string
    {
        return static::$permission;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $group = parent::getNavigationGroup();

        return is_string($group) ? __($group) : $group;
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(static::$globalSearchWith);
    }

    /** A date in a search result line, kept left-to-right inside Arabic text. */
    protected static function searchDate(mixed $date): ?string
    {
        return $date ? "\u{2066}".Carbon::parse($date)->format('Y-m-d')."\u{2069}" : null;
    }

    protected static function searchMoney(mixed $amount): ?string
    {
        return $amount === null ? null : "\u{2066}".number_format((float) $amount, 2)."\u{2069} ".__('ر.س');
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
            default => Str::snake($action),
        };

        return $user->can(static::$permission.'.'.$ability) ? Response::allow() : Response::deny();
    }
}
