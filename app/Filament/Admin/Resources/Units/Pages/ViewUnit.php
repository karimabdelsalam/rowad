<?php

namespace App\Filament\Admin\Resources\Units\Pages;

use App\Filament\Admin\Resources\Units\UnitResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUnit extends ViewRecord
{
    protected static string $resource = UnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \App\Filament\Admin\Pages\Assistant::askAboutAction('unit'),
            ...\App\Filament\Support\DocumentActions::make('unit', __('طباعة البطاقة')),
            EditAction::make(),
        ];
    }
}
