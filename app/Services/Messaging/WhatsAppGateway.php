<?php

namespace App\Services\Messaging;

use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * WhatsApp Business (Meta Cloud API). Business-initiated messages must use a template approved in
 * WhatsApp Manager; the office creates one whose body has a single variable ({{1}}) and the rendered
 * reminder text is passed as that variable, so the office's own message templates keep working.
 */
class WhatsAppGateway
{
    public const API = 'https://graph.facebook.com/v21.0';

    /** Events that never go through WhatsApp: one-time codes need Meta's separate authentication templates. */
    public const SMS_ONLY_EVENTS = ['login_otp', 'password_reset_otp'];

    public static function enabled(): bool
    {
        return tenancy()->initialized
            && Settings::enabled('whatsapp_enabled')
            && filled(Settings::get('whatsapp_phone_number_id'))
            && filled(Settings::get('whatsapp_access_token'))
            && filled(Settings::get('whatsapp_template'));
    }

    public function send(string $to, string $text): SmsResult
    {
        $phoneId = (string) Settings::get('whatsapp_phone_number_id');
        if (! preg_match('/^\d{5,25}$/', $phoneId)) {
            return SmsResult::failed(__('معرّف رقم واتساب غير صالح.'));
        }
        // Meta rejects newlines/tabs and more than four spaces in a row inside template variables
        $param = mb_substr(trim(preg_replace(['/[\r\n\t]+/', '/ {4,}/'], [' — ', '   '], $text)), 0, 1024);

        try {
            $response = Http::withToken((string) Settings::get('whatsapp_access_token'))->acceptJson()->timeout(15)
                ->post(self::API.'/'.$phoneId.'/messages', [
                    'messaging_product' => 'whatsapp',
                    'to' => SmsGateway::normalise($to),
                    'type' => 'template',
                    'template' => [
                        'name' => (string) Settings::get('whatsapp_template'),
                        'language' => ['code' => (string) Settings::get('whatsapp_language', 'ar')],
                        'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $param]]]],
                    ],
                ]);
        } catch (Throwable $e) {
            return SmsResult::failed(mb_substr($e->getMessage(), 0, 300));
        }

        if ($response->successful() && filled($response->json('messages.0.id'))) {
            return SmsResult::sent();
        }

        return SmsResult::failed(mb_substr((string) ($response->json('error.message') ?? 'HTTP '.$response->status()), 0, 300));
    }

    /** A wa.me link a staff member can open on their own phone — no API, no setup. */
    public static function shareUrl(string $mobile, string $text): string
    {
        return 'https://wa.me/'.SmsGateway::normalise($mobile).'?text='.rawurlencode($text);
    }
}
