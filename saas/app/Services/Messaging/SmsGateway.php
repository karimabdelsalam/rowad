<?php

namespace App\Services\Messaging;

use App\Support\Settings;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Port of legacy include/lib/class.sms.php: 13 providers, same request formats.
 * Config keys: provider, api_key (username/AppSid/SID), secret_key (password/token), sender.
 */
class SmsGateway
{
    public const PROVIDERS = ['unifonic', 'mobilyws', 'mobilysms', 'mrasel', 'hdvbx', 'twilio', 'nexmo', 'clickatell',
        'clicksend', 'bulksms', 'smsglobal', 'smsapi', 'msg91'];

    /** @return array{provider:?string, api_key:?string, secret_key:?string, sender:?string} */
    public static function configFromSettings(): array
    {
        return [
            'provider' => Settings::get('sms_provider'),
            'api_key' => Settings::get('sms_username'),
            'secret_key' => Settings::get('sms_password'),
            'sender' => Settings::get('sms_sender'),
            'country_code' => (string) Settings::get('phone_code', '966'),
        ];
    }

    /** Legacy fixMobileNumber: drop the leading 0 and add the country code. */
    public static function normalise(string $number, ?string $countryCode = null): string
    {
        $number = preg_replace('/[^0-9]/', '', $number);
        $number = preg_replace('/^00/', '', $number);
        $number = preg_replace('/^0/', '', $number);
        $countryCode ??= (string) Settings::get('phone_code', '966');

        return $countryCode !== '' && ! str_starts_with($number, $countryCode) ? $countryCode.$number : $number;
    }

    public function send(string $to, string $message, ?array $config = null): SmsResult
    {
        $config ??= self::configFromSettings();
        $provider = $config['provider'] ?? null;
        if (! in_array($provider, self::PROVIDERS, true)) {
            return SmsResult::failed('SMS provider is not configured');
        }
        if (blank($config['api_key'] ?? null)) {
            return SmsResult::failed('SMS credentials are missing');
        }

        $to = self::normalise($to, $config['country_code'] ?? null);

        try {
            return $this->{$provider}($to, $message, $config);
        } catch (\Throwable $e) {
            return SmsResult::failed($e->getMessage());
        }
    }

    /**
     * Legacy sms_balance::check: remaining credit as reported by the provider, true when only the
     * connection can be verified, false on failure, null when the provider has no balance API.
     * (smsglobal's legacy check sent a real message, so it is not repeated here.)
     */
    public function balance(?array $config = null): string|bool|null
    {
        $c = $config ?? self::configFromSettings();
        if (! in_array($c['provider'] ?? null, self::PROVIDERS, true) || blank($c['api_key'] ?? null)) {
            return false;
        }
        $basic = fn () => Http::withBasicAuth((string) $c['api_key'], (string) $c['secret_key']);

        try {
            $value = match ($c['provider']) {
                'clickatell' => Http::withHeaders(['Authorization' => $c['api_key']])->get('https://platform.clickatell.com/public-client/balance')->json('balance'),
                'msg91' => ($r = Http::get('https://control.msg91.com/api/balance.php', ['authkey' => $c['api_key'], 'type' => 4]))->ok() ? trim($r->body()) : false,
                'bulksms' => $basic()->get('https://api.bulksms.com/v1/profile')->json('credits.balance'),
                'smsapi' => Http::withToken((string) $c['api_key'])->get('https://api.smsapi.com/profile')->json('points'),
                'twilio' => $basic()->get("https://api.twilio.com/2010-04-01/Accounts/{$c['api_key']}/Balance.json")->json('balance'),
                'nexmo' => Http::get('https://rest.nexmo.com/account/get-balance', ['api_key' => $c['api_key'], 'api_secret' => $c['secret_key']])->json('value'),
                'clicksend' => $basic()->get('https://rest.clicksend.com/v3/account')->json('data.balance'),
                'unifonic' => Http::asForm()->post('https://api.unifonic.com/rest/Account/GetBalance', ['AppSid' => $c['api_key']])->json('data.Balance'),
                'mobilysms' => ($r = Http::get('https://www.mobilysms.net/api/getbalance.php', ['username' => $c['api_key'], 'password' => $c['secret_key']]))->ok() ? trim($r->body()) : false,
                'mobilyws' => Http::asJson()->post('https://mobily.ws/api/balance.php', ['mobile' => $c['api_key'], 'password' => $c['secret_key']])->json('Data.MessageAr'),
                'mrasel' => ($body = trim(Http::get('https://www.mrasel.net/api/credit.php', ['username' => $c['api_key'], 'password' => $c['secret_key']])->body())) !== '' && ! str_starts_with($body, 'Error') ? $body : false,
                default => null,
            };
        } catch (\Throwable) {
            return false;
        }

        return $value === null || $value === false ? ($c['provider'] === 'smsglobal' || $c['provider'] === 'hdvbx' ? null : false) : (string) $value;
    }

    private function unifonic(string $to, string $message, array $c): SmsResult
    {
        $r = Http::asForm()->post('https://api.unifonic.com/rest/Messages/Send', ['AppSid' => $c['api_key'], 'Body' => $message, 'Recipient' => $to, 'SenderID' => $c['sender']]);

        return $this->result($r, $r->ok() && isset($r->json('data')['MessageID']));
    }

