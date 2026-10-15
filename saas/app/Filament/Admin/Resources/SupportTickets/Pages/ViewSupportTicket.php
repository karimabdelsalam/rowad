<?php

namespace App\Filament\Admin\Resources\SupportTickets\Pages;

use App\Filament\Admin\Resources\SupportTickets\SupportTicketResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSupportTicket extends ViewRecord
{
    protected static string $resource = SupportTicketResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        if ($this->getRecord()->read_at === null) {
            $this->getRecord()->forceFill(['read_at' => now()])->save(); // legacy support/show marks it read
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            \App\Filament\Admin\Resources\SupportTickets\TicketActions::reply(),
            \App\Filament\Admin\Resources\SupportTickets\TicketActions::toggleRead(),
        ];
    }
}
