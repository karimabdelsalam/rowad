<?php

namespace App\Filament\Admin\Resources\InboxMessages;

use App\Enums\MessageChannel;
use App\Models\InboxMessage;
use App\Services\Messaging\Notifier;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/** Legacy inbox reply / read / unread. */
final class InboxActions
{
    public static function reply(): Action
    {
        return Action::make('reply')
            ->label(__('رد'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->visible(fn (InboxMessage $record) => (filled($record->email) || filled($record->mobile)) && self::can('reply'))
            ->fillForm(fn (InboxMessage $record) => [
                'channel' => filled($record->email) ? 'mail' : 'sms',
                'subject' => __('رد: :subject', ['subject' => $record->subject ?: __('رسالتك')]),
            ])
            ->schema(fn (InboxMessage $record) => [
                Radio::make('channel')->label(__('القناة'))->inline()->required()->live()
                    ->options(array_filter(['mail' => filled($record->email) ? __('البريد الإلكتروني').' — '.$record->email : null,
                        'sms' => filled($record->mobile) ? __('رسالة نصية').' — '.$record->mobile : null])),
                TextInput::make('subject')->label(__('الموضوع'))->visible(fn (Get $get) => $get('channel') === 'mail')->maxLength(150),
                Textarea::make('message')->label(__('الرد'))->required()->rows(5)->maxLength(5000),
            ])
            ->action(function (InboxMessage $record, array $data) {
                $mail = $data['channel'] === 'mail';
                app(Notifier::class)->direct($mail ? MessageChannel::Mail : MessageChannel::Sms, $mail ? $record->email : $record->mobile,
                    $mail ? $data['subject'] : null, $mail ? nl2br(e($data['message'])) : $data['message'], 'inbox_reply');
                $record->forceFill(['reply' => $data['message'], 'replied_at' => now(), 'read_at' => $record->read_at ?? now()])->save();
                Notification::make()->title(__('تم إرسال الرد'))->success()->send();
            });
    }

    public static function toggleRead(): Action
    {
        return Action::make('toggleRead')
            ->label(fn (InboxMessage $record) => $record->read_at ? __('تعيين كغير مقروء') : __('تعيين كمقروء'))
            ->icon(fn (InboxMessage $record) => $record->read_at ? Heroicon::OutlinedEnvelope : Heroicon::OutlinedEnvelopeOpen)
            ->color('gray')
            ->action(fn (InboxMessage $record) => $record->forceFill(['read_at' => $record->read_at ? null : now()])->save());
    }

    private static function can(string $ability): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || $user->can("inbox_messages.{$ability}"));
    }
}
