<?php

namespace Tests\Feature;

use App\Filament\Platform\Resources\ContactMessages\Pages\ListContactMessages;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\User;
use App\Support\PlatformSettings;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/** "تواصل معنا" at the foot of the official site: stored for the platform console and mailed to the team. */
class PlatformContactFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        PlatformSettings::flush();
    }

    private function form(array $overrides = []): array
    {
        return array_merge(['name' => 'فهد الدوسري', 'company' => 'مكتب الدوسري العقاري', 'phone' => '0551234567', 'email' => 'fahad@office.test',
            'city' => 'الرياض', 'topic' => 'demo', 'message' => 'لدينا 120 وحدة ونريد عرضًا للنظام.'], $overrides);
    }

    public function test_the_home_page_has_the_contact_form(): void
    {
        $this->get('http://localhost/')->assertOk()
            ->assertSee('id="contact"', false)->assertSee('action="/contact"', false)
            ->assertSee(__('نسعد بالإجابة على أسئلتك'))->assertSee(__('أريد عرضًا تجريبيًا'))->assertSee('name="website"', false);
    }

    public function test_a_message_is_kept_and_mailed_to_the_team(): void
    {
        Mail::fake();
        PlatformSettings::save(['signup_alert_emails' => ['team@aqarnova.test']]);

        $this->from('http://localhost/')->post('http://localhost/contact', $this->form())
            ->assertRedirect('/#contact')->assertSessionHas('contact_sent');

        $message = ContactMessage::sole();
        $this->assertSame(['فهد الدوسري', 'demo', 'new'], [$message->name, $message->topic, $message->status]);
        Mail::assertQueued(ContactMessageReceived::class, fn ($mail) => $mail->hasTo('team@aqarnova.test') && $mail->hasReplyTo('fahad@office.test'));

        $this->get('http://localhost/')->assertSee(__('وصلتنا رسالتك، شكرًا لك'));      // once, after sending
        $this->get('http://localhost/')->assertDontSee(__('وصلتنا رسالتك، شكرًا لك'));
    }

    public function test_the_form_explains_what_is_missing_and_bots_are_ignored(): void
    {
        Mail::fake();
        $this->from('http://localhost/')->post('http://localhost/contact', $this->form(['name' => '', 'phone' => '', 'email' => '', 'message' => '']))
            ->assertSessionHasErrors(['name', 'phone', 'email', 'message']);

        $this->from('http://localhost/')->post('http://localhost/contact', $this->form(['website' => 'http://spam.example']))
            ->assertRedirect('/#contact')->assertSessionHas('contact_sent');
        $this->assertSame(0, ContactMessage::count(), 'the hidden field marks a bot');
        Mail::assertNothingQueued();
    }

    public function test_the_platform_console_lists_and_follows_up_messages(): void
    {
        $message = ContactMessage::create($this->form() + ['locale' => 'ar']);
        $this->actingAs(User::create(['name' => 'Platform', 'email' => 'root@rowad.test', 'password' => 'secret-password', 'is_super_admin' => true]));
        Filament::setCurrentPanel('platform');

        $this->assertSame('1', \App\Filament\Platform\Resources\ContactMessages\ContactMessageResource::getNavigationBadge());
        Livewire::test(ListContactMessages::class)->assertCanSeeTableRecords([$message])
            ->callAction(TestAction::make('handle')->table($message), ['notes' => 'تم الاتصال وحُدد موعد عرض.']);

        $message->refresh();
        $this->assertSame(['handled', 'Platform', 'تم الاتصال وحُدد موعد عرض.'], [$message->status, $message->handled_by, $message->notes]);
        $this->assertNull(\App\Filament\Platform\Resources\ContactMessages\ContactMessageResource::getNavigationBadge());
    }
}
