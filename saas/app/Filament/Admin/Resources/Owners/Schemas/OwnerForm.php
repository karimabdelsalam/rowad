<?php

namespace App\Filament\Admin\Resources\Owners\Schemas;

use App\Filament\Admin\Resources\Concerns\PersonFormSections;
use Filament\Schemas\Schema;

class OwnerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                PersonFormSections::name(),
                PersonFormSections::identity(),
                PersonFormSections::contact(),
                PersonFormSections::notesAndFiles(),
                PersonFormSections::account(),
            ]);
    }
}
