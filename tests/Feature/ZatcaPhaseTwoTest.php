<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\EInvoicing;
use App\Filament\Admin\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Admin\Resources\Invoices\Pages\ViewInvoice;
use App\Jobs\SubmitZatcaInvoice;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Models\ZatcaDevice;
use App\Services\Finance\InvoiceService;
use App\Services\Zatca\Phase2\Certificate;
use App\Services\Zatca\Phase2\InvoiceDocument;
use App\Services\Zatca\Phase2\Onboarding;
use App\Services\Zatca\Phase2\Qr;
use App\Services\Zatca\Phase2\Signer;
use App\Services\Zatca\Phase2\ZatcaException;
use App\Services\Zatca\Phase2\ZatcaInvoicing;
use App\Support\Settings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * ZATCA integration phase against a fake Fatoora gateway that behaves like the real one: it issues a certificate
 * for the CSR it receives, and only passes documents whose hash and ECDSA signature verify with that certificate.
 */
class ZatcaPhaseTwoTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    private const GATEWAY = 'gw-fatoora.zatca.gov.sa/e-invoicing/';

    private ?string $token = null;

    /** @var list<array{path: string, body: array}> */
    private array $received = [];

    /** @var (callable(string, array): mixed)|null answers reporting/clearance instead of the normal checks */
    private $submission = null;

    private bool $faked = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeTenancy();
        foreach ([
            'site_name' => 'مكتب الاختبار العقاري', 'vat_number' => '399999999900003', 'commercial_register' => '1010010000',
            'zatca_street' => 'King Fahd Rd', 'zatca_building_number' => '1234', 'zatca_district' => 'Al Olaya',
            'address1' => 'Riyadh', 'zatca_postal_code' => '12211',
        ] as $key => $value) {
            Settings::set($key, $value, 'identity');
        }
        Settings::flush();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /** A test CA signs whatever CSR arrives, so the certificate carries our own public key — as ZATCA's does. */
    private function issueFor(string $csrPem): string
    {
        $caKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'secp256k1']);
        $ca = openssl_csr_sign(openssl_csr_new(['commonName' => 'TEST-ZATCA-CA'], $caKey, ['digest_alg' => 'sha256']), null, $caKey, 365, ['digest_alg' => 'sha256']);
        openssl_x509_export(openssl_csr_sign($csrPem, $ca, $caKey, 365, ['digest_alg' => 'sha256'], random_int(1000, PHP_INT_MAX)), $pem);

        return base64_encode((string) preg_replace('/-----[^-]+-----|\s+/', '', $pem));
    }

    /** The checks ZATCA runs on every document it receives. */
    private function verifies(array $body): bool
    {
        $xml = base64_decode($body['invoice']);
        if (Signer::invoiceHash($xml) !== $body['invoiceHash'] || ! str_contains($xml, $body['uuid'])) {
            return false;
        }
        preg_match('#<ds:SignatureValue>([^<]+)</ds:SignatureValue>#', $xml, $sig);
        $key = openssl_pkey_get_public(Certificate::fromBinarySecurityToken($this->token)->pem());

        return openssl_verify(base64_decode($body['invoiceHash']), base64_decode($sig[1] ?? ''), $key, OPENSSL_ALGO_SHA256) === 1;
    }

    private function fakeGateway(?callable $submission = null): void
    {
        $this->submission = $submission;
        if ($this->faked) {
            return;
        }
        $this->faked = true;
        Http::fake(function (Request $request) {
            $submission = $this->submission;
            $path = (string) preg_replace('#^https://'.preg_quote(self::GATEWAY, '#').'[^/]+/#', '', $request->url());
            $this->received[] = ['path' => $path, 'body' => $request->data()];
            $credentials = fn () => ['requestID' => 1234567890, 'dispositionMessage' => 'ISSUED', 'binarySecurityToken' => $this->token, 'secret' => 'secret-'.$path];

            return match ($path) {
                'compliance' => $request->header('OTP')[0] === '123345'
                    ? Http::response(($this->token = $this->issueFor(base64_decode($request['csr']))) ? $credentials() : [])
                    : Http::response(['errorCode' => 'Invalid-OTP', 'errorMessage' => 'Invalid OTP'], 400),
                'compliance/invoices' => $this->verifies($request->data())
                    ? Http::response(['validationResults' => ['status' => 'PASS', 'warningMessages' => [], 'errorMessages' => []]])
                    : Http::response(['validationResults' => ['status' => 'ERROR', 'errorMessages' => [['code' => 'signature', 'message' => 'bad signature']]]], 400),
                'production/csids' => Http::response($credentials()),
                default => $submission ? $submission($path, $request->data()) : Http::response($this->verifies($request->data())
                    ? ['validationResults' => ['status' => 'PASS', 'warningMessages' => [], 'errorMessages' => []], 'reportingStatus' => 'REPORTED',
                        'clearanceStatus' => 'CLEARED', 'clearedInvoice' => $path === 'invoices/clearance/single' ? $request['invoice'] : null]
                    : ['validationResults' => ['status' => 'ERROR', 'errorMessages' => [['code' => 'signature', 'message' => 'bad signature']]]],
                    $this->verifies($request->data()) ? 200 : 400),
            };
        });
    }

    private function onboard(): ZatcaDevice
    {
        $this->fakeGateway();

        return app(Onboarding::class)->run('simulation', '123345');
    }

    private function invoice(array $attributes = []): Invoice
    {
        return app(InvoiceService::class)->create([
            ['description' => 'إيجار محل تجاري — يناير', 'quantity' => 1, 'unit_price' => 5000, 'vat_percent' => 15],
            ['description' => 'إيجار شقة سكنية — يناير', 'quantity' => 1, 'unit_price' => 2500, 'vat_percent' => 0],
        ], $attributes)->fresh();
    }

    public function test_onboarding_passes_the_six_compliance_documents_and_gets_a_production_certificate(): void
    {
        $device = $this->onboard();

        $this->assertTrue($device->isReady());
        $this->assertSame(0, $device->counter);
        $this->assertSame(InvoiceDocument::FIRST_PREVIOUS_HASH, $device->last_hash);
        $this->assertSame(['compliance', 'compliance/invoices', 'compliance/invoices', 'compliance/invoices', 'compliance/invoices',
            'compliance/invoices', 'compliance/invoices', 'production/csids'], array_column($this->received, 'path'));
        $this->assertSame(['PASS'], array_values(array_unique(array_column(array_slice($device->onboarding_log, 1, 6), 'result'))));
        $this->assertStringContainsString('BEGIN PRIVATE KEY', $device->private_key);
        $this->assertStringNotContainsString('PRIVATE KEY', (string) \DB::table('zatca_devices')->value('private_key'), 'the key is stored encrypted');
        $this->assertNotNull($device->certificate_expires_at);
        $this->assertTrue(ZatcaInvoicing::enabled());
    }

    public function test_onboarding_refuses_without_the_national_address_or_with_a_bad_otp(): void
    {
        Settings::set('zatca_postal_code', '', 'identity');
        Settings::flush();
        try {
            app(Onboarding::class)->run('simulation', '123345');
            $this->fail('expected a business rule error');
        } catch (\App\Exceptions\BusinessRuleException $e) {
            $this->assertStringContainsString('الرمز البريدي', $e->getMessage());
        }

        Settings::set('zatca_postal_code', '12211', 'identity');
        Settings::flush();
        $this->fakeGateway();
        $this->expectException(ZatcaException::class);
        app(Onboarding::class)->run('simulation', '999999');
    }

    public function test_invoices_are_signed_on_one_chain_and_reported(): void
    {
        $this->onboard();
        Queue::fake();

        $first = $this->invoice();
        $second = $this->invoice();

        Queue::assertPushed(SubmitZatcaInvoice::class, 2);
        $this->assertSame([1, 2], [$first->zatca_icv, $second->zatca_icv]);
        $this->assertSame('pending', $first->zatca_status);
        $this->assertStringContainsString('<cbc:UUID>'.$first->zatca_uuid.'</cbc:UUID>', $first->zatca_xml);
        $this->assertStringContainsString(InvoiceDocument::FIRST_PREVIOUS_HASH, $first->zatca_xml);
        $this->assertStringContainsString($first->zatca_hash, $second->zatca_xml, 'the second invoice carries the first one\'s hash (PIH)');
        $this->assertSame(Signer::invoiceHash($first->zatca_xml), $first->zatca_hash);
        $this->assertSame(2, ZatcaDevice::active()->counter);

        $tags = Qr::decode($first->zatca_qr);
        $this->assertSame('8250.00', $tags[4]);
        $this->assertSame('750.00', $tags[5]);
        $this->assertSame($first->zatca_hash, $tags[6]);

        (new SubmitZatcaInvoice($first->id))->handle(app(ZatcaInvoicing::class));
        $this->assertSame('reported', $first->fresh()->zatca_status);
        $this->assertSame('invoices/reporting/single', last($this->received)['path']);
        $this->assertSame(1, $first->fresh()->zatca_attempts);
    }

    public function test_standard_invoices_are_cleared_and_keep_zatcas_stamped_copy(): void
    {
        $this->onboard();
        Queue::fake();
        $client = Client::create(['first_name' => 'شركة المستأجر', 'family_name' => 'التجارية', 'company_registration_number' => '4030000000',
            'national_address' => ['street' => 'Tahlia St', 'building' => '4321', 'district' => 'Al Andalus', 'city' => 'Jeddah', 'postal' => '23326']]);

        $invoice = $this->invoice(['client_id' => $client->id, 'customer_name' => $client->full_name, 'customer_vat_number' => '311111111100003']);
        $this->assertSame('standard', $invoice->type);
        $this->assertStringContainsString('<cbc:StreetName>Tahlia St</cbc:StreetName>', $invoice->zatca_xml);

        app(ZatcaInvoicing::class)->submit($invoice);
        $this->assertSame('cleared', $invoice->fresh()->zatca_status);
        $this->assertSame('invoices/clearance/single', last($this->received)['path']);
    }

    public function test_gateway_outages_are_retried_by_the_scheduler_and_rejections_are_kept(): void
    {
        $this->onboard();
        Queue::fake();
        $invoice = $this->invoice();
        $this->fakeGateway(fn () => Http::response(['message' => 'Service unavailable'], 503));

        app(ZatcaInvoicing::class)->submit($invoice);
        $this->assertSame('failed', $invoice->fresh()->zatca_status);

        $this->fakeGateway();
        $this->artisan('rowad:zatca')->assertSuccessful();
        $this->assertSame('reported', $invoice->fresh()->zatca_status);
        $this->assertSame(2, $invoice->fresh()->zatca_attempts);

        $rejected = $this->invoice();
        $this->fakeGateway(fn () => Http::response(['validationResults' => ['status' => 'ERROR',
            'errorMessages' => [['code' => 'BR-KSA-37', 'message' => 'The seller address building number must contain 4 digits.']]]], 400));
        app(ZatcaInvoicing::class)->submit($rejected);
        $rejected->refresh();
        $this->assertSame('rejected', $rejected->zatca_status);
        $this->assertStringContainsString('BR-KSA-37', implode(' ', $rejected->zatca_messages));

        $this->artisan('rowad:zatca')->assertSuccessful();
        $this->assertSame('rejected', $rejected->fresh()->zatca_status, 'rejected documents are not resent; they need a credit note');
    }

    public function test_a_credit_note_references_the_invoice_and_continues_the_chain(): void
    {
        $this->onboard();
        Queue::fake();
        $invoice = $this->invoice();

        $note = app(InvoiceService::class)->adjustment($invoice, 'credit', 'إلغاء العقد قبل بدايته')->fresh();

        $this->assertSame('credit', $note->kind);
        $this->assertSame($invoice->number, $note->billing_reference);
        $this->assertSame(2, $note->zatca_icv);
        $this->assertStringContainsString('>381</cbc:InvoiceTypeCode>', $note->zatca_xml);
        $this->assertStringContainsString('<cbc:ID>'.$invoice->number.'</cbc:ID>', $note->zatca_xml);
        $this->assertStringContainsString('إلغاء العقد قبل بدايته', $note->zatca_xml);
        app(ZatcaInvoicing::class)->submit($note);
        $this->assertSame('reported', $note->fresh()->zatca_status);

        $this->expectException(\App\Exceptions\BusinessRuleException::class);
        app(InvoiceService::class)->adjustment($note, 'credit', 'x');
    }

    public function test_the_developer_portal_keeps_testing_with_the_compliance_certificate(): void
    {
        $this->fakeGateway();
        $device = app(Onboarding::class)->run('developer-portal', '123345');
        Queue::fake();
        // the sandbox's production CSID is a fixed sample certificate; ours must not be replaced by it
        \DB::table('zatca_devices')->whereKey($device->id)->update(['production_token' => base64_encode('sample-certificate')]);

        $invoice = $this->invoice();
        app(ZatcaInvoicing::class)->submit($invoice);

        $this->assertSame('reported', $invoice->fresh()->zatca_status);
        $this->assertSame('compliance/invoices', last($this->received)['path']);
    }

    public function test_offices_without_phase_two_keep_the_phase_one_qr(): void
    {
        Queue::fake();
        $invoice = $this->invoice();

        $this->assertFalse(ZatcaInvoicing::enabled());
        $this->assertNull($invoice->zatca_status);
        $this->assertNull($invoice->zatca_xml);
        $this->assertNotNull($invoice->zatca_qr);
        Queue::assertNothingPushed();
    }

    public function test_screens_show_the_status_and_let_staff_resend_download_and_credit(): void
    {
        $this->onboard();
        Queue::fake();
        $invoice = $this->invoice();
        $admin = User::create(['type' => 'staff', 'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'secret-password', 'is_super_admin' => true]);
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(EInvoicing::class)->assertOk()->assertSee(__('بيئة المحاكاة'))->assertSee($invoice->number);
        Livewire::test(ListInvoices::class)->assertOk()->assertCanSeeTableRecords([$invoice])->assertSee(__('بانتظار الإرسال'));

        Livewire::test(ViewInvoice::class, ['record' => $invoice->id])
            ->assertOk()
            ->assertSee(__('عداد الفاتورة (ICV)'))
            ->assertActionVisible('zatcaSubmit')
            ->callAction('zatcaSubmit')
            ->assertNotified();
        $this->assertSame('reported', $invoice->fresh()->zatca_status);

        Livewire::test(ViewInvoice::class, ['record' => $invoice->id])
            ->assertActionHidden('zatcaSubmit')
            ->callAction('xml')
            ->assertFileDownloaded($invoice->number.'.xml');

        Livewire::test(ViewInvoice::class, ['record' => $invoice->id])
            ->callAction('adjust', ['kind' => 'credit', 'reason' => 'خصم متفق عليه'])
            ->assertHasNoActionErrors();
        $note = Invoice::where('kind', 'credit')->firstOrFail();
        $this->assertSame($invoice->number, $note->billing_reference);

        $host = 'http://'.$this->tenant->domains()->value('domain');
        $html = $this->get("{$host}/print/invoice/{$note->id}")->assertOk()->getContent();
        $this->assertStringContainsString(__('إشعار دائن'), $html);
        $this->assertStringContainsString($invoice->number, $html);
    }
}
