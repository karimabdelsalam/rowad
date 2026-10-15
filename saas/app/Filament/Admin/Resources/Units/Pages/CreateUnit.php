<?php

namespace App\Filament\Admin\Resources\Units\Pages;

use App\Filament\Admin\Resources\Units\Pages\Concerns\SavesUnitExtras;
use App\Filament\Admin\Resources\Units\UnitResource;
use App\Models\Unit;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUnit extends CreateRecord
{
    use SavesUnitExtras;

    protected static string $resource = UnitResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $data = $this->pullUnitExtras($data);

        return $this->saveUnit(fn () => Unit::create($data));
    }
}
