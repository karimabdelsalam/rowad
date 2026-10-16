<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Installments\Pages\ListInstallments;
use App\Models\Client;
use App\Models\Installment;
use App\Models\Letter;
use App\Models\OutgoingMessage;
use App\Models\RentContract;
use App\Models\Task;
use App\Models\User;
use App\Services\Collection\CollectionEscalation;
use App\Services\Contracts\ContractService;
use App\Support\Settings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** The collection ladder for late rent: notice → second notice and task → formal warning letter. */
class CollectionEscalationTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private string $host;

    private RentContract $contract;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-03-01 09:00');
        $office = $this->initializeTenancy();
        $this->host = 'http://'.$office->domains()->value('domain');
        $renter = Client::create(['first_name' => 'سعد', 'family_name' => 'المطيري', 'mobile' => '0552220000']);
        $this->contract = app(ContractService::class)->createRent([
            'unit_id' => $this->makeUnit([[100, 100]], ['title' => 'شقة 7'])->id, 'calendar' => 'gregorian', 'contract_date' => '2026-01-01',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'period_unit' => 'year', 'period_count' => 1, 'payment_cycle' => 'month', 'rent_amount' => 2500,
        ], [$renter->id => 100]);
        $this->admin = User::create(['type' => 'staff', 'name' => 'مدير', 'email' => 'admin@test.local', 'password' => 'secret-password', 'is_super_admin' => true]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        Date::setTestNow();
        parent::tearDown();
    }

    private function enable(): void
    {
        Settings::set('collection_escalation_enabled', true, 'collection');
        Settings::flush();
    }

    private function runOn(string $date): array
    {
        Date::setTestNow($date.' 09:00');

        return app(CollectionEscalation::class)->run();
    }

    private function sent(string $event): int
    {
        return OutgoingMessage::where('event', $event)->count();
    }

    public function test_nothing_happens_until_the_office_turns_it_on(): void
    {
        $this->assertSame(['notices' => 0, 'tasks' => 0, 'warnings' => 0], $this->runOn('2026-02-25'));
        $this->assertSame(0, Installment::where('escalation_stage', '>', 0)->count());
    }

    public function test_late_rent_climbs_the_ladder_one_step_at_a_time(): void
    {
        $this->enable();
        $january = $this->contract->installments()->whereDate('due_date', '2026-01-01')->sole();
        $february = $this->contract->installments()->whereDate('due_date', '2026-02-01')->sole();

        // 1 March: January is 59 days late, February 28 — both start at the first notice
        $this->runOn('2026-03-01');
        $this->assertSame([1, 1], [$january->fresh()->escalation_stage, $february->fresh()->escalation_stage]);
        $this->assertSame(2, $this->sent('rent_overdue'));
        $this->assertStringContainsString('2,500.00', OutgoingMessage::where('event', 'rent_overdue')->value('body'));

        $this->runOn('2026-03-02');
        $this->assertSame(2, $january->fresh()->escalation_stage);
        $this->assertSame(2, Task::count(), 'a follow-up task per late instalment');
        $this->assertStringContainsString('0552220000', Task::first()->content);

        // the next day both reach the formal warning: one letter for the contract covering both
        $this->runOn('2026-03-03');
        $this->assertSame(1, Letter::count());
        $letter = Letter::sole();
        $this->assertSame($letter->id, $january->fresh()->warning_letter_id);
        $this->assertSame($letter->id, $february->fresh()->warning_letter_id);
        $this->assertStringContainsString('5,000.00', $letter->data['official']['content']);
        $this->assertSame(1, $this->sent('rent_warning'));
        $this->assertTrue($this->admin->notifications()->get()->contains(fn ($n) => str_contains($n->data['title'], 'إنذار رسمي')));

        // 4 March: only the March instalment (now 3 days late) gets its first notice; nothing repeats for the others
        $this->assertSame(['notices' => 1, 'tasks' => 0, 'warnings' => 0], $this->runOn('2026-03-04'));
        $this->assertSame(1, Letter::count());

        $this->actingAs($this->admin);
        $this->get("{$this->host}/print/letter/{$letter->id}")->assertOk()->assertSee('إنذار بسداد الأجرة المتأخرة')->assertSee('5,000.00');
    }

    public function test_respects_the_office_days_and_skips_paid_or_recent_instalments(): void
    {
        $this->enable();
        Settings::set('collection_stage1_days', 7, 'collection');
        Settings::flush();

        $this->runOn('2026-01-05'); // January is 4 days late: under 7
        $this->assertSame(0, $this->sent('rent_overdue'));

        $this->contract->installments()->whereDate('due_date', '2026-01-01')->update(['is_collected' => true]);
        $this->runOn('2026-01-20');
        $this->assertSame(0, $this->sent('rent_overdue'), 'paid instalments are left alone');
    }

    public function test_staff_can_issue_the_warning_from_the_instalments_screen(): void
    {
        Date::setTestNow('2026-02-10 09:00');
        $this->actingAs($this->admin);
        Filament::setCurrentPanel('admin');
        $january = $this->contract->installments()->whereDate('due_date', '2026-01-01')->sole();

        Livewire::test(ListInstallments::class)
            ->assertTableActionVisible('warn', $january)
            ->callTableAction('warn', $january)
            ->assertNotified();

        $this->assertSame(3, $january->fresh()->escalation_stage);
        $this->assertNotNull($january->fresh()->warning_letter_id);
        $this->assertSame($january->fresh()->warning_letter_id, $this->contract->installments()->whereDate('due_date', '2026-02-01')->value('warning_letter_id'));
        Livewire::test(ListInstallments::class)->assertTableActionHidden('warn', $january->fresh());
    }
}
