<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Owners\Pages\ListOwners;
use App\Filament\Owner\Resources\SupportTickets\Pages\ManageSupportTickets;
use App\Models\Owner;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Contracts\ContractService;
use App\Services\Installments\InstallmentCollector;
use App\Services\Portal\PortalAccounts;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** Owners and clients must only ever see their own records. */
class PortalsTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-01-10');
        $tenant = $this->initializeTenancy();
        $this->host = 'http://'.$tenant->domains()->value('domain');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        Date::setTestNow();
        parent::tearDown();
    }

    /** Two owners, each with a rented unit and its own renter. */
    private function scenario(): array
    {
        $out = [];
        foreach (['A', 'B'] as $key) {
            $unit = $this->makeUnit([[100, 100]], ['title' => "وحدة {$key}"]);
            $client = $this->makeClient();
            $contract = app(ContractService::class)->createRent([
                'unit_id' => $unit->id, 'calendar' => 'gregorian', 'contract_date' => '2026-01-01', 'start_date' => '2026-01-01',
                'end_date' => '2026-12-31', 'period_unit' => 'year', 'period_count' => 1, 'payment_cycle' => 'year', 'rent_amount' => $key === 'A' ? 11111 : 22222,
            ], [$client->id => 100]);
            app(InstallmentCollector::class)->collect($contract->installments()->first());
            $owner = $unit->owners()->first();
            $out[$key] = [
                'unit' => $unit, 'contract' => $contract,
                'owner' => app(PortalAccounts::class)->issue($owner, "owner{$key}@office.test", 'secret-pass-1')['user'],
                'client' => app(PortalAccounts::class)->issue($client, "client{$key}@office.test", 'secret-pass-1')['user'],
            ];
        }

        return $out;
    }

    public function test_owner_sees_only_their_units_contracts_collections_and_statement(): void
    {
        $s = $this->scenario();
        $this->actingAs($s['A']['owner']);

        $this->get("{$this->host}/owner")->assertOk();
        $this->get("{$this->host}/owner/units")->assertOk()->assertSee('وحدة A')->assertDontSee('وحدة B');
        $this->get("{$this->host}/owner/rent-contracts")->assertOk()->assertSee($s['A']['contract']->number)->assertDontSee($s['B']['contract']->number);
        $this->get("{$this->host}/owner/installments")->assertOk()->assertSee('11,111')->assertDontSee('22,222');
        $this->get("{$this->host}/owner/owner-transactions")->assertOk()->assertSee('11,111')->assertDontSee('22,222');
    }

    public function test_owner_filters_collections_by_unit_and_tenant_within_their_own_units(): void
    {
        $s = $this->scenario();
        $this->actingAs($s['A']['owner']);
        \Filament\Facades\Filament::setCurrentPanel('owner');
        $mine = $s['A']['contract']->installments()->get();
        $s['B']['contract']->clients()->first()->update(['first_name' => 'سلمان', 'family_name' => 'العتيبي']);
        $tenantA = $s['A']['contract']->clients()->first()->full_name;
        $tenantB = 'سلمان العتيبي';

        \Livewire\Livewire::test(\App\Filament\Owner\Resources\Installments\Pages\ManageInstallments::class)
            ->filterTable('tenant', ['name' => $tenantA])->assertCanSeeTableRecords($mine)
            ->filterTable('tenant', ['name' => $tenantB])->assertCountTableRecords(0)
            ->assertTableFilterExists('unit_id');
    }

    public function test_owner_prints_the_sheet_of_their_own_units_only(): void
    {
        $s = $this->scenario();
        $this->actingAs($s['A']['owner']);

        $this->get("{$this->host}/print/unit/{$s['A']['unit']->id}")->assertOk()->assertSee('وحدة A');
        $this->get("{$this->host}/print/unit/{$s['B']['unit']->id}")->assertForbidden();
    }

    public function test_client_sees_only_their_contracts_and_payments(): void
    {
        $s = $this->scenario();
        $this->actingAs($s['B']['client']);

        $this->get("{$this->host}/client/rent-contracts")->assertOk()->assertSee($s['B']['contract']->number)->assertDontSee($s['A']['contract']->number);
        $this->get("{$this->host}/client/installments")->assertOk();

        Filament::setCurrentPanel('client');
        $mine = $s['B']['contract']->installments;
        $theirs = $s['A']['contract']->installments;
        Livewire::test(\App\Filament\Client\Resources\Installments\Pages\ManageInstallments::class)
            ->filterTable('is_collected', true)
            ->assertCanSeeTableRecords($mine)
            ->assertCanNotSeeTableRecords($theirs);
    }

    public function test_portal_users_cannot_cross_into_other_panels(): void
    {
        $s = $this->scenario();

        $this->actingAs($s['A']['owner'])->get("{$this->host}/client")->assertForbidden();
        $this->actingAs($s['A']['owner'])->get("{$this->host}/admin")->assertForbidden();
        $this->actingAs($s['A']['client'])->get("{$this->host}/owner")->assertForbidden();
    }

    public function test_owner_ticket_ownership_is_set_by_the_server(): void
    {
        $s = $this->scenario();
        $this->actingAs($s['A']['owner']);
        Filament::setCurrentPanel('owner');

        // Another owner's unit is not even an allowed option.
        Livewire::test(ManageSupportTickets::class)
            ->callAction('create', ['type' => 'problem', 'unit_id' => $s['B']['unit']->id, 'subject' => 'x', 'message' => 'x'])
            ->assertHasActionErrors(['unit_id']);

        Livewire::test(ManageSupportTickets::class)
            ->callAction('create', ['type' => 'problem', 'unit_id' => $s['A']['unit']->id, 'subject' => 'تسريب مياه', 'message' => 'يوجد تسريب'])
            ->assertHasNoActionErrors();

        $ticket = SupportTicket::sole();
        $this->assertSame($s['A']['owner']->owner->id, $ticket->owner_id);
        $this->assertSame($s['A']['unit']->id, $ticket->unit_id);
        $this->assertStringStartsWith('TCK-', $ticket->code);

        // Server-side guard also drops a foreign unit if it slips past the form.
        $data = \App\Filament\Owner\Resources\SupportTickets\SupportTicketResource::prepareTicket(
            ['unit_id' => $s['B']['unit']->id, 'subject' => 's', 'message' => 'm', 'type' => 'request'], ['owner_id' => 1]);
        $this->assertNull($data['unit_id']);
    }

    public function test_staff_issue_credentials_and_sign_in_as_owner(): void
    {
        $owner = Owner::create(['first_name' => 'مالك', 'family_name' => 'جديد', 'email' => 'new.owner@office.test']);
        $staff = User::create(['name' => 'موظف', 'email' => 'staff@office.test', 'password' => 'secret-password']);
        $staff->assignRole('admin');
        $this->actingAs($staff);
        Filament::setCurrentPanel('admin');

        Livewire::test(ListOwners::class)
            ->callAction(TestAction::make('portalAccount')->table($owner), ['login' => 'new.owner@office.test', 'password' => 'chosen-pass-9'])
            ->assertNotified();

        $user = $owner->fresh()->user;
        $this->assertSame('owner', $user->type->value);
        $this->assertTrue(auth()->validate(['email' => 'new.owner@office.test', 'password' => 'chosen-pass-9']));

        URL::forceRootUrl($this->host);
        $url = URL::temporarySignedRoute('portal.impersonate', now()->addMinutes(2), ['user' => $user->id, 'panel' => 'owner']);
        $this->get($url)->assertRedirect('/owner');
        $this->assertTrue(auth()->user()->is($user));
        $this->get("{$this->host}/impersonate/stop")->assertRedirect('/admin');
        $this->assertTrue(auth()->user()->is($staff));
    }
}
