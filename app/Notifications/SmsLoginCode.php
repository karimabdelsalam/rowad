<?php

namespace App\Notifications;

use App\Notifications\Channels\OfficeMessageChannel;
use Illuminate\Notifications\Notification;
use SensitiveParameter;

class SmsLoginCode extends Notification
{
    public function __construct(#[SensitiveParameter] public string $code, public int $codeExpiryMinutes)
    {
    }

    public function via(object $notifiable): array
    {
        return [OfficeMessageChannel::class];
    }

    /** @return array{event: \App\Enums\NotificationEvent, variables: array<string, string>} */
    public function toOfficeMessage(object $notifiable): array
    {
        return ['event' => \App\Enums\NotificationEvent::LoginOtp, 'variables' => ['code' => $this->code]];
    }
}
