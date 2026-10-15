<?php

namespace App\Filament\Admin\Resources\RentContracts\Pages;

use App\Filament\Admin\Resources\RentContracts\RentContractResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRentContract extends EditRecord
{
    protected static string $resource = RentContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
