<?php

namespace App\Services\Import\Importers;

use App\Models\Owner;

class OwnersImporter extends PeopleImporter
{
    public static function key(): string
    {
        return 'owners';
    }

    public static function title(): string
    {
        return __('الملاك');
    }

    protected static function model(): string
    {
        return Owner::class;
    }
}
