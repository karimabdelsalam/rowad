<?php

namespace Tests\Feature;

use App\Enums\ViewingStatus;
use App\Filament\Admin\Resources\Viewings\Pages\ManageViewings;
use App\Models\Client;
use App\Models\OutgoingMessage;
use App\Models\Unit;
use App\Models\User;
use App\Models\Viewing;
use App\Services\Viewings\ViewingSchedule;
use App\Support\Settings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** Viewing appointments: slots from the office's hours, booking from the website, reminders and outcomes. */
class ViewingsTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private string $host;

    private Unit $unit;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-11-07 10:00:00'); // Saturday, 13:00 in Riyadh
        $office = $this->initializeTenancy();
        $this->host = 'http://'.$office->domains()->value('domain');
        $this->unit = $this->makeUnit([[100, 100]], ['title' => 'شقة 101', 'is_published' => true, 'rent_amount' => 30000,
            'latitude' => 24.7, 'longitude' => 46.68]);
        $this->admin = User::create(['type' => 'staff', 'name' => 'مدير', 'email' => 'admin@test.local', 'password' => 'secret-password', 'is_super_admin' => true]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_slots_follow_the_office_hours_and_skip_closed_days(): void
    {
        $slots = app(ViewingSchedule::class)->slots($this->unit, 7);

        $this->assertSame(['16:00', '16:30', '17:00', '17:30', '18:00', '18:30', '19:00', '19:30', '20:00', '20:30'], $slots['2026-11-07']);
        $this->assertArrayNotHasKey('2026-11-13', $slots, 'Friday is closed by default');
        $this->assertCount(6, $slots, 'Saturday to Thursday');

        Settings::set('viewing_from', '09:00:00', 'viewings');
        Settings::set('viewing_to', '11:00:00', 'viewings');
        Settings::set('viewing_slot_minutes', 60, 'viewings');
        Settings::set('viewing_days', ['0'], 'viewings');
        Settings::flush();
        $slots = app(ViewingSchedule::class)->slots($this->unit, 7);
        $this->assertSame(['2026-11-08' => ['09:00', '10:00']], $slots, 'Sunday only, hourly, today 09:00 already passed');
    }

    public function test_a_visitor_books_from_the_unit_page_and_gets_the_details(): void
    {
        $page = $this->get("{$this->host}/units/{$this->unit->id}")->assertOk();
        $page->assertSee(__('احجز موعد معاينة'))->assertSee('16:30');

        $this->post("{$this->host}/units/{$this->unit->id}/viewing", ['slot' => '2026-11-08 17:00', 'name' => 'أحمد السالم', 'mobile' => '0559876543'])
            ->assertRedirect()->assertSessionHas('viewing_booked', '2026-11-08 17:00');

        $viewing = Viewing::sole();
        $this->assertSame(ViewingStatus::Booked, $viewing->status);
        $this->assertSame('2026-11-08 17:00', $viewing->starts_at->format('Y-m-d H:i'));
        $sms = OutgoingMessage::where('event', 'viewing_booked')->where('channel', 'sms')->sole();
        $this->assertStringContainsString('17:00', $sms->body);
        $this->assertStringContainsString('google.com/maps', $sms->body);
        $this->assertSame(1, $this->admin->notifications()->count());

        $this->assertNotContains('17:00', app(ViewingSchedule::class)->slots($this->unit)['2026-11-08'], 'the slot is taken');
        $this->post("{$this->host}/units/{$this->unit->id}/viewing", ['slot' => '2026-11-08 17:00', 'name' => 'زائر آخر', 'mobile' => '0551111111'])
            ->assertSessionHasErrors('slot');
        $this->post("{$this->host}/units/{$this->unit->id}/viewing", ['slot' => '2026-11-13 17:00', 'name' => 'زائر', 'mobile' => '0551111111'])
            ->assertSessionHasErrors('slot'); // Friday
        $this->assertSame(1, Viewing::count());
    }

    public function test_offices_can_switch_booking_off(): void
    {
        Settings::set('viewing_enabled', false, 'viewings');
        Settings::flush();

        $this->get("{$this->host}/units/{$this->unit->id}")->assertOk()->assertDontSee(__('احجز موعد معاينة'));
        $this->post("{$this->host}/units/{$this->unit->id}/viewing", ['slot' => '2026-11-08 17:00', 'name' => 'أ', 'mobile' => '0559876543'])->assertNotFound();
    }

    public function test_same_day_reminders_go_out_once(): void
    {
        app(ViewingSchedule::class)->book($this->unit, ['starts_at' => '2026-11-07 18:00', 'name' => 'أحمد', 'mobile' => '0559876543']);
        app(ViewingSchedule::class)->book($this->unit, ['starts_at' => '2026-11-08 18:00', 'name' => 'سارة', 'mobile' => '0559876544']);

        $this->assertSame(1, app(ViewingSchedule::class)->sendReminders());
        $this->assertSame(0, app(ViewingSchedule::class)->sendReminders());
        $this->assertSame('0559876543', OutgoingMessage::where('event', 'viewing_reminder')->where('channel', 'sms')->value('recipient'));
    }

    public function test_staff_record_the_outcome_cancel_and_turn_visitors_into_clients(): void
    {
        $today = app(ViewingSchedule::class)->book($this->unit, ['starts_at' => '2026-11-07 18:00', 'name' => 'أحمد السالم', 'mobile' => '0559876543']);
        $later = app(ViewingSchedule::class)->book($this->unit, ['starts_at' => '2026-11-10 17:00', 'name' => 'سارة', 'mobile' => '0559876544']);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(ManageViewings::class)
            ->assertCanSeeTableRecords([$today])->assertCanNotSeeTableRecords([$later]) // "today" tab
            ->callTableAction('mark_done', $today)
            ->callTableAction('toClient', $today);
        $this->assertSame(ViewingStatus::Done, $today->fresh()->status);
        $client = Client::where('mobile', '0559876543')->sole();
        $this->assertSame('أحمد', $client->first_name);
        $this->assertSame($client->id, $today->fresh()->client_id);

        Livewire::test(ManageViewings::class, ['activeTab' => 'upcoming'])
            ->assertCanSeeTableRecords([$later])
            ->assertTableActionHidden('mark_done', $later) // not before its day
            ->callTableAction('cancel', $later, ['reason' => 'تم تأجير الوحدة']);
        $this->assertSame(ViewingStatus::Cancelled, $later->fresh()->status);
        $this->assertStringContainsString('تم تأجير الوحدة', OutgoingMessage::where('event', 'viewing_cancelled')->where('channel', 'sms')->value('body'));

        Livewire::test(ManageViewings::class)
            ->callAction('create', ['unit_id' => $this->unit->id, 'starts_at' => '2026-11-09 19:00', 'name' => 'خالد', 'mobile' => '0550000000'])
            ->assertHasNoActionErrors();
        $this->assertSame('staff', Viewing::latest('id')->value('source'));
    }
}
