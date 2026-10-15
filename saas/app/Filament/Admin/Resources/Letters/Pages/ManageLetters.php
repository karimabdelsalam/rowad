<?php

namespace App\Filament\Admin\Resources\Letters\Pages;

use App\Filament\Admin\Resources\Letters\LetterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLetters extends ManageRecords
{
    protected static string $resource = LetterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
