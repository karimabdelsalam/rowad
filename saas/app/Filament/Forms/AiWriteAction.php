<?php

namespace App\Filament\Forms;

use App\Filament\Pages\AssistantPage;
use App\Services\Ai\AiUnavailable;
use App\Services\Ai\Assistant;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * "اكتب بالذكاء الاصطناعي" next to a text field: the user says what they want to say, the AI drafts it
 * into the field, and the user edits it before saving. Nothing is sent by the AI.
 */
final class AiWriteAction
{
    /** @param string $kind email|sms|letter */
    public static function make(string $kind, ?string $subjectField = null, string $name = 'aiWrite'): Action
    {
        return Action::make($name)
            ->label(__('اكتب بالذكاء الاصطناعي'))
            ->icon(Heroicon::OutlinedSparkles)
            ->visible(fn () => \App\Filament\Admin\Pages\Assistant::canAccess())
            ->modalHeading(__('اكتب بالذكاء الاصطناعي'))
            ->modalDescription(__('اكتب فكرة الرسالة وسيصوغها المساعد في الحقل، وتستطيع تعديلها قبل الحفظ.'))
            ->modalSubmitActionLabel(__('اكتب المسودة'))
            ->schema([
                Textarea::make('brief')->label(__('ماذا تريد أن تقول؟'))->required()->rows(4)->maxLength(2000)
                    ->placeholder(__('مثال: تذكير المستأجرين بسداد إيجار الشهر قبل يوم 5، مع رقم الحساب البنكي')),
                Select::make('tone')->label(__('الأسلوب'))->default('رسمي ومهذب')->required()->options([
                    'رسمي ومهذب' => __('رسمي ومهذب'), 'ودّي' => __('ودّي'), 'مختصر ومباشر' => __('مختصر ومباشر'), 'حازم ومهذب' => __('حازم ومهذب'),
                ]),
                Select::make('language')->label(__('اللغة'))->default(fn () => app()->getLocale())->required()
                    ->options(['ar' => 'العربية', 'en' => 'English']),
            ])
            ->action(function (array $data, Field $component, Set $set) use ($kind, $subjectField) {
                try {
                    $draft = app(Assistant::class)->write(auth()->user(), $kind, $data['brief'], $data['tone'], $data['language'],
                        ['organisation' => \App\Support\Website::name()]);
                } catch (AiUnavailable $e) {
                    Notification::make()->title($e->getMessage())->warning()->send();

                    return;
                }
                // rich editors take HTML; the model's markdown is converted with any raw HTML escaped
                $component->state($kind === 'sms' ? $draft['body'] : AssistantPage::markdown($draft['body']));
                if ($subjectField && $draft['subject']) {
                    $set($subjectField, Str::limit($draft['subject'], 190, ''));
                }
                Notification::make()->title(__('تمت كتابة المسودة. راجعها قبل الحفظ.'))->success()->send();
            });
    }
}
