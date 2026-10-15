<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Services\Portal\PortalAccounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** The assistant in the mobile app. */
class AiAssistantApiTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private string $api;

    protected function setUp(): void
    {
        parent::setUp();
        config(['rowad.ai.api_key' => 'test-key', 'rowad.ai.model' => 'test-model']);
        $tenant = $this->initializeTenancy();
        $this->api = 'http://'.$tenant->domains()->value('domain').'/api/v1';
        Http::fake(['api.anthropic.com/*' => fn (Request $r) => Http::response(
            collect($r->data()['messages'])->contains(fn ($m) => is_array($m['content']))
                ? ['content' => [['type' => 'text', 'text' => '**3** وحدات']], 'stop_reason' => 'end_turn', 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]]
                : ['content' => [['type' => 'tool_use', 'id' => 't1', 'name' => $r->data()['tools'][0]['name'], 'input' => new \stdClass]], 'stop_reason' => 'tool_use', 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]],
        )]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function token(string $login): string
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson("{$this->api}/auth/login", ['login' => $login, 'password' => 'secret-password', 'device_name' => 'iPhone'])
            ->assertOk()->json('token');
    }

    public function test_staff_ask_follow_up_list_show_and_delete(): void
    {
        $user = User::create(['name' => 'مدير', 'email' => 'admin@office.test', 'password' => 'secret-password']);
        $user->assignRole('admin');
        $token = $this->token('admin@office.test');

        $first = $this->withToken($token)->postJson("{$this->api}/assistant/ask", ['question' => 'كم وحدة لدي؟'])
            ->assertOk()->assertJsonPath('answer.content', '**3** وحدات')->assertJsonPath('answer.format', 'markdown')
            ->assertJsonPath('answer.sources.0', 'نظرة عامة على المؤسسة');
        $id = $first->json('conversation_id');

        $this->withToken($token)->postJson("{$this->api}/assistant/ask", ['question' => 'والمؤجرة؟', 'conversation_id' => $id])
            ->assertOk()->assertJsonPath('conversation_id', $id);

        $this->withToken($token)->getJson("{$this->api}/assistant/conversations")->assertOk()
            ->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.title', 'كم وحدة لدي؟');
        $this->withToken($token)->getJson("{$this->api}/assistant/conversations/{$id}")->assertOk()->assertJsonCount(4, 'messages');
        $this->withToken($token)->deleteJson("{$this->api}/assistant/conversations/{$id}")->assertNoContent();
        $this->withToken($token)->getJson("{$this->api}/assistant/conversations/{$id}")->assertNotFound();
    }

    public function test_owner_gets_the_owner_assistant_and_cannot_read_others_conversations(): void
    {
        $staff = User::create(['name' => 'مدير', 'email' => 'admin@office.test', 'password' => 'secret-password']);
        $staff->assignRole('admin');
        $staffToken = $this->token('admin@office.test');
        $staffConversation = $this->withToken($staffToken)->postJson("{$this->api}/assistant/ask", ['question' => 'سؤال'])->json('conversation_id');

        $owner = $this->makeUnit()->owners()->first();
        app(PortalAccounts::class)->issue($owner, 'owner@office.test', 'secret-password');
        $token = $this->token('owner@office.test');
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->postJson("{$this->api}/assistant/ask", ['question' => 'وحداتي؟'])->assertOk()
            ->assertJsonPath('answer.sources.0', 'ملخص حسابي')
            ->assertJsonPath('remaining_this_month', null);
        $this->withToken($token)->getJson("{$this->api}/assistant/conversations/{$staffConversation}")->assertNotFound();
        $this->withToken($token)->postJson("{$this->api}/assistant/ask", ['question' => 'x', 'conversation_id' => $staffConversation])->assertNotFound();
    }

    public function test_unavailable_assistant_and_plan_without_ai(): void
    {
        User::create(['name' => 'مدير', 'email' => 'admin@office.test', 'password' => 'secret-password'])->assignRole('admin');
        $token = $this->token('admin@office.test');

        config(['rowad.ai.api_key' => null]);
        $this->withToken($token)->postJson("{$this->api}/assistant/ask", ['question' => 'سؤال'])
            ->assertStatus(503)->assertJsonPath('code', 'ai_unavailable');

        $plan = Plan::create(['slug' => 'noai', 'name_ar' => 'بدون', 'name_en' => 'No AI', 'monthly_price' => 1, 'features' => ['api']]);
        $this->tenant->update(['plan_id' => $plan->id]);
        $this->tenant->unsetRelation('plan');
        $this->withToken($token)->getJson("{$this->api}/assistant/conversations")->assertForbidden();
    }
}
