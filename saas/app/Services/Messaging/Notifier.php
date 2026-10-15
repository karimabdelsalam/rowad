<?php

namespace App\Services\Messaging;

use App\Enums\MessageChannel;
use App\Enums\NotificationEvent;
use App\Jobs\SendOutgoingMessage;
use App\Models\MessageTemplate;
use App\Models\OutgoingMessage;
use App\Models\User;
use App\Support\Settings;
use Filament\Notifications\Notification as FilamentNotification;

/**
 * Port of the legacy notification module: renders the office's editable
 * template for an event, logs each email/SMS in outgoing_messages and queues
 * delivery. Optionally alerts staff in the admin panel (legacy insert_notifcation).
 */
class Notifier
{
    /**
     * @param  array<string, scalar|null>  $variables  placeholders, e.g. ['name' => 'خالد', 'amount' => '1,000.00']
     * @param  list<array{email?: ?string, mobile?: ?string, name?: ?string}>  $recipients
     * @return list<OutgoingMessage>
     */
    public function send(NotificationEvent $event, array $variables, array $recipients, ?string $staffAlertPermission = null): array
    {
        $template = MessageTemplate::where('event', $event->value)->first();
        $messages = [];

        foreach ($recipients as $recipient) {
            $vars = $variables + ['name' => $recipient['name'] ?? '', 'office' => Settings::get('site_name', tenant('name'))];

            if ($template?->email_enabled && filled($recipient['email'] ?? null) && filled($template->email_body)) {
                $messages[] = $this->queue(MessageChannel::Mail, $event, $recipient['email'], $this->render($template->email_subject, $vars), $this->render($template->email_body, $vars));
            }
            if ($template?->sms_enabled && filled($recipient['mobile'] ?? null) && filled($template->sms_body)) {
                $messages[] = $this->queue(MessageChannel::Sms, $event, $recipient['mobile'], null, $this->render($template->sms_body, $vars));
            }
        }

        if ($staffAlertPermission !== null) {
            $staff = User::permission($staffAlertPermission)->where('is_active', true)->get();
            if ($staff->isNotEmpty()) {
                FilamentNotification::make()
                    ->title($event->getLabel())
                    ->body($template ? strip_tags($this->render($template->sms_body ?: $template->email_subject, $variables)) : null)
                    ->warning()
                    ->sendToDatabase($staff);
            }
        }

        return $messages;
    }

    /** Replace {placeholders}; unknown placeholders are left visible so template mistakes are noticed. */
    public function render(?string $text, array $variables): string
    {
        return strtr((string) $text, collect($variables)->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => (string) $v])->all());
    }

    private function queue(MessageChannel $channel, NotificationEvent $event, string $recipient, ?string $subject, string $body): OutgoingMessage
    {
        $message = OutgoingMessage::create([
            'channel' => $channel,
            'event' => $event->value,
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $body,
            'status' => 'pending',
        ]);
        SendOutgoingMessage::dispatch($message->id);

        return $message;
    }
}
