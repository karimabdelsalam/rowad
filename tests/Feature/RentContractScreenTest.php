<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Enums\UnitStatus;
use App\Filament\Admin\Resources\RentContracts\Pages\CreateRentContract;
use App\Filament\Admin\Resources\RentContracts\Pages\ListRentContracts;
use App\Filament\Admin\Resources\RentContracts\Pages\ViewRentContract;
use App\Filament\Admin\Resources\RentContracts\RelationManagers\InstallmentsRelationManager;
use App\Models\RentContract;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class RentContractScreenTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-01-10');
        $this->initializeTenancy();
        Filament::setCurrentPanel('admin');
        $user = User::create(['name' => 'مدير', 'email' => 'admin@office.test', 'password' => 'secret-password']);
        $user->assignRole('admin');
        $this->actingAs($user);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        Date::setTestNow();
        parent::tearDown();
    }

    private function formData(int $unitId, array $parties, array $overrides = []): array
    {
        return $overrides + [
            'unit_id' => $unitId,
            'parties' => $parties,
            'calendar' => 'gregorian',
            'contract_date' => '2026-01-01',
            'start_date' => '2026-01-01',
            'period_unit' => 'year',
            'period_count' => 1,
            'end_date' => '2026-12-31',
            'payment_cycle' => 'half_year',
            'rent_amount' => 23000,
            'vat_applicable' => true,
            'vat_percent' => 15,
            'vat_amount' => 3000,
            'commission' => 0,
        ];
    }

    public function test_office_creates_a_contract_with_two_renters_from_the_form(): void
    {
        $unit = $this->makeUnit();
        [$a, $b] = [$this->makeClient(), $this->makeClient()];

        Livewire::test(CreateRentContract::class)
            ->fillForm($this->formData($unit->id, [
                ['client_id' => $a->id, 'share_percent' => 70],
                ['client_id' => $b->id, 'share_percent' => 30],
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $contract = RentContract::sole();
        $this->assertSame('RC-2026-00001', $contract->number);
        $this->assertSame(2, $contract->clients()->count());
        $this->assertSame(['23000.00', '23000.00'], $contract->installments()->orderBy('due_date')->pluck('amount')->all());
        $this->assertSame('3000.00', $contract->installments()->first()->vat_amount);
        $this->assertSame(UnitStatus::Rented, $unit->fresh()->status);

        Livewire::test(InstallmentsRelationManager::class, ['ownerRecord' => $contract, 'pageClass' => ViewRentContract::class])
            ->assertCanSeeTableRecords($contract->installments);
    }

    public function test_business_rule_errors_are_shown_instead_of_saving(): void
    {
        $unit = $this->makeUnit();
        $client = $this->makeClient();

        Livewire::test(CreateRentContract::class)
            ->fillForm($this->formData($unit->id, [['client_id' => $client->id, 'share_percent' => 80]]))
            ->call('create')
            ->assertNotified('مجموع نسب أطراف العقد يجب أن يساوي 100%');

        $this->assertSame(0, RentContract::count());
    }

    public function test_cancel_action_releases_the_unit(): void
    {
        $unit = $this->makeUnit();
        Livewire::test(CreateRentContract::class)
            ->fillForm($this->formData($unit->id, [['client_id' => $this->makeClient()->id, 'share_percent' => 100]]))
            ->call('create');
        $contract = RentContract::sole();

        Livewire::test(ListRentContracts::class)
            ->callAction(TestAction::make('cancel')->table($contract));

        $this->assertSame(ContractStatus::Cancelled, $contract->fresh()->status);
        $this->assertSame(UnitStatus::Available, $unit->fresh()->status);
    }
}
