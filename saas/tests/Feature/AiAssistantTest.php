<?php

namespace Tests\Feature;

use App\Models\AiMessage;
use App\Models\Plan;
use App\Models\User;
use App\Services\Ai\AiUnavailable;
use App\Services\Ai\Assistant;
use App\Services\Ai\Tools\StaffTools;
use App\Services\Contracts\ContractService;
use App\Services\Portal\PortalAccounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** The assistant answers from the office's data through scoped tools, within the plan's monthly quota. */
class AiAssistantTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    /** @var list<array<string, mixed>> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-05-10');
        config(['rowad.ai.api_key' => 'test-key', 'rowad.ai.model' => 'test-model']);
        $this->initializeTenancy();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        Date::setTestNow();
        parent::tearDown();
    }

    /** Fake API: first asks for a tool, then answers with text. */
    private function fakeApi(string $tool, array $input = [], string $answer = 'الإجابة'): void
    {
        Http::fake(['api.anthropic.com/*' => function (Request $request) use ($tool, $input, $answer) {
            $this->sent[] = $request->data();
            $hasResult = collect($request->data()['messages'])->contains(fn ($m) => is_array($m['content']) && ($m['content'][0]['type'] ?? null) === 'tool_result');

            return Http::response($hasResult
                ? ['content' => [['type' => 'text', 'text' => $answer]], 'stop_reason' => 'end_turn', 'usage' => ['input_tokens' => 50, 'output_tokens' => 20]]
                : ['content' => [['type' => 'tool_use', 'id' => 'call_1', 'name' => $tool, 'input' => $input]], 'stop_reason' => 'tool_use', 'usage' => ['input_tokens' => 40, 'output_tokens' => 10]]);
        }]);
    }

    private function toolResult(): array
    {
        $last = end($this->sent)['messages'];

        return json_decode(end($last)['content'][0]['content'], true);
    }

    private function staff(string $role = 'admin'): User
    {
        $user = User::create(['name' => $role, 'email' => "{$role}@office.test", 'password' => 'secret-password', 'type' => 'staff']);
        $user->assignRole($role);

        return $user;
    }

    public function test_staff_question_is_answered_from_office_data(): void
    {
        $this->makeUnit([[100, 100]], ['title' => 'شقة النخيل']);
        $this->makeUnit([[100, 100]], ['title' => 'شقة الياسمين']);
        $this->fakeApi('office_overview', answer: 'لديك وحدتان متاحتان.');
        $admin = $this->staff();

        $assistant = app(Assistant::class);
        $answer = $assistant->ask($assistant->startConversation($admin), 'كم وحدة متاحة؟');

        $this->assertSame('لديك وحدتان متاحتان.', $answer->content);
        $this->assertSame(['office_overview'], $answer->tools_used);
        $this->assertSame(90, $answer->input_tokens);
        $this->assertSame(2, $this->toolResult()['units']);
        $this->assertSame('test-model', $this->sent[0]['model']);
        $this->assertStringContainsString('Answer in Arabic', $this->sent[0]['system']);
        $this->assertSame('كم وحدة متاحة؟', $answer->conversation->title);
    }

    public function test_tools_follow_the_user_permissions(): void
    {
        $names = fn (User $u) => array_map(fn ($t) => $t->name, Assistant::tools($u));

        $this->assertContains('finance_summary', $names($this->staff('admin')));
        $agent = $names($this->staff('agent'));
        $this->assertContains('search_units', $agent);
        $this->assertNotContains('finance_summary', $agent);
        $this->assertNotContains('owner_balances', $agent);

        // Even if the model calls a tool the user lacks, it refuses.
        $tool = collect(StaffTools::all())->firstWhere('name', 'finance_summary');
        $this->assertSame(['error' => 'not_permitted'], $tool->run([], User::where('email', 'agent@office.test')->first()));
    }

    public function test_owner_assistant_sees_only_their_own_units(): void
    {
        $mine = $this->makeUnit([[100, 100]], ['title' => 'وحدتي']);
        $this->makeUnit([[100, 100]], ['title' => 'وحدة غيري']);
        $owner = app(PortalAccounts::class)->issue($mine->owners()->first(), 'owner@office.test', 'secret-pass-1')['user'];

        $this->assertSame(['my_overview', 'my_units', 'my_collections', 'my_statement'], array_map(fn ($t) => $t->name, Assistant::tools($owner)));

        $this->fakeApi('my_units');
        $assistant = app(Assistant::class);
        $assistant->ask($assistant->startConversation($owner), 'ما هي وحداتي؟');

        $units = collect($this->toolResult()['units'])->pluck('title')->all();
        $this->assertSame(['وحدتي'], $units);
        $this->assertStringContainsString('OWNER', $this->sent[0]['system']);
    }

    public function test_client_assistant_sees_only_their_own_payments(): void
    {
        $client = $this->makeClient();
        $other = $this->makeClient();
        foreach ([[$client, 1000], [$other, 9999]] as [$c, $rent]) {
            app(ContractService::class)->createRent([
                'unit_id' => $this->makeUnit()->id, 'calendar' => 'gregorian', 'contract_date' => '2026-05-01', 'start_date' => '2026-05-01',
                'end_date' => '2027-04-30', 'period_unit' => 'year', 'period_count' => 1, 'payment_cycle' => 'month', 'rent_amount' => $rent,
            ], [$c->id => 100]);
        }
        $user = app(PortalAccounts::class)->issue($client, 'client@office.test', 'secret-pass-1')['user'];

        $this->fakeApi('my_payments', ['status' => 'upcoming']);
        $assistant = app(Assistant::class);
        $assistant->ask($assistant->startConversation($user), 'متى دفعتي القادمة؟');

        $amounts = collect($this->toolResult()['rows'])->pluck('amount')->unique()->values()->all();
        $this->assertSame([1000.0], $amounts);
    }

    public function test_monthly_quota_and_plan_feature_are_enforced(): void
    {
        $plan = Plan::create(['slug' => 'tiny', 'name_ar' => 'صغيرة', 'name_en' => 'Tiny', 'monthly_price' => 1, 'max_ai_messages_per_month' => 1, 'features' => ['ai']]);
        $this->tenant->update(['plan_id' => $plan->id]);
        $this->fakeApi('office_overview');
        $admin = $this->staff();
        $assistant = app(Assistant::class);
        $conversation = $assistant->startConversation($admin);

        $assistant->ask($conversation, 'سؤال أول');
        $this->assertSame(1, AiMessage::where('role', 'assistant')->count());

        try {
            $assistant->ask($conversation, 'سؤال ثان');
            $this->fail('quota not enforced');
        } catch (AiUnavailable $e) {
            $this->assertStringContainsString('رصيد', $e->getMessage());
        }

        $plan->update(['features' => [], 'max_ai_messages_per_month' => null]);
        $this->tenant->unsetRelation('plan');
        $this->expectException(AiUnavailable::class);
        Assistant::ensureAvailable();
    }

    public function test_unconfigured_service_explains_itself_and_calls_nothing(): void
    {
        config(['rowad.ai.api_key' => null]);
        Http::fake();

        $this->expectException(AiUnavailable::class);
        try {
            Assistant::ensureAvailable();
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_follow_up_questions_carry_the_conversation(): void
    {
        $this->fakeApi('office_overview', answer: 'الأولى');
        $admin = $this->staff();
        $assistant = app(Assistant::class);
        $conversation = $assistant->startConversation($admin);
        $assistant->ask($conversation, 'سؤال 1');
        $this->sent = [];
        $assistant->ask($conversation->fresh(), 'سؤال 2');

        $first = $this->sent[0]['messages'];
        $this->assertSame(['user', 'assistant', 'user'], array_column($first, 'role'));
        $this->assertSame('الأولى', $first[1]['content']);
    }

    public function test_every_tool_runs_against_real_data(): void
    {
        $client = $this->makeClient();
        $unit = $this->makeUnit([[100, 100]], ['title' => 'شقة 7']);
        $contract = app(ContractService::class)->createRent([
            'unit_id' => $unit->id, 'calendar' => 'gregorian', 'contract_date' => '2026-01-01', 'start_date' => '2026-01-01',
            'end_date' => '2026-12-31', 'period_unit' => 'year', 'period_count' => 1, 'payment_cycle' => 'month', 'rent_amount' => 1500,
        ], [$client->id => 100]);
        app(\App\Services\Installments\InstallmentCollector::class)->collect($contract->installments()->orderBy('due_date')->first());
        $admin = $this->staff();
        $owner = app(PortalAccounts::class)->issue($unit->owners()->first(), 'o@office.test', 'secret-pass-1')['user'];
        $tenant = app(PortalAccounts::class)->issue($client, 'c@office.test', 'secret-pass-1')['user'];

        $inputs = ['unit_details' => ['unit_id' => $unit->id], 'rent_contract_details' => ['number' => $contract->number],
            'search_people' => ['type' => 'owner'], 'installments' => ['status' => 'overdue']];
        foreach ([$admin, $owner, $tenant] as $user) {
            foreach (Assistant::tools($user) as $tool) {
                $result = $tool->run($inputs[$tool->name] ?? [], $user);
                $this->assertArrayNotHasKey('error', $result, $tool->name);
                $this->assertNotFalse(json_encode($result), $tool->name);
            }
        }

        $details = collect(StaffTools::all())->firstWhere('name', 'rent_contract_details')->run(['number' => $contract->number], $admin);
        $this->assertCount(12, $details['installments']);
        $this->assertSame(1500.0, $details['installments'][0]['paid']);
    }
}
