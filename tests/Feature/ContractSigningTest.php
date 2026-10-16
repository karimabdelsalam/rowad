<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\RentContracts\Pages\ViewRentContract;
use App\Filament\Client\Resources\RentContracts\Pages\ManageRentContracts;
use App\Models\Client;
use App\Models\ContractSignature;
use App\Models\OutgoingMessage;
use App\Models\RentContract;
use App\Models\User;
use App\Services\Contracts\ContractService;
use App\Services\Portal\PortalAccounts;
use App\Services\Signing\ContractSigning;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** Electronic signature: private links, a code to the party's own mobile, the drawing, evidence and the print. */
class ContractSigningTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private string $host;

    private RentContract $contract;

    private Client $renter;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $office = $this->initializeTenancy();
        $this->host = 'http://'.$office->domains()->value('domain');
        $unit = $this->makeUnit([[100, 100]], ['title' => 'شقة 9']);
        $unit->owners()->first()->update(['mobile' => '0501110000']);
        $this->renter = Client::create(['first_name' => 'سعد', 'family_name' => 'المطيري', 'mobile' => '0552220000']);
        $this->contract = app(ContractService::class)->createRent([
            'unit_id' => $unit->id, 'calendar' => 'gregorian', 'contract_date' => '2026-01-01', 'start_date' => '2026-01-01',
            'end_date' => '2026-12-31', 'period_unit' => 'year', 'period_count' => 1, 'payment_cycle' => 'quarter', 'rent_amount' => 9000,
        ], [$this->renter->id => 100]);
        $this->admin = User::create(['type' => 'staff', 'name' => 'مدير', 'email' => 'admin@test.local', 'password' => 'secret-password', 'is_super_admin' => true]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /** @return array<string, string> mobile => signing token from the messages sent */
    private function links(): array
    {
        return OutgoingMessage::where('event', 'signature_request')->where('channel', 'sms')->orderBy('id')->get()
            ->mapWithKeys(fn ($m) => [$m->recipient => preg_match('#/sign/([A-Za-z0-9]{48})#', $m->body, $x) ? $x[1] : null])->all();
    }

    private function codeFor(string $mobile): string
    {
        $body = OutgoingMessage::where('event', 'signature_otp')->where('recipient', $mobile)->latest('id')->value('body');
        preg_match('/\b(\d{6})\b/', (string) $body, $m);

        return $m[1];
    }

    private function signAs(string $mobile, string $token): void
    {
        $this->post("{$this->host}/sign/{$token}/code")->assertRedirect();
        $this->post("{$this->host}/sign/{$token}", ['code' => $this->codeFor($mobile), 'signature' => self::PNG, 'agree' => '1'])
            ->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_every_party_gets_a_private_link_and_signs_with_a_code_sent_to_their_mobile(): void
    {
        $this->assertCount(2, app(ContractSigning::class)->request($this->contract));
        $links = $this->links();
        $this->assertSame(['0501110000', '0552220000'], array_keys($links));
        $this->assertSame(0, ContractSignature::where('token_hash', $links['0552220000'])->count(), 'only a hash of the token is stored');

        $page = $this->get("{$this->host}/sign/{$links['0552220000']}")->assertOk();
        $page->assertSee($this->contract->number)->assertSee('سعد المطيري')->assertSee(__('أرسل رمز التحقق'));
        $this->get("{$this->host}/sign/".str_repeat('x', 48))->assertNotFound();

        $this->post("{$this->host}/sign/{$links['0552220000']}/code")->assertRedirect();
        $this->post("{$this->host}/sign/{$links['0552220000']}", ['code' => '000000', 'signature' => self::PNG, 'agree' => '1'])
            ->assertSessionHasErrors('sign');
        $this->assertSame(1, ContractSignature::where('mobile', '0552220000')->value('otp_attempts'));
        $this->post("{$this->host}/sign/{$links['0552220000']}", ['code' => $this->codeFor('0552220000'), 'signature' => 'data:image/png;base64,AAAA', 'agree' => '1'])
            ->assertSessionHasErrors('sign');

        $this->post("{$this->host}/sign/{$links['0552220000']}", ['code' => $this->codeFor('0552220000'), 'signature' => self::PNG, 'agree' => '1'])
            ->assertSessionHasNoErrors();
        $tenant = ContractSignature::where('role', 'lessee')->sole();
        $this->assertTrue($tenant->isSigned());
        $this->assertSame(ContractSigning::fingerprint($this->contract->fresh()), $tenant->document_hash);
        $this->assertNotNull($tenant->ip);
        $this->assertNull($this->contract->fresh()->signed_at, 'the owner has not signed yet');

        $this->signAs('0501110000', $links['0501110000']);
        $this->assertNotNull($this->contract->fresh()->signed_at);
        $this->assertTrue($this->admin->notifications()->get()->contains(fn ($n) => str_contains($n->data['title'], 'اكتمل توقيع العقد')));

        $this->actingAs($this->admin);
        $print = $this->get("{$this->host}/print/rent_contract/{$this->contract->id}")->assertOk()->getContent();
        $this->assertSame(2, substr_count($print, 'class="esign"'));
        $this->assertStringContainsString(__('وُقّع إلكترونيًا :at', ['at' => $tenant->signed_at->format('Y-m-d H:i')]), $print);
    }

    public function test_signed_parties_are_asked_again_only_when_the_terms_change(): void
    {
        app(ContractSigning::class)->request($this->contract);
        $links = $this->links();
        $this->signAs('0552220000', $links['0552220000']);

        $this->assertCount(1, app(ContractSigning::class)->request($this->contract->fresh()), 'only the owner is reminded');

        $this->contract->update(['extra_terms' => 'يلتزم المستأجر بصيانة المكيفات']);
        $this->assertCount(2, app(ContractSigning::class)->request($this->contract->fresh()), 'changed terms need everyone again');
    }

    public function test_a_party_can_decline_and_links_expire(): void
    {
        app(ContractSigning::class)->request($this->contract);
        $links = $this->links();

        $this->post("{$this->host}/sign/{$links['0552220000']}/decline", ['reason' => 'أطلب تعديل موعد الدفعة الأولى'])->assertRedirect();
        $this->assertSame(ContractSignature::DECLINED, ContractSignature::where('role', 'lessee')->value('status'));
        $this->get("{$this->host}/sign/{$links['0552220000']}")->assertOk()->assertSee(__('رفضت توقيع العقد'));
        $this->assertTrue($this->admin->notifications()->get()->contains(fn ($n) => str_contains($n->data['title'], 'رفض توقيع العقد')));

        ContractSignature::where('role', 'lessor')->update(['expires_at' => now()->subMinute()]);
        $this->post("{$this->host}/sign/{$links['0501110000']}/code")->assertSessionHasErrors('sign');
    }

    public function test_parties_without_a_mobile_block_the_request(): void
    {
        $this->renter->update(['mobile' => null]);

        $this->expectExceptionMessage('سعد المطيري');
        app(ContractSigning::class)->request($this->contract->fresh());
    }

    public function test_staff_request_from_the_contract_page_and_tenants_sign_from_their_portal(): void
    {
        $this->actingAs($this->admin);
        Filament::setCurrentPanel('admin');
        Livewire::test(ViewRentContract::class, ['record' => $this->contract->id])
            ->callAction('requestSignatures')
            ->assertNotified();
        $this->assertSame(2, ContractSignature::count());
        Livewire::test(ViewRentContract::class, ['record' => $this->contract->id])->assertSee(__('التوقيع الإلكتروني'))->assertSee(__('بانتظار التوقيع'));

        $user = app(PortalAccounts::class)->issue($this->renter, 'saad@portal.test', 'secret-pass-1')['user'];
        $this->flushSession();
        $this->actingAs($user);
        Filament::setCurrentPanel('client');
        $redirect = Livewire::test(ManageRentContracts::class)
            ->assertTableActionVisible('sign', $this->contract)
            ->callTableAction('sign', $this->contract)
            ->effects['redirect'] ?? null;
        $this->assertMatchesRegularExpression('#/sign/[A-Za-z0-9]{48}$#', (string) $redirect);
        $this->get($this->host.parse_url($redirect, PHP_URL_PATH))->assertOk()->assertSee(__('توقّع بصفتك'));
    }
}
