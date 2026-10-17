<?php

namespace App\Filament\Admin\Resources\JournalEntries\Pages;

use App\Filament\Admin\Resources\JournalEntries\JournalEntryResource;
use App\Filament\Concerns\HandlesContractParties;
use App\Models\JournalEntry;
use App\Services\Accounting\Journal;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditJournalEntry extends EditRecord
{
    use HandlesContractParties;

    protected static string $resource = JournalEntryResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['lines'] = $this->getRecord()->lines()->get(['account_id', 'debit', 'credit', 'memo'])
            ->map(fn ($l) => ['account_id' => $l->account_id, 'debit' => (float) $l->debit ?: null, 'credit' => (float) $l->credit ?: null, 'memo' => $l->memo])->all();

        return $data;
    }

    /** @param  JournalEntry  $record */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->guardBusinessRules(fn () => app(Journal::class)->post(
            ['entry_date' => $data['entry_date'], 'description' => $data['description']], $data['lines'] ?? [], $record));
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return JournalEntryResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
