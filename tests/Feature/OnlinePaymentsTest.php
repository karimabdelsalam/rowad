<?php

namespace Tests\Feature;

use App\Enums\NotificationEvent as E;
use App\Filament\Client\Resources\Installments\Pages\ManageInstallments;
use App\Models\Installment;
use App\Models\OnlinePayment;
use App\Models\OutgoingMessage;
use App\Models\ReceiptVoucher;
use App\Models\User;
use App\Services\Messaging\DomainNotifications;
use App\Services\Payments\Payments;
use App\Services\Portal\PortalAccounts;
use App\Support\Settings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** E-collection: pay links in reminders and the portal, verified with the gateway, settled into a receipt voucher. */
class OnlinePaymentsTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private string $host;

    private Installment $installment;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = $this->initializeTenancy();
        $this->host = 'http://'.$tenant->domains()->value('domain');
        $this->installment = app(\App\Services\Installments\InstallmentScheduler::class)->schedule($this->makeRentContract())->sortBy('due_date')->first()->fresh();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function enableMoyasar(): void
    {
        Settings::set('payments_gateway', 'moyasar', 'payments');
        Settings::set('moyasar_publishable_key', 'pk_test_abc', 'payments');
        Settings::set('moyasar_secret_key', 'sk_test_secret', 'payments');
        Settings::set('moyasar_webhook_secret', 'whsec_123', 'payments');
        Settings::flush();
    }

    private function moyasarAnswers(string $status, int $amount, string $id = 'pay_111'): void
    {
        Http::fake(["api.moyasar.com/v1/payments/{$id}" => Http::response([
            'id' => $id, 'status' => $status, 'amount' => $amount, 'currency' => 'SAR',
            'source' => ['type' => 'creditcard', 'company' => 'mada', 'message' => $status === 'failed' ? 'DECLINED' : null],
        ])]);
    }

    public function test_nothing_is_offered_until_the_office_turns_the_gateway_on(): void
    {
        $this->assertFalse(Payments::enabled());
        $this->assertNull(app(Payments::class)->urlFor($this->installment));
        $this->get("{$this->host}/pay/".str_repeat('a', 40))->assertNotFound();
        $this->assertSame(0, OnlinePayment::count());
    }

    public function test_reminders_and_the_portal_carry_the_pay_link(): void
    {
        $this->enableMoyasar();
        $this->assertTrue(Payments::enabled());

        $link = app(Payments::class)->linkFor($this->installment);
        $this->assertSame($link->id, app(Payments::class)->linkFor($this->installment)->id, 'one link per open amount');
        $this->assertSame('1000.00', (string) $link->amount);
        $url = $link->url();
        $this->assertStringStartsWith("{$this->host}/pay/", $url);

        $this->installment->contract->clients->first()->update(['mobile' => '0500000002', 'email' => 'renter@example.com']);
        app(DomainNotifications::class)->installmentReminder(E::RentDueIn15Days, $this->installment->fresh());
        $sms = OutgoingMessage::where('channel', 'sms')->latest('id')->firstOrFail();
        $this->assertStringContainsString($url, $sms->body);
        $this->assertStringContainsString('للسداد الإلكتروني', $sms->body);

        // the public page shows the figures and the Moyasar form with the right amount in halalas
        $this->get($url)->assertOk()->assertSee('شقة 101')->assertSee('1,000.00')->assertSee('pk_test_abc')->assertSee('amount: 100000', false)->assertSee('mysr-form');

        // the tenant sees "pay now" in the portal
        $client = $this->installment->contract->clients->first();
        $user = app(PortalAccounts::class)->issue($client, 'client1', 'secret-password')['user'];
        Filament::setCurrentPanel('client');
        $this->actingAs($user);
        Livewire::test(ManageInstallments::class)->assertSee('ادفع الآن')->assertSee($url);

        // and the mobile API gets the same link
        $token = $user->createToken('phone')->plainTextToken;
        $this->getJson("{$this->host}/api/v1/client/installments", ['Authorization' => "Bearer {$token}"])->assertOk()->assertJsonFragment(['pay_url' => $url]);
    }

    public function test_a_paid_callback_collects_the_installment_and_issues_a_receipt_once(): void
    {
        $this->enableMoyasar();
        $link = app(Payments::class)->linkFor($this->installment);
        $this->moyasarAnswers('paid', 100000);

        $this->get("{$this->host}/pay/{$link->token}/callback?id=pay_111&status=paid&message=APPROVED")->assertRedirect("{$this->host}/pay/{$link->token}");
        $link->refresh();
        $this->assertSame('paid', $link->status);
        $this->assertSame('pay_111', $link->gateway_id);
        $this->assertSame('creditcard mada', $link->source);
        $this->assertTrue($this->installment->fresh()->is_collected);
        $voucher = ReceiptVoucher::findOrFail($link->receipt_voucher_id);
        $this->assertSame('1000.00', (string) $voucher->amount);
        $this->assertSame('card', $voucher->payment_method->value);
        $this->assertSame('client', $voucher->party_type);
        $this->assertStringContainsString('pay_111', $voucher->reason);
        $this->get("{$this->host}/pay/{$link->token}")->assertOk()->assertSee('تم السداد بنجاح')->assertSee($voucher->number);

        // the webhook arriving later for the same payment changes nothing
        $this->postJson("{$this->host}/webhooks/moyasar", ['type' => 'payment_paid', 'secret_token' => 'whsec_123', 'data' => ['id' => 'pay_111', 'metadata' => ['token' => $link->token]]])
            ->assertOk()->assertJson(['ok' => true, 'status' => 'paid']);
        $this->assertSame(1, ReceiptVoucher::count());
        $this->assertSame(1, OnlinePayment::count());
    }

    public function test_the_gateway_is_the_only_truth(): void
    {
        $this->enableMoyasar();
        $link = app(Payments::class)->linkFor($this->installment);

        // the browser says paid, the gateway says failed
        $this->moyasarAnswers('failed', 100000);
        $this->get("{$this->host}/pay/{$link->token}/callback?id=pay_111&status=paid")->assertRedirect();
        $this->assertSame('failed', $link->fresh()->status);
        $this->assertSame('DECLINED', $link->fresh()->failure);
        $this->assertFalse($this->installment->fresh()->is_collected);
        $this->get("{$this->host}/pay/{$link->token}")->assertOk()->assertSee('لم تكتمل عملية الدفع');

        // paid, but for a different amount than the link
        $this->moyasarAnswers('paid', 50000, 'pay_222');
        $this->get("{$this->host}/pay/{$link->token}/callback?id=pay_222&status=paid")->assertRedirect();
        $this->assertSame('failed', $link->fresh()->status);
        $this->assertStringContainsString('amount mismatch', $link->fresh()->failure);
        $this->assertFalse($this->installment->fresh()->is_collected);
        $this->assertSame(0, ReceiptVoucher::count());

        // a webhook with the wrong secret is refused; the right one with an unknown token is ignored
        $this->postJson("{$this->host}/webhooks/moyasar", ['secret_token' => 'nope', 'data' => ['id' => 'pay_111']])->assertForbidden();
        $this->postJson("{$this->host}/webhooks/moyasar", ['secret_token' => 'whsec_123', 'data' => ['id' => 'pay_111', 'metadata' => ['token' => 'zzz']]])->assertStatus(202);

        // a webhook with the right secret settles the payment without any browser involved
        $this->moyasarAnswers('paid', 100000, 'pay_333');
        $this->postJson("{$this->host}/webhooks/moyasar", ['secret_token' => 'whsec_123', 'data' => ['id' => 'pay_333', 'metadata' => ['token' => $link->token]]])->assertOk();
        $this->assertSame('paid', $link->fresh()->status);
        $this->assertTrue($this->installment->fresh()->is_collected);
    }

    public function test_a_changed_installment_gets_a_fresh_link_and_the_admin_sees_the_log(): void
    {
        $this->enableMoyasar();
        $first = app(Payments::class)->linkFor($this->installment);
        app(\App\Services\Installments\InstallmentCollector::class)->collectPartial($this->installment, 400);
        $second = app(Payments::class)->linkFor($this->installment->fresh());
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame('600.00', (string) $second->amount);
        $this->assertSame('superseded', $first->fresh()->status);

        $admin = User::create(['type' => 'staff', 'name' => 'مدير', 'email' => 'admin@office.test', 'password' => 'secret-password']);
        $admin->assignRole('admin');
        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);
        $this->get("{$this->host}/admin/online-payments")->assertOk()->assertSee('المدفوعات الإلكترونية')->assertSee('600.00');
    }
}
