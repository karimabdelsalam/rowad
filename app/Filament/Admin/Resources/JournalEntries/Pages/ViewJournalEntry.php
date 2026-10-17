<?php

namespace App\Filament\Admin\Resources\JournalEntries\Pages;

use App\Filament\Admin\Resources\JournalEntries\JournalEntryResource;
use App\Models\JournalEntry;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewJournalEntry extends ViewRecord
{
    protected static string $resource = JournalEntryResource::class;

    public function getTitle(): string
    {
        return __('قيد :number', ['number' => $this->getRecord()->number]);
    }

    protected function getHeaderActions(): array
    {
        /** @var JournalEntry $entry */
        $entry = $this->getRecord();

        return [
            Action::make('source')->label(__('فتح المستند'))->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                ->visible(JournalEntryResource::sourceUrl($entry) !== null)
                ->url(JournalEntryResource::sourceUrl($entry)),
            Action::make('print')->label(__('طباعة'))->icon(Heroicon::OutlinedPrinter)->color('gray')
                ->alpineClickHandler('window.print()'),
            EditAction::make(),
        ];
    }
}
