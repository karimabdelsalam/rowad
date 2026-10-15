<?php

namespace App\Filament\Admin\Resources\NewsletterSubscribers\Pages;

use App\Filament\Admin\Resources\NewsletterSubscribers\NewsletterSubscriberResource;
use App\Models\NewsletterSubscriber;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;

class ManageNewsletterSubscribers extends ManageRecords
{
    protected static string $resource = NewsletterSubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            // legacy newsletter/import: pull every e-mail (and Saudi mobile) out of a contacts file
            Action::make('import')
                ->label(__('استيراد قائمة'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->visible(fn () => NewsletterSubscriberResource::canCreate())
                ->schema([
                    FileUpload::make('file')->label(__('ملف جهات الاتصال (Excel أو CSV أو VCF أو TXT)'))->required()
                        ->disk('local')->directory('imports')->maxSize(5120)
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'text/vcard', 'text/x-vcard', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']),
                ])
                ->action(function (array $data) {
                    $path = Storage::disk('local')->path($data['file']);
                    $added = $this->importContacts(self::readText($path));
                    Storage::disk('local')->delete($data['file']);

                    $added > 0
                        ? Notification::make()->title(__('تمت إضافة :count مشترك', ['count' => $added]))->success()->send()
                        : Notification::make()->title(__('لم يتم العثور على بريد إلكتروني أو جوال جديد في الملف. تأكد أن ترميز الملف UTF-8'))->warning()->send();
                }),
        ];
    }

    private static function readText(string $path): string
    {
        if (str_ends_with(strtolower($path), '.xlsx')) {
            $reader = new Reader;
            $reader->open($path);
            $lines = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $lines[] = implode(' ', array_map('strval', $row->toArray()));
                }
            }
            $reader->close();

            return implode("\n", $lines);
        }

        return (string) file_get_contents($path);
    }

    private function importContacts(string $text): int
    {
        preg_match_all('/[a-z\d._%+-]+@[a-z\d.-]+\.[a-z]{2,}\b/i', $text, $emails);
        preg_match_all('/(?<!\d)(?:\+?966|0)5\d{8}(?!\d)/', $text, $mobiles);

        $added = 0;
        foreach (array_unique(array_map('strtolower', $emails[0])) as $email) {
            $added += NewsletterSubscriber::firstOrCreate(['email' => $email])->wasRecentlyCreated ? 1 : 0;
        }
        foreach (array_unique($mobiles[0]) as $mobile) {
            $added += NewsletterSubscriber::firstOrCreate(['mobile' => $mobile])->wasRecentlyCreated ? 1 : 0;
        }

        return $added;
    }
}
