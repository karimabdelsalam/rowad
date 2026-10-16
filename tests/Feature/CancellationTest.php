<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Subscription;
use App\Mail\OfficeCancelled;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Platform\OfficeProvisioner;
use App\Services\Platform\SubscriptionManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/** An office cancels itself, keeps its data for 30 days, comes back from the emailed link, or is purged. */
class CancellationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $office;

    private string $host = 'http://ofuq.localhost';

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-03-01 10:00:00');
        $this->seed();
        $this->office = app(OfficeProvisioner::class)->provision(['name' => 'مكتب الأفق', 'subdomain' => 'ofuq', 'admin_name' => 'أحمد',
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

    private function actAsOfficeAdmin(): void
    {
        tenancy()->initialize($this->office);
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::where('email', 'a@ofuq.test')->firstOrFail());
    }

    public function test_admin_cancels_from_the_subscription_page_and_the_office_closes_with_a_retention_window(): void
    {
        Mail::fake();
        $this->actAsOfficeAdmin();

        Livewire::test(Subscription::class)
            ->callAction('cancel', ['reason' => 'price', 'note' => 'غالي', 'confirm' => 'اسم خاطئ'])
            ->assertHasActionErrors(['confirm']);
        Livewire::test(Subscription::class)
            ->callAction('cancel', ['reason' => 'price', 'note' => 'غالي', 'confirm' => 'مكتب الأفق'])
            ->assertHasNoActionErrors();

        $office = $this->office->fresh();
        $this->assertSame(Tenant::STATUS_CANCELLED, $office->status);
        $this->assertSame('2026-03-31', $office->purge_after->toDateString());
        $this->assertSame('price', $office->cancellation['reason']);
        $this->assertSame('trial', $office->cancellation['previous_status']);
        $this->assertSame('cancelled', $office->subscriptions()->value('status'));
        $this->assertFalse($office->isAccessible());
        $this->assertTrue($office->canBeReactivated());

        Mail::assertSent(OfficeCancelled::class, fn ($mail) => $mail->hasTo('office@ofuq.test') && $mail->hasTo('a@ofuq.test')
            && $mail->stage === 'confirmed' && str_contains($mail->render(), '/reactivate?') && str_contains($mail->render(), '2026-03-31'));

        tenancy()->end();
        auth()->logout();
        $this->get("{$this->host}/admin/login")->assertStatus(402)->assertSee('تم إلغاء الاشتراك')->assertSee('2026-03-31')->assertSee('إرسال رابط إعادة التفعيل');
        $this->get("{$this->host}/")->assertStatus(402);
    }

    public function test_the_emailed_link_brings_the_office_back_as_it_was(): void
    {
        Mail::fake();
        app(SubscriptionManager::class)->cancel($this->office, 'other', null, 'أحمد', 'a@ofuq.test');
        $url = SubscriptionManager::reactivationUrl($this->office->fresh());
        $this->assertStringStartsWith('http://ofuq.localhost/reactivate?', $url);

        // tampering with the signature or the office id fails
        $this->get(str_replace('signature=', 'signature=x', $url))->assertStatus(403);

        $this->get($url)->assertOk()->assertSee('إعادة تفعيل المؤسسة')->assertSee('مكتب الأفق');
        $this->post($url)->assertRedirect('/admin/login');

        $office = $this->office->fresh();
        $this->assertSame(Tenant::STATUS_TRIAL, $office->status); // the trial was still running
        $this->assertNull($office->purge_after);
        $this->assertNull($office->cancellation);
        $this->get("{$this->host}/admin/login")->assertOk();

        // a paid office comes back active with its period intact
        app(SubscriptionManager::class)->record($office, Plan::where('slug', 'basic')->first(), 'monthly', 199); // until 2026-04-01
        app(SubscriptionManager::class)->cancel($office->fresh(), 'other', null, null, null);
        $this->assertSame('cancelled', $office->subscriptions()->where('amount', 199)->value('status'));
        app(SubscriptionManager::class)->reactivate($office->fresh());
        $this->assertSame(Tenant::STATUS_ACTIVE, $office->fresh()->status);
        $this->assertSame('active', $office->subscriptions()->where('amount', 199)->value('status'));

        // after the window the link is dead
        app(SubscriptionManager::class)->cancel($office->fresh(), 'other', null, null, null);
        Date::setTestNow('2026-05-05');
        $this->assertFalse($office->fresh()->canBeReactivated());
        $this->expectException(\App\Exceptions\BusinessRuleException::class);
        app(SubscriptionManager::class)->reactivate($office->fresh());
    }

    public function test_daily_check_reminds_then_purges_the_office(): void
    {
        Mail::fake();
        $manager = app(SubscriptionManager::class);
        $manager->cancel($this->office, 'closing', null, null, null); // purge after 2026-03-31
        $id = $this->office->id;

        Date::setTestNow('2026-03-28 03:00');
        $done = $manager->checkExpirations();
        $this->assertSame(1, $done['purge_reminders']);
        $this->assertSame(0, $done['purged']);
        Mail::assertSent(OfficeCancelled::class, fn ($mail) => $mail->stage === 'reminder' && $mail->hasTo('office@ofuq.test'));

        Date::setTestNow('2026-04-01 03:00');
        $this->assertSame(1, $manager->checkExpirations()['purged']);
        $this->assertNull(Tenant::find($id));
        $this->assertSame(0, \Stancl\Tenancy\Database\Models\Domain::where('tenant_id', $id)->count());
        $this->get("{$this->host}/admin/login")->assertStatus(404);
    }

    public function test_platform_activation_clears_the_cancellation(): void
    {
        Mail::fake();
        app(SubscriptionManager::class)->cancel($this->office, 'other', null, null, null);
        app(OfficeProvisioner::class)->activate($this->office->fresh());
        $office = $this->office->fresh();
        $this->assertSame(Tenant::STATUS_ACTIVE, $office->status);
        $this->assertNull($office->purge_after);
        $this->assertNull($office->cancellation);
    }
}
