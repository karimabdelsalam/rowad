<?php

namespace App\Filament\Admin\Resources\SupportTickets;

use App\Enums\TicketStatus;
use App\Models\SupportTicket;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/** Staff side of support tickets: reply (shown in the owner/client portal), read/unread, close. */
final class TicketActions
{
    public static function reply(): Action
    {
        return Action::make('reply')
            ->label(__('رد'))
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->visible(fn (SupportTicket $record) => $record->status !== TicketStatus::Closed && self::can('reply'))
            ->schema([
                Textarea::make('message')->label(__('الرد'))->required()->rows(5)->maxLength(5000),
                Toggle::make('close')->label(__('إغلاق التذكرة بعد الرد')),
            ])
            ->action(function (SupportTicket $record, array $data) {
                $record->replies()->create(['user_id' => auth()->id(), 'message' => $data['message']]);
                $record->forceFill([
                    'status' => $data['close'] ? TicketStatus::Closed : TicketStatus::Answered,
                    'read_at' => $record->read_at ?? now(),
                ])->save();

                // the owner/client sees it in the portal bell
                $user = $record->owner?->user ?? $record->client?->user;
                if ($user !== null) {
                    Notification::make()->title(__('رد جديد على التذكرة :code', ['code' => $record->code]))
                        ->body(str($data['message'])->limit(120))->sendToDatabase($user);
                }
                Notification::make()->title(__('تم إرسال الرد'))->success()->send();
            });
    }

    public static function toggleRead(): Action
    {
        return Action::make('toggleRead')
            ->label(fn (SupportTicket $record) => $record->read_at ? __('تعيين كغير مقروء') : __('تعيين كمقروء'))
            ->icon(fn (SupportTicket $record) => $record->read_at ? Heroicon::OutlinedEnvelope : Heroicon::OutlinedEnvelopeOpen)
            ->color('gray')
            ->action(fn (SupportTicket $record) => $record->forceFill(['read_at' => $record->read_at ? null : now()])->save());
    }

    private static function can(string $ability): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || $user->can("support_tickets.{$ability}"));
    }
}
