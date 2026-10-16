<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\WebsiteSettings;
use App\Models\InboxMessage;
use App\Models\Plan;
use App\Models\Unit;
use App\Models\User;
use App\Support\Settings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = $this->initializeTenancy(['name' => 'مكتب النخبة العقاري']);
        $this->host = 'http://'.$tenant->domains()->value('domain');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function unit(string $title, array $attributes = []): Unit
    {
        return $this->makeUnit([[100, 100]], $attributes + ['title' => $title, 'is_published' => true, 'rent_amount' => 30000]);
    }

    public function test_home_lists_published_available_units_for_rent_and_sale(): void
    {
        $this->unit('شقة الياسمين المتاحة');
        $this->unit('شقة غير منشورة', ['is_published' => false]);
        $this->unit('شقة مؤجرة', ['status' => 'rented']);
        $this->unit('فيلا للبيع في الملقا', ['offer_type' => 'sale', 'sale_price' => 2350000]);
        $this->unit('أرض مباعة', ['offer_type' => 'sale', 'status' => 'sold']);
        $this->unit('وحدة موقوفة', ['is_active' => false]);

        $this->get($this->host.'/')
            ->assertOk()
            ->assertSee('مكتب النخبة العقاري')
            ->assertSee('شقة الياسمين المتاحة')
            ->assertSee('30,000')
            ->assertSee('فيلا للبيع في الملقا')
            ->assertSee('2,350,000')
            ->assertDontSee('شقة غير منشورة')
            ->assertDontSee('شقة مؤجرة')
            ->assertDontSee('أرض مباعة')
            ->assertDontSee('وحدة موقوفة');

        $this->get($this->host.'/?offer=sale')->assertOk()->assertSee('فيلا للبيع في الملقا')->assertDontSee('شقة الياسمين المتاحة');
        $this->get($this->host.'/?offer=rent')->assertOk()->assertSee('شقة الياسمين المتاحة')->assertDontSee('فيلا للبيع في الملقا');
        $this->get($this->host.'/?max_price=100000')->assertOk()->assertSee('شقة الياسمين المتاحة')->assertDontSee('فيلا للبيع في الملقا');
    }

    public function test_sale_unit_page_shows_the_asking_price_and_hidden_prices_stay_hidden(): void
    {
        $villa = $this->unit('فيلا للبيع في الملقا', ['offer_type' => 'sale', 'sale_price' => 2350000, 'rent_amount' => null]);
        $hidden = $this->unit('شقة بسعر خاص', ['show_price' => false, 'rent_amount' => 77777]);

        $this->get($this->host."/units/{$villa->id}")->assertOk()->assertSee(__('سعر البيع'))->assertSee('2,350,000');
        $this->get($this->host."/units/{$hidden->id}")->assertOk()->assertSee(__('السعر عند التواصل'))->assertDontSee('77,777');
        $this->get($this->host.'/?sort=price_desc')->assertOk()->assertSeeInOrder(['فيلا للبيع في الملقا', 'شقة بسعر خاص']);
    }

    public function test_another_office_never_sees_these_units(): void
    {
        $this->unit('وحدة المكتب الأول');
        $other = $this->createTenant(['name' => 'مكتب آخر']);
        $otherHost = 'http://'.$other->domains()->value('domain');

        tenancy()->end();
        $this->get($otherHost.'/')->assertOk()->assertSee('مكتب آخر')->assertDontSee('وحدة المكتب الأول');
        $other->delete();
        tenancy()->initialize($this->tenant);
    }

    public function test_unit_page_and_filters(): void
    {
        $listed = $this->unit('فيلا حي الملقا', ['rooms' => 6, 'details' => 'فيلا واسعة مع حديقة']);
        $hidden = $this->unit('مخفية', ['is_published' => false]);
        $this->unit('شقة مشابهة'); // renders the "similar units" cards too

        $this->get($this->host."/units/{$listed->id}")->assertOk()->assertSee('فيلا واسعة مع حديقة')->assertSee('شقة مشابهة');
        $this->get($this->host."/units/{$hidden->id}")->assertNotFound();
        $this->get($this->host.'/?rooms=7')->assertOk()->assertDontSee('فيلا حي الملقا');
        $this->get($this->host.'/?rooms=5')->assertOk()->assertSee('فيلا حي الملقا');
    }

    public function test_unit_inquiry_reaches_the_office_inbox_and_notifies_staff(): void
    {
        $admin = User::create(['name' => 'مدير', 'email' => 'admin@office.test', 'password' => 'secret-password']);
        $admin->assignRole('admin');
        $unit = $this->unit('شقة العليا');

        $this->from($this->host."/units/{$unit->id}")
            ->post($this->host."/units/{$unit->id}/inquiry", ['name' => 'خالد', 'mobile' => '0555555555', 'message' => 'أرغب في المعاينة غدًا'])
            ->assertRedirect()
            ->assertSessionHas('inquiry_sent');

        $message = InboxMessage::sole();
        $this->assertSame(['unit_inquiry', $unit->id, 'خالد'], [$message->source, $message->unit_id, $message->name]);
        $this->assertSame(1, $admin->notifications()->count());
    }

    public function test_inquiry_validation_honeypot_and_rate_limit(): void
    {
        $this->post($this->host.'/contact', ['name' => 'x', 'message' => 'y'])->assertSessionHasErrors('mobile');
        $this->post($this->host.'/contact', ['name' => 'bot', 'mobile' => '0500000000', 'message' => 'spam', 'website' => 'http://spam'])
            ->assertSessionHasErrors('website');

        foreach (range(1, 5) as $i) {
            $this->post($this->host.'/contact', ['name' => "زائر {$i}", 'mobile' => '0500000000', 'message' => 'مرحبا']);
        }
        $this->post($this->host.'/contact', ['name' => 'زائر 6', 'mobile' => '0500000000', 'message' => 'مرحبا'])->assertSessionHasErrors('message');
        $this->assertSame(5, InboxMessage::count());
    }

    public function test_english_version(): void
    {
        $this->unit('شقة البحر', ['title_en' => 'Sea View Apartment']);
        Settings::set('landing_hero_title_en', 'Homes for rent');

        $this->get($this->host.'/?lang=en')
            ->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertSee('Sea View Apartment')
            ->assertSee('Homes for rent');

        $this->get($this->host.'/')->assertSee('dir="ltr"', false); // remembered in session
        $this->get($this->host.'/?lang=ar')->assertSee('dir="rtl"', false)->assertSee('شقة البحر');
    }

    public function test_site_is_off_when_disabled_or_not_in_plan(): void
    {
        Settings::set('landing_enabled', false);
        $this->get($this->host.'/')->assertRedirect('/admin');
        Settings::set('site_closed_message', 'الموقع مغلق مؤقتًا للتحديث');
        $this->get($this->host.'/')->assertOk()->assertSee('الموقع مغلق مؤقتًا للتحديث');
        Settings::set('site_closed_message', null);

        Settings::set('landing_enabled', true);
        $plan = Plan::create(['slug' => 'mini', 'name_ar' => 'مصغرة', 'name_en' => 'Mini', 'features' => ['owner_portal']]);
        $this->tenant->plan()->associate($plan)->save();
        $this->get($this->host.'/')->assertRedirect('/admin');
    }

    public function test_unit_photos_are_public_only_while_listed(): void
    {
        $unit = $this->unit('شقة بالصور');
        $media = $unit->addMedia(UploadedFile::fake()->image('front.jpg', 1600, 1200))->toMediaCollection('photos');

        $this->get($this->host."/media/{$media->id}")->assertOk();
        $this->assertTrue($media->fresh()->hasGeneratedConversion('thumb'));
        $thumb = $this->get($this->host."/media/{$media->id}?size=thumb")->assertOk()->baseResponse->getFile()->getPathname();
        $this->assertSame([640, 480], array_slice(getimagesize($thumb), 0, 2));
        $this->assertStringContainsString('size=thumb', $unit->coverPhotoUrl());

        $unit->update(['is_published' => false]);
        $this->get($this->host."/media/{$media->id}")->assertNotFound();
    }

    public function test_office_admin_edits_website_content(): void
    {
        Filament::setCurrentPanel('admin');
        $admin = User::create(['name' => 'مدير', 'email' => 'admin@office.test', 'password' => 'secret-password']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Livewire::test(WebsiteSettings::class)
            ->fillForm(['landing_enabled' => true, 'landing_hero_title' => 'أفضل الوحدات في الرياض', 'landing_whatsapp' => '966500000000'])
            ->call('save')
            ->assertNotified();

        auth()->logout();
        $this->get($this->host.'/')->assertSee('أفضل الوحدات في الرياض')->assertSee('wa.me/966500000000', false);
    }
}
