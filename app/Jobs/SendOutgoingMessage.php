<?php

namespace App\Jobs;

use App\Enums\MessageChannel;
use App\Models\OutgoingMessage;
use App\Services\Messaging\SmsGateway;
use App\Support\PlanLimits;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

/** Delivers one logged email/SMS/WhatsApp message (legacy email_queue processed by cron/perminute). Runs in the office's context. */
class SendOutgoingMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public int $messageId)
    {
    }

    public function handle(SmsGateway $sms, \App\Services\Messaging\WhatsAppGateway $whatsapp): void
    {
        $message = OutgoingMessage::find($this->messageId);
        if ($message === null || $message->status === 'sent') {
            return;
        }

        // The public demo office never reaches real phones or inboxes.
        if (tenancy()->initialized && tenant()->isDemo()) {
            $message->update(['status' => 'failed', 'error' => __('حساب تجريبي: لا تُرسل رسائل حقيقية.')]);
            $message->campaign?->increment('failed_count');

            return;
        }

        if ($message->channel === MessageChannel::WhatsApp) {
            $result = $whatsapp->send($message->recipient, $message->body);
            if ($result->ok) {
                $message->update(['status' => 'sent', 'sent_at' => now(), 'provider' => 'whatsapp', 'error' => null]);
                $message->campaign?->increment('sent_count');

                return;
            }
            // not on WhatsApp, template not approved, token expired… the reminder still goes out by SMS
            if (blank(Settings::get('sms_provider'))) {
                $message->update(['status' => 'failed', 'provider' => 'whatsapp', 'error' => 'WhatsApp: '.$result->error]);
                $message->campaign?->increment('failed_count');

                return;
            }
            $message->update(['channel' => MessageChannel::Sms, 'error' => 'WhatsApp: '.$result->error.' → SMS']);
        }

        if ($message->channel === MessageChannel::Sms) {
            if (PlanLimits::reached('sms')) {
                $message->update(['status' => 'failed', 'error' => __('تم بلوغ حد رسائل SMS الشهري في باقة المؤسسة.')]);
                $message->campaign?->increment('failed_count');

                return;
            }
            $result = $sms->send($message->recipient, $message->body);
            $message->update($result->ok
                ? ['status' => 'sent', 'sent_at' => now(), 'provider' => Settings::get('sms_provider'), 'error' => str_starts_with((string) $message->error, 'WhatsApp:') ? $message->error : null]
                : ['status' => 'failed', 'error' => $result->error, 'provider' => Settings::get('sms_provider')]);
            $message->campaign?->increment($result->ok ? 'sent_count' : 'failed_count');

            return;
        }

        try {
            $this->mailer()->html(self::layout((string) $message->subject, (string) $message->body), function ($mail) use ($message) {
                $mail->to($message->recipient)->subject((string) $message->subject);
                if ($from = Settings::get('email')) {
                    $mail->replyTo($from, Settings::get('site_name'));
                }
            });
            $message->update(['status' => 'sent', 'sent_at' => now(), 'provider' => Settings::get('mail_driver', 'platform'), 'error' => null]);
            $message->campaign?->increment('sent_count');
        } catch (\Throwable $e) {
            $message->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
            $message->campaign?->increment('failed_count');
        }
    }

    /** Wraps a template or free-text body in the organisation's branded, right-to-left email layout. */
    public static function layout(string $subject, string $body): string
    {
        $html = strip_tags($body) === $body ? nl2br(e($body), false) : $body; // free text (e.g. an inbox reply) keeps its lines
        $domain = tenancy()->initialized ? tenant()->domains()->value('domain') : null;
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        if (! tenancy()->initialized) { // platform emails (subscription reminders, upgrade requests)
            return view('mail.layout', ['subject' => $subject, 'body' => $html, 'org' => __('عقار نوفا'), 'logo' => null, 'contact' => [], 'locale' => app()->getLocale()])->render();
        }

        return view('mail.layout', [
            'subject' => $subject,
            'body' => $html,
            'org' => \App\Support\Website::name(),
            'logo' => $domain && filled(Settings::get('logo')) ? $scheme.'://'.$domain.route('site.logo', [], false) : null,
            'contact' => [trim(Settings::get('address1').' — '.Settings::get('address2'), ' —'), Settings::get('phone'), Settings::get('email')],
            'locale' => (string) Settings::get('locale', 'ar'),
        ])->render();
    }

    /** The office's own SMTP server when configured, otherwise the platform mailer. */
    private function mailer(): \Illuminate\Contracts\Mail\Mailer
    {
        if (Settings::get('mail_driver') !== 'smtp' || blank(Settings::get('smtp_host'))) {
            return Mail::mailer();
        }

        return Mail::build([
            'transport' => 'smtp',
            'host' => Settings::get('smtp_host'),
            'port' => (int) Settings::get('smtp_port', 587),
            'username' => Settings::get('smtp_username'),
            'password' => Settings::get('smtp_password'),
            'scheme' => (int) Settings::get('smtp_port', 587) === 465 ? 'smtps' : 'smtp',
        ]);
    }
}
