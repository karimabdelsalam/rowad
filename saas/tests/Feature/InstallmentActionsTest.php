<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Installments\Pages\ListInstallments;
use App\Models\Installment;
use App\Models\OwnerTransaction;
use App\Models\User;
use App\Services\Installments\InstallmentScheduler;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class InstallmentActionsTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeTenancy();
        Filament::setCurrentPanel('admin');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function actingAsRole(string $role): void
    {
        $user = User::create(['name' => $role, 'email' => "{$role}@office.test", 'password' => 'secret-password']);
        $user->assignRole($role);
        $this->actingAs($user);
    }

    public function test_accountant_collects_an_installment_from_the_table(): void
    {
        $this->actingAsRole('accountant');
        $installments = app(InstallmentScheduler::class)->schedule($this->makeRentContract());
        $first = $installments->first();

        Livewire::test(ListInstallments::class)
            ->assertCanSeeTableRecords($installments->take(5))
            ->callAction(TestAction::make('collect')->table($first))
            ->assertHasNoActionErrors();

        $this->assertTrue($first->fresh()->is_collected);
        $this->assertSame(1, OwnerTransaction::where('installment_id', $first->id)->count());
    }

    public function test_partial_payment_through_the_modal(): void
    {
        $this->actingAsRole('accountant');
        $first = app(InstallmentScheduler::class)->schedule($this->makeRentContract())->first();

        Livewire::test(ListInstallments::class)
            ->callAction(TestAction::make('collectPartial')->table($first), ['amount' => 250, 'close' => false, 'settle_owners' => false])
            ->assertHasNoActionErrors();

        $this->assertSame('250.00', $first->fresh()->paid_amount);
        $this->assertFalse($first->fresh()->is_collected);
    }

    public function test_viewer_does_not_see_collect_actions(): void
    {
        $this->actingAsRole('viewer');
        $first = app(InstallmentScheduler::class)->schedule($this->makeRentContract())->first();

        Livewire::test(ListInstallments::class)
            ->assertActionHidden(TestAction::make('collect')->table($first));
    }
}
