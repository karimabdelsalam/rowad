<?php

namespace App\Filament\Admin\Resources\Concerns;

use App\Services\Ai\AiUnavailable;
use App\Services\Ai\DocumentReader;
use App\Support\PlanLimits;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/** "Fill from a photo": the AI reads an ID card or a title deed and fills the form for staff to review. */
final class ReadDocumentAction
{
    /** @param 'id'|'deed' $kind */
    public static function make(string $kind): Action
    {
        return Action::make($kind === 'deed' ? 'readDeed' : 'readId')
            ->label($kind === 'deed' ? __('تعبئة من صورة الصك') : __('تعبئة من صورة الهوية'))
            ->icon(Heroicon::OutlinedSparkles)
            ->color('gray')
            ->size('sm')
            ->visible(fn () => PlanLimits::allows('ai'))
            ->modalHeading($kind === 'deed' ? __('قراءة الصك بالذكاء الاصطناعي') : __('قراءة الهوية بالذكاء الاصطناعي'))
            ->modalDescription(__('صوّر المستند بوضوح وارفعه؛ تُعبأ الحقول تلقائيًا لتراجعها قبل الحفظ. لا تُحفظ الصورة في النظام.'))
            ->modalSubmitActionLabel(__('قراءة وتعبئة'))
            ->schema([
                FileUpload::make('document')->hiddenLabel()->required()->storeFiles(false)->maxSize(5120)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf']),
            ])
            ->action(function (array $data, Get $get, Set $set) use ($kind) {
                $file = $data['document'];
                try {
                    $fields = app(DocumentReader::class)->read(auth()->user(), $kind, (string) $file->get(), (string) $file->getMimeType());
                } catch (AiUnavailable $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                }
                $filled = $kind === 'deed' ? self::fillDeed($fields, $get, $set) : self::fillPerson($fields, $set);
                Notification::make()->{$filled ? 'success' : 'warning'}()
                    ->title($filled ? __('عُبئ :n حقلًا من المستند — راجعها قبل الحفظ', ['n' => $filled]) : __('لم نتمكن من قراءة بيانات واضحة، جرّب صورة أوضح'))
                    ->send();
            });
    }

    private static function fillPerson(array $fields, Set $set): int
    {
        $keys = ['first_name', 'father_name', 'grandfather_name', 'family_name', 'id_type', 'id_number', 'nationality', 'birth_date', 'id_source'];
        $filled = 0;
        foreach (array_intersect_key($fields, array_flip($keys)) as $key => $value) {
            $set($key, $value);
            $filled++;
        }

        return $filled;
    }

    private static function fillDeed(array $fields, Get $get, Set $set): int
    {
        $filled = 0;
        foreach (['plan_number', 'plot_number', 'area'] as $key) {
            if (filled($fields[$key] ?? null)) {
                $set($key, $fields[$key]);
                $filled++;
            }
        }
        if (filled($fields['deed_number'] ?? null)) {
            $deeds = (array) ($get('deeds') ?? []);
            $already = collect($deeds)->contains(fn ($d) => ($d['number'] ?? null) === $fields['deed_number']);
            if (! $already) {
                $deeds[(string) Str::uuid()] = ['deed_type_id' => null, 'number' => $fields['deed_number'],
                    'source' => $fields['deed_issuer'] ?? null, 'issue_date' => $fields['deed_date'] ?? null];
                $set('deeds', $deeds);
            }
            $filled++;
        }

        return $filled;
    }
}
