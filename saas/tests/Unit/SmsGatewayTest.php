<?php

namespace Tests\Unit;

use App\Services\Messaging\SmsGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsGatewayTest extends TestCase
{
    private array $config = ['api_key' => 'KEY', 'secret_key' => 'SECRET', 'sender' => 'ROWAD', 'country_code' => '966'];

    public function test_number_normalisation_matches_legacy_fixMobileNumber(): void
    {
        $this->assertSame('966555123456', SmsGateway::normalise('0555123456', '966'));
        $this->assertSame('966555123456', SmsGateway::normalise('+966 55 512 3456', '966'));
        $this->assertSame('966555123456', SmsGateway::normalise('00966555123456', '966'));
    }

    public function test_unifonic_request_and_success(): void
    {
        Http::fake(['api.unifonic.com/*' => Http::response(['success' => true, 'data' => ['MessageID' => 9]])]);

        $result = (new SmsGateway)->send('0555123456', 'مرحبا', $this->config + ['provider' => 'unifonic']);

        $this->assertTrue($result->ok);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.unifonic.com/rest/Messages/Send'
            && $r['AppSid'] === 'KEY' && $r['Recipient'] === '966555123456' && $r['Body'] === 'مرحبا');
    }

    public function test_twilio_failure_message_is_reported(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['error_message' => 'Invalid To number', 'error_code' => 21211], 400)]);

        $result = (new SmsGateway)->send('0555123456', 'x', $this->config + ['provider' => 'twilio']);

        $this->assertFalse($result->ok);
        $this->assertSame('Invalid To number', $result->error);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/Accounts/KEY/Messages.json') && $r['To'] === '+966555123456' && $r['From'] === '+ROWAD');
    }

    public function test_mobily_sms_requires_code_100(): void
    {
        Http::fake(['www.mobilysms.net/*' => Http::sequence()->push('100')->push('2')]);
        $gateway = new SmsGateway;

        $this->assertTrue($gateway->send('0555123456', 'x', $this->config + ['provider' => 'mobilysms'])->ok);
        $this->assertFalse($gateway->send('0555123456', 'x', $this->config + ['provider' => 'mobilysms'])->ok);
    }

    public function test_every_provider_has_an_implementation(): void
    {
        Http::fake(fn () => Http::response('', 500));
        foreach (SmsGateway::PROVIDERS as $provider) {
            $result = (new SmsGateway)->send('0555123456', 'x', $this->config + ['provider' => $provider]);
            $this->assertFalse($result->ok, $provider);
            $this->assertNotSame('SMS provider is not configured', $result->error, $provider);
        }
    }

    public function test_unconfigured_provider_fails_without_calling_out(): void
    {
        Http::fake();
        $this->assertFalse((new SmsGateway)->send('0555', 'x', ['provider' => null, 'country_code' => '966'])->ok);
        Http::assertNothingSent();
    }
}
