<?php

namespace App\Filament\Admin\Resources\EndOfServiceSettlements\Pages;

use App\Filament\Admin\Resources\EndOfServiceSettlements\EndOfServiceSettlementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEndOfServiceSettlement extends CreateRecord
{
    protected static string $resource = EndOfServiceSettlementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return EndOfServiceSettlementResource::compute($data);
    }

    protected function getRedirectUrl(): string
    {
        return EndOfServiceSettlementResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
