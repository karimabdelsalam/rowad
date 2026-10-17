<?php

namespace App\Filament\Admin\Resources\JournalEntries\Pages;

use App\Filament\Admin\Resources\JournalEntries\JournalEntryResource;
use App\Filament\Concerns\HandlesContractParties;
use App\Services\Accounting\Journal;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateJournalEntry extends CreateRecord
{
    use HandlesContractParties;

    protected static string $resource = JournalEntryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return $this->guardBusinessRules(fn () => app(Journal::class)->post(
            ['entry_date' => $data['entry_date'], 'description' => $data['description']], $data['lines'] ?? []));
    }

    protected function getRedirectUrl(): string
    {
        return JournalEntryResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
