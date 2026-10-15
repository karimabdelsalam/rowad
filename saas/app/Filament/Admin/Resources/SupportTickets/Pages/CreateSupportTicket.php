<?php

namespace App\Filament\Admin\Resources\SupportTickets\Pages;

use App\Filament\Admin\Resources\SupportTickets\SupportTicketResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSupportTicket extends CreateRecord
{
    protected static string $resource = SupportTicketResource::class;
}
