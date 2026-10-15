<?php

namespace App\Filament\Admin\Resources\Units\Pages;

use App\Filament\Admin\Resources\Units\Pages\Concerns\SavesUnitExtras;
use App\Filament\Admin\Resources\Units\UnitResource;
use App\Services\Units\UnitOwnership;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUnit extends EditRecord
{
    use SavesUnitExtras;

    protected static string $resource = UnitResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['owner_shares'] = app(UnitOwnership::class)->ownersForForm($this->getRecord());
        $data['custom_fields'] = app(UnitOwnership::class)->customFieldsForForm($this->getRecord());

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $data = $this->pullUnitExtras($data);

        return $this->saveUnit(function () use ($record, $data) {
            $record->update($data);

            return $record;
        });
    }
}
