<?php

namespace Tests\Feature;

use App\Filament\Platform\Pages\PlatformSettingsPage;
use App\Models\Setting;
use App\Models\User;
use App\Services\Ai\ClaudeClient;
use App\Support\PlatformSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** The AI key and model are set from the platform console (key encrypted, never sent back), .env as fallback. */
class PlatformAiSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['rowad.ai.api_key' => null, 'rowad.ai.model' => null]);
        PlatformSettings::flush();
    }

    protected function tearDown(): void
    {
        PlatformSettings::flush();
        parent::tearDown();
    }

    private function platformAdmin(): void
    {
        $this->actingAs(User::create(['name' => 'Platform', 'email' => 'root@rowad.test', 'password' => 'secret-password', 'is_super_admin' => true]));
        Filament::setCurrentPanel('platform');
    }

    public function test_the_key_is_saved_encrypted_used_for_requests_and_never_shown_again(): void
    {
        $this->platformAdmin();
        $this->assertFalse(ClaudeClient::configured());

        Livewire::test(PlatformSettingsPage::class)
            ->fillForm(['ai_api_key' => 'sk-ant-secret-123', 'ai_model' => 'claude-sonnet-5-5'])
            ->call('save')->assertHasNoFormErrors();

        $stored = (string) Setting::where('key', 'ai_api_key')->value('value');
        $this->assertNotSame('', $stored);
        $this->assertStringNotContainsString('sk-ant-secret-123', $stored, 'encrypted at rest');
        $this->assertSame('sk-ant-secret-123', PlatformSettings::aiApiKey());
        $this->assertSame('console', PlatformSettings::aiKeySource());
        $this->assertTrue(ClaudeClient::configured());

        Livewire::test(PlatformSettingsPage::class)
            ->assertSet('data.ai_api_key', null)->assertDontSee('sk-ant-secret-123')
            ->assertSet('data.ai_model', 'claude-sonnet-5-5')
            ->fillForm(['ai_model' => 'claude-haiku-5-5'])->call('save'); // empty key field keeps the saved key
        PlatformSettings::flush();
        $this->assertSame('sk-ant-secret-123', PlatformSettings::aiApiKey());

        Http::fake(['*/v1/messages' => Http::response(['model' => 'claude-haiku-5-5', 'content' => [['type' => 'text', 'text' => 'ok']], 'usage' => ['input_tokens' => 3, 'output_tokens' => 1]])]);
        app(ClaudeClient::class)->message('s', [['role' => 'user', 'content' => 'hi']]);
        Http::assertSent(fn (Request $r) => $r->header('x-api-key')[0] === 'sk-ant-secret-123' && $r['model'] === 'claude-haiku-5-5');
    }

    public function test_removing_the_console_key_falls_back_to_the_server_one(): void
    {
        $this->platformAdmin();
        PlatformSettings::setAiApiKey('sk-ant-console');
        config(['rowad.ai.api_key' => 'sk-ant-from-env', 'rowad.ai.model' => 'claude-opus-5-5']);
        $this->assertSame('sk-ant-console', PlatformSettings::aiApiKey());

        Livewire::test(PlatformSettingsPage::class)->fillForm(['ai_api_key_clear' => true])->call('save');
        PlatformSettings::flush();
        $this->assertSame('sk-ant-from-env', PlatformSettings::aiApiKey());
        $this->assertSame('env', PlatformSettings::aiKeySource());
        $this->assertSame('claude-opus-5-5', PlatformSettings::aiModel());
    }

    public function test_the_connection_test_reports_success_and_failure(): void
    {
        $this->platformAdmin();
        Http::fake(['*/v1/messages' => Http::sequence()
            ->push(['model' => 'claude-sonnet-5-5', 'content' => [['type' => 'text', 'text' => 'ok']]])
            ->push(['error' => ['message' => 'invalid x-api-key']], 401)]);

        Livewire::test(PlatformSettingsPage::class)
            ->fillForm(['ai_api_key' => 'sk-ant-good', 'ai_model' => 'claude-sonnet-5-5'])
            ->call('testAi')->assertNotified(__('الاتصال ناجح'))
            ->call('testAi')->assertNotified(__('فشل الاتصال'));
    }

    public function test_the_platform_login_page_has_its_own_panel(): void
    {
        $this->get('http://localhost/platform/login')->assertOk()
            ->assertSee(__('دخول إدارة المنصة'))->assertSee(__('لوحة إدارة المنصة'))->assertSee(__('لفريق تشغيل عقار نوفا فقط'));
    }
}