    private function mobilyws(string $to, string $message, array $c): SmsResult
    {
        $r = Http::asJson()->post('https://mobily.ws/api/msgSend.php', ['mobile' => $c['api_key'], 'password' => $c['secret_key'], 'numbers' => $to,
            'sender' => $c['sender'], 'msg' => $message, 'lang' => 3, 'applicationType' => 68]);

        return $this->result($r, $r->ok());
    }

    private function mobilysms(string $to, string $message, array $c): SmsResult
    {
        $r = Http::get('https://www.mobilysms.net/api/sendsms.php', ['username' => $c['api_key'], 'password' => $c['secret_key'], 'numbers' => $to,
            'sender' => $c['sender'], 'message' => $this->windows1256($message), 'unicode' => 'e', 'return' => 'full']);

        return $this->result($r, $r->ok() && trim($r->body()) === '100');
    }

    private function mrasel(string $to, string $message, array $c): SmsResult
    {
        $r = Http::get('https://www.mrasel.net/api/', ['username' => $c['api_key'], 'password' => $c['secret_key'], 'mobile' => $to,
            'sender' => $c['sender'], 'message' => $this->windows1256($message), 'thelang' => 1]);

        return $this->result($r, $r->ok());
    }

    private function hdvbx(string $to, string $message, array $c): SmsResult
    {
        $r = Http::get('https://app.hdvbx.com/api/sms', ['key' => $c['api_key'], 'text' => $message, 'number' => $to]);

        return $this->result($r, $r->ok() && $r->json('return') === 'success', $r->json('messages'));
    }

    private function twilio(string $to, string $message, array $c): SmsResult
    {
        $from = str_starts_with((string) $c['sender'], '+') ? $c['sender'] : '+'.$c['sender'];
        $r = Http::asForm()->withBasicAuth($c['api_key'], $c['secret_key'])
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$c['api_key']}/Messages.json", ['Body' => $message, 'To' => '+'.$to, 'From' => $from]);

        return $this->result($r, $r->status() === 201, $r->json('error_message'));
    }

    private function nexmo(string $to, string $message, array $c): SmsResult
    {
        $r = Http::asForm()->post('https://rest.nexmo.com/sms/json', ['api_key' => $c['api_key'], 'api_secret' => $c['secret_key'], 'text' => $message,
            'to' => $to, 'from' => $c['sender'], 'type' => 'unicode']);

        return $this->result($r, $r->ok() && (string) $r->json('messages.0.status') === '0', $r->json('messages.0.error-text'));
    }

    private function clickatell(string $to, string $message, array $c): SmsResult
    {
        $r = Http::get('https://platform.clickatell.com/messages/http/send', ['apiKey' => $c['api_key'], 'content' => $message, 'to' => $to, 'from' => $c['sender']]);

        return $this->result($r, $r->status() === 202, $r->json('error'));
    }

    private function clicksend(string $to, string $message, array $c): SmsResult
    {
        $r = Http::withBasicAuth($c['api_key'], $c['secret_key'])->asJson()
            ->post('https://rest.clicksend.com/v3/sms/send', ['messages' => [['source' => 'php', 'body' => $message, 'to' => '+'.$to, 'from' => $c['sender']]]]);

        return $this->result($r, $r->ok() && $r->json('data.messages.0.status') === 'SUCCESS', $r->json('data.messages.0.status') ?? $r->json('response_msg'));
    }

    private function bulksms(string $to, string $message, array $c): SmsResult
    {
        $r = Http::withBasicAuth($c['api_key'], $c['secret_key'])->asJson()
            ->post('https://api.bulksms.com/v1/messages', ['body' => $message, 'to' => '+'.$to, 'from' => $c['sender']]);

        return $this->result($r, $r->status() === 201);
    }

    private function smsglobal(string $to, string $message, array $c): SmsResult
    {
        $r = Http::get('https://api.smsglobal.com/http-api.php', ['action' => 'sendsms', 'user' => $c['api_key'], 'password' => $c['secret_key'],
            'text' => $message, 'to' => $to, 'from' => ltrim((string) $c['sender'], '+')]);

        return $this->result($r, in_array($r->status(), [201, 202], true) || str_contains($r->body(), 'OK: 0;'));
    }

    private function smsapi(string $to, string $message, array $c): SmsResult
    {
        $r = Http::withToken($c['api_key'])->get('https://api.smsapi.com/sms.do', ['message' => $message, 'to' => $to, 'from' => $c['sender'], 'format' => 'json']);

        return $this->result($r, $r->ok() && empty($r->json('error')), $r->json('error'));
    }

    private function msg91(string $to, string $message, array $c): SmsResult
    {
        $r = Http::get('https://api.msg91.com/api/sendhttp.php', ['authkey' => $c['api_key'], 'message' => $message, 'mobiles' => $to,
            'sender' => $c['sender'], 'route' => 4, 'country' => 0]);

        return $this->result($r, $r->ok());
    }

    private function result(Response $response, bool $ok, mixed $error = null): SmsResult
    {
        return $ok ? SmsResult::sent() : SmsResult::failed(is_string($error) && $error !== '' ? $error : "HTTP {$response->status()}");
    }

    /** Some Saudi gateways expect windows-1256 Arabic. */
    private function windows1256(string $message): string
    {
        return (string) (@iconv('UTF-8', 'windows-1256//TRANSLIT', $message) ?: $message);
    }
}
