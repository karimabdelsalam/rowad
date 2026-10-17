<?php

namespace Tests\Feature;

use App\Filament\Platform\Pages\PlatformSettingsPage;
use App\Models\Setting;
use App\Models\User;
use App\Support\PlatformMail;
use App\Support\PlatformSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The platform console sets the mail server, the main sender and everything the official site shows. */
class PlatformMailAndSiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        PlatformSettings::flush();
        $this->actingAs(User::create(['name' => 'Platform', 'email' => 'root@rowad.test', 'password' => 'secret-password', 'is_super_admin' => true]));
        Filament::setCurrentPanel('platform');
    }

    protected function tearDown(): void
    {
        PlatformSettings::flush();
        parent::tearDown();
    }

    public function test_the_mail_server_and_sender_replace_the_env_values(): void
    {
        Livewire::test(PlatformSettingsPage::class)
            ->fillForm([
                'mail_mailer' => 'smtp', 'mail_host' => 'smtp.aqarnova.test', 'mail_port' => 465, 'mail_encryption' => 'ssl',
                'mail_username' => 'no-reply@aqarnova.com', 'mail_password' => 'mail-secret-9',
                'mail_from_address' => 'no-reply@aqarnova.com', 'mail_from_name' => 'عقار نوفا', 'mail_reply_to' => 'support@aqarnova.com',
                'signup_alert_emails' => ['team@aqarnova.com'],
            ])
            ->call('save')->assertHasNoFormErrors();

        $this->assertStringNotContainsString('mail-secret-9', (string) Setting::where('key', 'mail_password')->value('value'), 'encrypted at rest');
        $this->assertSame('mail-secret-9', PlatformSettings::secret('mail_password'));

        config(['mail.default' => 'log', 'mail.from.address' => 'hello@example.com']);
        PlatformMail::apply();
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.aqarnova.test', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('mail-secret-9', config('mail.mailers.smtp.password'));
        $this->assertSame('no-reply@aqarnova.com', config('mail.from.address'));
        $this->assertSame('عقار نوفا', config('mail.from.name'));
        $this->assertSame('support@aqarnova.com', config('mail.reply_to.address'));
        $this->assertSame(['team@aqarnova.com'], PlatformSettings::signupAlertEmails());

        Livewire::test(PlatformSettingsPage::class)->assertSet('data.mail_password', null)->assertDontSee('mail-secret-9')
            ->fillForm(['mail_from_name' => 'Aqar Nova'])->call('save');
        $this->assertSame('mail-secret-9', PlatformSettings::secret('mail_password'), 'an empty field keeps the saved password');
    }

    public function test_a_test_message_goes_out_through_the_saved_settings(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        Livewire::test(PlatformSettingsPage::class)
            ->fillForm(['mail_mailer' => 'log', 'mail_from_address' => 'no-reply@aqarnova.com'])
            ->call('testMail', 'karim@aqarnova.test')
            ->assertNotified(__('أُرسلت رسالة التجربة إلى :to', ['to' => 'karim@aqarnova.test']));
        $this->assertSame('log', config('mail.default'));
        $this->assertSame('no-reply@aqarnova.com', config('mail.from.address'));
    }

    public function test_nothing_saved_leaves_env_in_charge(): void
    {
        config(['mail.default' => 'log', 'mail.from.address' => 'hello@example.com']);
        PlatformMail::apply();
        $this->assertSame('log', config('mail.default'));
        $this->assertSame('hello@example.com', config('mail.from.address'));
        config(['rowad.contact.email' => 'info@aqarnova.test']);
        $this->assertSame(['info@aqarnova.test'], PlatformSettings::signupAlertEmails(), 'no list: the contact email');
    }

    public function test_the_official_site_shows_the_texts_seo_social_tracking_and_widget_set_in_the_console(): void
    {
        Livewire::test(PlatformSettingsPage::class)
            ->fillForm([
                'site_hero_title_ar' => 'نظامك العقاري', 'site_hero_highlight_ar' => 'في جيبك', 'site_hero_lead_ar' => 'كل شيء في مكان واحد.',
                'site_footer_about_ar' => 'نبذة جديدة عن المنصة.',
                'site_seo_title_ar' => 'عقار نوفا | إدارة الأملاك', 'site_seo_description_ar' => 'وصف جديد لمحركات البحث.', 'site_seo_keywords' => 'عقار، إيجار',
                'social_x' => 'https://x.com/aqarnova', 'social_instagram' => 'https://instagram.com/aqarnova',
                'analytics_ga4' => 'G-ABC123', 'analytics_meta_pixel' => '123456789012345',
                'site_head_code' => '<meta name="google-site-verification" content="verify-me">',
                'widget_enabled' => true, 'widget_auto_open' => false, 'widget_id' => 'w-777', 'widget_tenant' => '22',
            ])
            ->call('save')->assertHasNoFormErrors();

        $this->get('http://localhost/')->assertOk()
            ->assertSee('نظامك العقاري')->assertSee('في جيبك')->assertSee('كل شيء في مكان واحد.')->assertSee('نبذة جديدة عن المنصة.')
            ->assertSee('<title>عقار نوفا | إدارة الأملاك</title>', false)->assertSee('content="وصف جديد لمحركات البحث."', false)
            ->assertSee('<meta name="keywords" content="عقار، إيجار">', false)
            ->assertSee('href="https://x.com/aqarnova"', false)->assertSee('href="https://instagram.com/aqarnova"', false)
            ->assertSee('gtag/js?id=G-ABC123', false)->assertSee("fbq('init'", false)
            ->assertSee('<meta name="google-site-verification" content="verify-me">', false)
            ->assertSee('data-planova-tenant="22" data-planova-widget="w-777"', false)->assertDontSee('PlanovaWidget?.open()', false)
            ->assertDontSee('أدر عقاراتك وعقودك وتحصيلك');

        PlatformSettings::save(['widget_enabled' => false]);
        $this->get('http://localhost/')->assertDontSee('data-planova-widget', false);
    }

    public function test_invalid_tracking_ids_are_refused(): void
    {
        Livewire::test(PlatformSettingsPage::class)
            ->fillForm(['analytics_ga4' => '<script>', 'analytics_meta_pixel' => 'abc'])
            ->call('save')->assertHasFormErrors(['analytics_ga4', 'analytics_meta_pixel']);
    }
}
