<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Owner;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;

/** The owner/client record behind the signed-in portal user. */
final class Portal
{
    public static function owner(): Owner
    {
        $owner = auth()->user()?->owner;
        abort_if($owner === null, 403);

        return $owner;
    }

    public static function client(): Client
    {
        $client = auth()->user()?->client;
        abort_if($client === null, 403);

        return $client;
    }

    /** @return Builder<Unit> units the owner holds a share in */
    public static function ownerUnits(Owner $owner): Builder
    {
        return Unit::query()->whereHas('owners', fn (Builder $q) => $q->whereKey($owner->getKey()));
    }

    /** @return Builder<Unit> units the client rents or bought */
    public static function clientUnits(Client $client): Builder
    {
        return Unit::query()->where(fn (Builder $q) => $q
            ->whereHas('rentContracts.clients', fn (Builder $q) => $q->whereKey($client->getKey()))
            ->orWhereHas('saleContracts.clients', fn (Builder $q) => $q->whereKey($client->getKey())));
    }
}
