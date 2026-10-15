<?php

namespace Tests\Feature;

use App\Enums\InstallmentType;
use App\Enums\NotificationEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** Arabic is the primary language; every UI string must also exist in English. */
class BilingualTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    public function test_every_translated_arabic_string_has_an_english_version(): void
    {
        $english = json_decode(file_get_contents(lang_path('en.json')), true);
        $missing = [];

        foreach ([app_path(), resource_path('views')] as $dir) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
                if (! str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }
                preg_match_all("/__\\(\\s*'((?:\\\\'|[^'])*)'/u", file_get_contents($file->getPathname()), $matches);
                foreach ($matches[1] as $string) {
                    $string = str_replace("\\'", "'", $string);
                    if (preg_match('/\p{Arabic}/u', $string) && ! array_key_exists($string, $english)) {
                        $missing[] = $string.'  ('.basename($file->getPathname()).')';
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)), 'Add these to lang/en.json');
    }

    public function test_enum_labels_switch_language(): void
    {
        app()->setLocale('ar');
        $this->assertSame('إيجار سنوي - دفعة شهرية', InstallmentType::YearRentMonthly->getLabel());

        app()->setLocale('en');
        $this->assertSame('Yearly rent - monthly payment', InstallmentType::YearRentMonthly->getLabel());
        foreach (NotificationEvent::cases() as $event) {
            $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $event->getLabel(), $event->name);
        }
    }

    public function test_admin_panel_renders_in_the_users_language(): void
    {
        $tenant = $this->initializeTenancy();
        $host = 'http://'.$tenant->domains()->value('domain');
        $user = User::create(['name' => 'Admin', 'email' => 'admin@office.test', 'password' => 'secret-password', 'locale' => 'en']);
        $user->assignRole('admin');

        $this->actingAs($user)->get("{$host}/admin/units")
            ->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertSee('Properties')
            ->assertSee('Units')
            ->assertDontSee('العقارات');

        $this->get("{$host}/locale/ar")->assertRedirect();
        $this->assertSame('ar', $user->fresh()->locale);
        // Navigation is memoised per app instance, which the test runner reuses across requests;
        // the page title is computed per request.
        $this->get("{$host}/admin/units")->assertSee('dir="rtl"', false)->assertSee('<title>', false)->assertSee('الوحدات');
    }
}
