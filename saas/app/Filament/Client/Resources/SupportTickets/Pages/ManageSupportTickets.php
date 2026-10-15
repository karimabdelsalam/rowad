<?php

namespace App\Filament\Client\Resources\SupportTickets\Pages;

use App\Filament\Client\Resources\SupportTickets\SupportTicketResource;
use App\Support\Portal;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSupportTickets extends ManageRecords
{
    protected static string $resource = SupportTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('تذكرة جديدة'))
                ->mutateDataUsing(fn (array $data) => SupportTicketResource::prepareTicket($data, ['client_id' => Portal::client()->getKey()])),
        ];
    }
}
