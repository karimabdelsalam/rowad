<?php

namespace Tests\Feature;

use App\Enums\NotificationEvent as E;
use App\Models\Campaign;
use App\Models\OutgoingMessage;
use App\Services\Installments\InstallmentScheduler;
use App\Services\Messaging\CampaignService;
use App\Services\Messaging\DomainNotifications;
use App\Services\Messaging\Notifier;
use App\Services\Messaging\WhatsAppGateway;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** WhatsApp Business (Meta Cloud API) as the first channel for reminders, with SMS as the fallback. */
class WhatsAppTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private int $metaStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeTenancy();
        Http::fake([
            'graph.facebook.com/*' => fn () => $this->metaStatus === 200
                ? Http::response(['messages' => [['id' => 'wamid.123']]])
                : Http::response(['error' => ['message' => '(#131026) Message undeliverable']], $this->metaStatus),
            'api.unifonic.com/*' => Http::response(['data' => ['MessageID' => 1]]),
        ]);
        Settings::set('sms_provider', 'unifonic');
        Settings::set('sms_username', 'APP-SID');
        Settings::set('sms_password', 'x');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function connect(): void
    {
        foreach (['whatsapp_enabled' => '1', 'whatsapp_phone_number_id' => '1098765432', 'whatsapp_access_token' => 'EAAG-token',
            'whatsapp_template' => 'aqarnova_notice', 'whatsapp_language' => 'ar'] as $key => $value) {
            Settings::set($key, $value, 'whatsapp');
        }
    }

    private function remind(): void
    {
        $installment = app(InstallmentScheduler::class)->schedule($this->makeRentContract())->sortBy('due_date')->first()->fresh();
        $installment->contract->clients->first()->update(['mobile' => '0551234567']);
        app(DomainNotifications::class)->installmentReminder(E::RentDueIn15Days, $installment->fresh());
    }

    public function test_reminders_stay_on_sms_until_whatsapp_is_connected(): void
    {
        $this->assertFalse(WhatsAppGateway::enabled());
        $this->remind();
        $this->assertSame(1, OutgoingMessage::where('channel', 'sms')->where('status', 'sent')->count());
        $this->assertSame(0, OutgoingMessage::where('channel', 'whatsapp')->count());
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'graph.facebook.com'));
    }

    public function test_reminder_goes_by_whatsapp_template_with_the_rendered_text(): void
    {
        $this->connect();
        $this->remind();

        $message = OutgoingMessage::where('channel', 'whatsapp')->firstOrFail();
        $this->assertSame(['sent', 'whatsapp', null], [$message->status, $message->provider, $message->error]);
        $this->assertSame(0, OutgoingMessage::where('channel', 'sms')->count());

        Http::assertSent(function (Request $r) {
            if (! str_contains($r->url(), 'graph.facebook.com/v21.0/1098765432/messages')) {
                return false;
            }
            $text = $r['template']['components'][0]['parameters'][0]['text'];

            return $r->hasHeader('Authorization', 'Bearer EAAG-token')
                && $r['to'] === '966551234567' && $r['type'] === 'template'
                && $r['template']['name'] === 'aqarnova_notice' && $r['template']['language']['code'] === 'ar'
                && str_contains($text, 'شقة 101') && ! str_contains($text, "\n");
        });

        // one-time codes never go through WhatsApp
        app(Notifier::class)->send(E::LoginOtp, ['code' => '123456'], [['name' => 'x', 'mobile' => '0551234567', 'email' => null]], force: true);
        $this->assertSame('sms', OutgoingMessage::where('event', 'login_otp')->value('channel')->value);
    }

    public function test_whatsapp_failure_falls_back_to_sms_and_keeps_the_reason(): void
    {
        $this->connect();
        $this->metaStatus = 400;
        $this->remind();

        $message = OutgoingMessage::where('event', 'rent_due_15')->firstOrFail();
        $this->assertSame('sms', $message->channel->value);
        $this->assertSame('sent', $message->status);
        $this->assertStringContainsString('Message undeliverable', $message->error);
        $this->assertStringContainsString('→ SMS', $message->error);

        // without any SMS provider the failure is recorded instead
        Settings::set('sms_provider', null);
        $this->remind();
        $last = OutgoingMessage::where('event', 'rent_due_15')->latest('id')->firstOrFail();
        $this->assertSame(['failed', 'whatsapp'], [$last->status, $last->channel->value]);
    }

    public function test_whatsapp_campaign_targets_mobiles_and_needs_a_connection(): void
    {
        $this->makeClient()->update(['mobile' => '0559999999']);
        $campaign = Campaign::create(['subject' => 'عرض', 'channel' => 'whatsapp', 'audience' => 'clients', 'body' => 'وحدات جديدة متاحة', 'status' => 'draft']);
        $this->expectExceptionMessage('اربط واتساب للأعمال');
        try {
            app(CampaignService::class)->send($campaign);
        } finally {
            $this->connect();
            $this->assertSame(1, app(CampaignService::class)->send($campaign->fresh()));
            $this->assertSame('sent', OutgoingMessage::where('event', 'campaign')->value('status'));
        }
    }

    public function test_share_link_for_staff_phones(): void
    {
        $this->assertSame('https://wa.me/966551234567?text=%D9%85%D8%B1%D8%AD%D8%A8%D8%A7', WhatsAppGateway::shareUrl('055 123 4567', 'مرحبا'));
    }
}
