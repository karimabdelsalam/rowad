<?php

namespace Tests\Feature;

use App\Models\Installment;
use App\Models\PrintTemplate;
use App\Models\User;
use App\Services\Contracts\ContractService;
use App\Services\Documents\DocumentRenderer;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\VoucherService;
use App\Services\Portal\PortalAccounts;
use App\Services\Zatca\ZatcaQrCode;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class DocumentsTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-01-10 10:00:00');
        $tenant = $this->initializeTenancy(['name' => 'مكتب الإتقان']);
        $this->host = 'http://'.$tenant->domains()->value('domain');
        Settings::set('vat_number', '310122393500003');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        Date::setTestNow();
        parent::tearDown();
    }

    private function contract(): \App\Models\RentContract
    {
        $unit = $this->makeUnit([[100, 100]], ['title' => 'شقة الروضة 4']);

        return app(ContractService::class)->createRent([
            'unit_id' => $unit->id, 'calendar' => 'gregorian', 'contract_date' => '2026-01-01', 'start_date' => '2026-01-01',
            'end_date' => '2026-12-31', 'period_unit' => 'year', 'period_count' => 1, 'payment_cycle' => 'quarter',
            'rent_amount' => 11500, 'vat_applicable' => true, 'vat_percent' => 15, 'vat_amount' => 1500,
        ], [$this->makeClient()->id => 100]);
    }

    private function staff(string $role = 'admin'): User
    {
        $user = User::create(['name' => 'موظف', 'email' => "{$role}@office.test", 'password' => 'secret-password']);
        $user->assignRole($role);

        return $user;
    }

    public function test_renderer_supports_legacy_placeholders_wildcards_and_blocks(): void
    {
        PrintTemplate::create(['key' => 'demo', 'title' => 'x', 'content' =>
            '{org_arname}|{$}|{number}|{serial}|{doc_date}|{item_a}-{item_b}|<!--{hide}-->secret<!--{hide}-->|{missing}']);

        $html = app(DocumentRenderer::class)->render('demo',
            ['number' => 'RC-1', 'date' => '2026-01-01', 'item_*' => ['a' => 'A', 'b' => 'B']], ['hide' => '']);

        $this->assertSame('مكتب الإتقان|SAR|رقــم المستند: RC-1|RC-1|تاريخ المستند: 2026-01-01|A-B||{missing}', $html);
    }

    public function test_rent_contract_prints_with_parties_terms_qr_and_a_working_verification_link(): void
    {
        $contract = $this->contract();
        $this->actingAs($this->staff());

        $response = $this->get("{$this->host}/print/rent_contract/{$contract->id}")->assertOk();
        $response->assertSee($contract->number)->assertSee('شقة الروضة 4')->assertSee('11,500.00')
            ->assertSee('فقط أحد عشر ألف وخمسمئة ريال لا غير')->assertSee('<img src="data:image/', false);

        auth()->logout();
        \Illuminate\Support\Facades\URL::forceRootUrl($this->host);
        $verify = app(\App\Services\Documents\DocumentData::class)->verifyUrl($contract);
        $this->get($verify)->assertOk()->assertSee($contract->number);
        $tampered = str_replace("/rent_contract/{$contract->id}?", '/rent_contract/'.($contract->id + 1).'?', $verify);
        $this->get($tampered)->assertForbidden();
        $this->get("{$this->host}/verify/rent_contract/{$contract->id}")->assertForbidden(); // unsigned
    }

    public function test_invoice_from_installments_has_correct_totals_and_a_valid_zatca_qr(): void
    {
        $contract = $this->contract();
        $installments = $contract->installments()->orderBy('due_date')->take(2)->get()->load(['unit', 'contract.clients']);

        $invoice = app(InvoiceService::class)->fromInstallments($installments);

        $this->assertSame(['INV-2026-00001', '20000.00', '3000.00', '23000.00'], [$invoice->number, $invoice->subtotal, $invoice->vat_total, $invoice->total]);
        $this->assertSame(2, Installment::where('invoice_id', $invoice->id)->count());
        $qr = ZatcaQrCode::decode($invoice->zatca_qr);
        $this->assertSame(['مكتب الإتقان', '310122393500003', '23000.00', '3000.00'], [$qr[1], $qr[2], $qr[4], $qr[5]]);

        $this->expectExceptionMessage('صدرت له فاتورة من قبل');
        app(InvoiceService::class)->fromInstallments($installments->fresh()->load(['unit', 'contract.clients']));
    }

    public function test_manual_invoice_computes_vat_per_line(): void
    {
        $invoice = app(InvoiceService::class)->create([
            ['description' => 'رسوم تسويق', 'quantity' => 2, 'unit_price' => 500, 'vat_percent' => 15],
            ['description' => 'خدمة معفاة', 'quantity' => 1, 'unit_price' => 100, 'vat_percent' => 0],
        ], ['customer_name' => 'شركة الأمل']);

        $this->assertSame(['1100.00', '150.00', '1250.00', 'simplified'], [$invoice->subtotal, $invoice->vat_total, $invoice->total, $invoice->type]);
    }

    public function test_voucher_prints_amount_in_words(): void
    {
        $voucher = app(VoucherService::class)->createReceipt(['issued_at' => '2026-01-10', 'amount' => 2750.25, 'party_name' => 'سالم', 'reason' => 'دفعة']);
        $this->actingAs($this->staff('accountant'));

        $this->get("{$this->host}/print/receipt_voucher/{$voucher->id}")->assertOk()
            ->assertSee('سالم')->assertSee('2,750.25')->assertSee('فقط ألفان وسبعمئة وخمسون ريالا وخمس وعشرون هللة لا غير');
    }

    public function test_print_authorization_by_role_and_ownership(): void
    {
        $mine = $this->contract();
        $other = $this->contract();
        $owner = app(PortalAccounts::class)->issue($mine->unit->owners()->first(), 'own@office.test', 'secret-pass-1')['user'];

        $this->actingAs($owner)->get("{$this->host}/print/rent_contract/{$mine->id}")->assertOk();
        $this->actingAs($owner)->get("{$this->host}/print/rent_contract/{$other->id}")->assertForbidden();
        $this->actingAs($owner)->get("{$this->host}/print/owner_statement/{$owner->owner->id}")->assertOk();

        $this->flushSession();
        $this->actingAs($this->staff('viewer'))->get("{$this->host}/print/rent_contract/{$mine->id}")->assertForbidden();

        auth()->logout();
        $this->get("{$this->host}/print/rent_contract/{$mine->id}")->assertRedirect('/admin/login');
    }
}
