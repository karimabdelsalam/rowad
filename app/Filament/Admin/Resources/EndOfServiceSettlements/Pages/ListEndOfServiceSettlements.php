<?php

namespace App\Filament\Admin\Resources\EndOfServiceSettlements\Pages;

use App\Filament\Admin\Resources\EndOfServiceSettlements\EndOfServiceSettlementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEndOfServiceSettlements extends ListRecords
{
    protected static string $resource = EndOfServiceSettlementResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('تسوية جديدة'))];
    }
}
