<?php

namespace App\Notifications\Channels;

use App\Services\Messaging\Notifier;
use Illuminate\Notifications\Notification;

/** Sends a Laravel notification through the office's message templates (SMS first, email if no mobile). */
class OfficeMessageChannel
{
    public function __construct(private readonly Notifier $notifier)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        ['event' => $event, 'variables' => $variables] = $notification->toOfficeMessage($notifiable);
        $mobile = $notifiable->mobile ?? null;

        $this->notifier->send($event, $variables, [[
            'name' => $notifiable->name ?? '',
            'mobile' => $mobile,
            'email' => filled($mobile) ? null : ($notifiable->email ?? null),
        ]], force: true);
    }
}
