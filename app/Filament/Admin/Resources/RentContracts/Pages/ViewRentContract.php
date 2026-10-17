<?php

namespace App\Filament\Admin\Resources\RentContracts\Pages;

use App\Filament\Admin\Resources\RentContracts\RentContractResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewRentContract extends ViewRecord
{
    protected static string $resource = RentContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \App\Filament\Admin\Pages\Assistant::askAboutAction('rent_contract'),
            ...\App\Filament\Support\DocumentActions::make('rent_contract', __('طباعة العقد')),
            \App\Filament\Admin\Resources\RentContracts\SignatureActions::request(),
            \App\Filament\Admin\Resources\RentContracts\EjarActions::group(),
            EditAction::make(),
        ];
    }
}
