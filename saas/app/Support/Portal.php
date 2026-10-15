<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Owner;

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
}
