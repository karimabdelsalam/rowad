<?php

namespace App\Filament\Admin\Resources\InboxMessages\Pages;

use App\Filament\Admin\Resources\InboxMessages\InboxMessageResource;
use Filament\Resources\Pages\ViewRecord;

class ViewInboxMessage extends ViewRecord
{
    protected static string $resource = InboxMessageResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->getRecord()->read_at === null) {
            $this->getRecord()->forceFill(['read_at' => now()])->save();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
