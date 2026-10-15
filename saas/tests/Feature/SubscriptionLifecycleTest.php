<?php

namespace Tests\Feature;

use App\Filament\Platform\Resources\Tenants\Pages\EditTenant;
use App\Filament\Platform\Resources\Tenants\Pages\ListTenants;
use App\Filament\Platform\Widgets\OfficesNeedingAttention;
use App\Filament\Platform\Widgets\PlatformStats;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Platform\OfficeProvisioner;
use App\Services\Platform\SubscriptionManager;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/** Legacy updatecore: an office pays for a period, gets reminded before it ends, a grace period after, then is suspended. */
class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $office;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-03-01 10:00:00');
        $this->seed(); // central plans
        $this->office = app(OfficeProvisioner::class)->provision(['name' => 'مكتب الأفق', 'subdomain' => 'ofuq', 'admin_name' => 'a',
            'admin_email' => 'a@ofuq.test', 'admin_password' => 'secret-pass', 'plan_id' => Plan::where('slug', 'basic')->value('id')]);
        $this->office->update(['email' => 'office@ofuq.test']);
        tenancy()->end();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        Tenant::all()->each->delete();
        Date::setTestNow();
        parent::tearDown();
    }

    private function platformAdmin(): User
    {
        return User::create(['name' => 'Platform', 'email' => 'root@rowad.test', 'password' => 'secret-password', 'is_super_admin' => true]);
    }

    public function test_recording_a_payment_activates_the_office_on_the_paid_plan(): void
    {
        $pro = Plan::where('slug', 'professional')->first();
        $this->office->forceFill(['upgrade_request' => ['plan_id' => $pro->id, 'plan' => $pro->name_ar, 'at' => now()->toIso8601String()]])->save();

        $this->actingAs($this->platformAdmin());
        Filament::setCurrentPanel('platform');
        Livewire::test(ListTenants::class)
            ->mountAction(TestAction::make('recordPayment')->table($this->office))
            ->assertSchemaStateSet(['plan_id' => $pro->id, 'billing_cycle' => 'monthly', 'amount' => (float) $pro->monthly_price])
            ->setActionData(['billing_cycle' => 'yearly'])
            ->assertSchemaStateSet(['amount' => (float) $pro->yearly_price])
            ->setActionData(['reference' => 'TRX-1001'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $office = $this->office->fresh();
        $this->assertSame(Tenant::STATUS_ACTIVE, $office->status);
        $this->assertSame($pro->id, $office->plan_id);
        $this->assertNull($office->upgrade_request);

        $subscription = $office->subscriptions()->where('status', 'active')->sole();
        $this->assertSame('2027-03-01', $subscription->ends_at->toDateString());
        $this->assertSame('expired', $office->subscriptions()->where('id', '!=', $subscription->id)->value('status')); // the trial
        $payment = SubscriptionPayment::where('subscription_id', $subscription->id)->sole();
        $this->assertSame('TRX-1001', $payment->gateway_reference);
        $this->assertEqualsWithDelta((float) $pro->yearly_price * 15 / 115, (float) $payment->vat_amount, 0.01);
    }

    public function test_paying_early_extends_from_the_end_of_the_current_period(): void
    {
        $basic = Plan::where('slug', 'basic')->first();
        $manager = app(SubscriptionManager::class);
        $manager->record($this->office, $basic, 'monthly', 199);
        Date::setTestNow('2026-03-20');
        $second = $manager->record($this->office->fresh(), $basic, 'monthly', 199);

        $this->assertSame('2026-04-01', $second->starts_at->toDateString());
        $this->assertSame('2026-05-01', $second->ends_at->toDateString());
    }

    public function test_active_subscription_ignores_newer_rows_of_other_statuses(): void
    {
        // Paid from before the trial row started (a backdated bank transfer): the expired trial starts later.
        $paid = app(SubscriptionManager::class)->record($this->office, Plan::where('slug', 'basic')->first(), 'yearly', 1990, now()->subMonth());

        $this->assertSame($paid->id, $this->office->fresh()->activeSubscription?->id);
    }

    public function test_unpaid_office_gets_reminders_then_grace_then_suspension(): void
    {
        Mail::fake();
        $manager = app(SubscriptionManager::class);
        $manager->record($this->office, Plan::where('slug', 'basic')->first(), 'monthly', 199); // until 2026-04-01

        Date::setTestNow('2026-03-25 03:00');
        $this->assertSame(1, $manager->checkExpirations()['reminders']);

        Date::setTestNow('2026-04-02 03:00');
        $done = $manager->checkExpirations();
        $this->assertSame(1, $done['past_due']);
        $this->assertSame(1, $done['reminders']);
        $this->assertSame(Tenant::STATUS_PAST_DUE, $this->office->fresh()->status);
        $this->assertTrue($this->office->fresh()->isAccessible()); // grace period

        Date::setTestNow('2026-04-09 03:00');
        $this->assertSame(1, $manager->checkExpirations()['suspended']);
        $this->assertSame(Tenant::STATUS_SUSPENDED, $this->office->fresh()->status);
        $this->assertFalse($this->office->fresh()->isAccessible());
        $this->get('http://ofuq.localhost/admin/login')->assertStatus(402);

        // Paying again brings it back.
        $renewed = app(SubscriptionManager::class)->record($this->office->fresh(), Plan::where('slug', 'basic')->first(), 'monthly', 199);
        $this->assertSame(Tenant::STATUS_ACTIVE, $this->office->fresh()->status);
        $this->assertSame('2026-05-09', $renewed->ends_at->toDateString());
    }

    public function test_trial_reminder_three_days_before_the_end(): void
    {
        Mail::fake();
        $this->office->update(['trial_ends_at' => now()->addDays(3)]);

        $this->assertSame(1, app(SubscriptionManager::class)->checkExpirations()['reminders']);
        $this->artisan('rowad:subscriptions')->assertSuccessful();
    }

    public function test_platform_dashboard_shows_offices_needing_follow_up(): void
    {
        $this->office->update(['trial_ends_at' => now()->addDays(2)]);
        $calm = app(OfficeProvisioner::class)->provision(['name' => 'مكتب هادئ', 'subdomain' => 'calm', 'admin_name' => 'a',
            'admin_email' => 'a@calm.test', 'admin_password' => 'secret-pass']);
        tenancy()->end();
        $calm->update(['trial_ends_at' => now()->addDays(30)]);

        $this->actingAs($this->platformAdmin());
        Filament::setCurrentPanel('platform');
        Livewire::test(OfficesNeedingAttention::class)->assertCanSeeTableRecords([$this->office])->assertCanNotSeeTableRecords([$calm]);
        Livewire::test(PlatformStats::class)->assertSee('في الفترة التجريبية');
        $this->get('http://localhost/platform')->assertOk()->assertSeeLivewire(OfficesNeedingAttention::class);
    }

    public function test_deleting_an_office_requires_typing_its_name(): void
    {
        $this->actingAs($this->platformAdmin());
        Filament::setCurrentPanel('platform');

        Livewire::test(EditTenant::class, ['record' => $this->office->getRouteKey()])
            ->callAction('delete', ['confirm_name' => 'اسم خطأ'])
            ->assertHasActionErrors(['confirm_name']);
        $this->assertNotNull(Tenant::find($this->office->id));
    }
}
