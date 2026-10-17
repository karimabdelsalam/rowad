<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Admin\Resources\ReceiptVouchers\Pages\CreateReceiptVoucher;
use App\Filament\Admin\Resources\RentContracts\Pages\CreateRentContract;
use App\Filament\Admin\Resources\Units\Pages\CreateUnit;
use App\Models\Client;
use App\Models\Owner;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** "+" beside owner / client pickers: add the person in place and have them selected, without leaving the form. */
class QuickCreateTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeTenancy();
        $this->actingAs(User::create(['type' => 'staff', 'name' => 'مدير', 'email' => 'admin@test.local', 'password' => 'secret-password', 'is_super_admin' => true]));
        Filament::setCurrentPanel('admin');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    public function test_a_tenant_is_added_from_the_rent_contract_form_and_selected(): void
    {
        $page = Livewire::test(CreateRentContract::class);
        $item = array_key_first($page->get('data.parties'));

        $page->callAction(TestAction::make('createOption')->schemaComponent("parties.{$item}.client_id"), [
            'kind' => 'individual', 'first_name' => 'سعد', 'family_name' => 'المطيري', 'mobile' => '0553334444',
            'id_type' => 'iqama', 'id_number' => '2412345678',
        ])->assertHasNoActionErrors();

        $client = Client::where('id_number', '2412345678')->firstOrFail();
        $this->assertSame('سعد المطيري', $client->full_name);
        $this->assertEquals($client->id, $page->get("data.parties.{$item}.client_id"));
    }

    public function test_an_owner_is_added_from_the_unit_form_and_a_company_from_an_invoice(): void
    {
        $page = Livewire::test(CreateUnit::class);
        $item = array_key_first($page->get('data.owner_shares'));
        $page->callAction(TestAction::make('createOption')->schemaComponent("owner_shares.{$item}.owner_id"), [
            'first_name' => 'نورة', 'family_name' => 'القحطاني', 'id_type' => 'national_id', 'id_number' => '1098765432',
        ])->assertHasNoActionErrors();
        $this->assertEquals(Owner::where('id_number', '1098765432')->value('id'), $page->get("data.owner_shares.{$item}.owner_id"));

        Livewire::test(CreateInvoice::class)
            ->callAction(TestAction::make('createOption')->schemaComponent('client_id'), ['kind' => 'company', 'first_name' => 'خالد'])
            ->assertHasActionErrors(['company_name' => 'required']);
        $invoice = Livewire::test(CreateInvoice::class)->callAction(TestAction::make('createOption')->schemaComponent('client_id'), [
            'kind' => 'company', 'company_name' => 'شركة المدار', 'company_registration_number' => '1010998877', 'first_name' => 'خالد',
        ])->assertHasNoActionErrors();
        $this->assertEquals(Client::where('company_name', 'شركة المدار')->value('id'), $invoice->get('data.client_id'));
    }

    public function test_a_voucher_adds_the_party_its_type_asks_for(): void
    {
        $page = Livewire::test(CreateReceiptVoucher::class)->fillForm(['party_type' => 'owner']);
        $page->callAction(TestAction::make('createOption')->schemaComponent('party_id'), ['first_name' => 'فهد', 'family_name' => 'الدوسري'])
            ->assertHasNoActionErrors();
        $this->assertEquals(Owner::where('full_name', 'فهد الدوسري')->value('id'), $page->get('data.party_id'));
        $this->assertSame(0, Client::count());
    }

    public function test_the_plus_is_hidden_from_users_who_cannot_add_people(): void
    {
        $clerk = User::create(['type' => 'staff', 'name' => 'موظف', 'email' => 'clerk@test.local', 'password' => 'secret-password']);
        $clerk->givePermissionTo('invoices.view_any', 'invoices.create');
        $this->actingAs($clerk);

        Livewire::test(CreateInvoice::class)->assertActionHidden(TestAction::make('createOption')->schemaComponent('client_id'));
    }
}
