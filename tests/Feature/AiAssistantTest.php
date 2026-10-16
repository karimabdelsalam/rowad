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

        $inputs = ['unit_details' => ['unit_id' => $unit->id], 'suggest_rent' => ['unit_id' => $unit->id], 'payment_risk' => ['client_id' => $client->id],
            'rent_contract_details' => ['number' => $contract->number],
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

    public function test_chat_page_asks_and_shows_the_answer(): void
    {
        $this->fakeApi('office_overview', answer: '**الإشغال** ممتاز');
        $admin = $this->staff();
        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel('admin');

        \Livewire\Livewire::test(\App\Filament\Admin\Pages\Assistant::class)
            ->assertSee('كيف أساعدك اليوم؟')
            ->call('send', 'كيف الأداء؟')
            ->assertSet('pending', 'كيف الأداء؟')
            ->call('answer')
            ->assertSet('pending', null)
            ->assertSee('<strong>الإشغال</strong>', false)
            ->assertSee('نظرة عامة على المؤسسة');

        // Another user cannot open it.
        $conversation = \App\Models\AiConversation::sole();
        $this->actingAs($this->staff('agent'));
        \Livewire\Livewire::test(\App\Filament\Admin\Pages\Assistant::class)
            ->call('open', $conversation->id)
            ->assertSet('conversationId', null)
            ->call('deleteConversation', $conversation->id);
        $this->assertNotNull($conversation->fresh());
    }

    public function test_html_in_answers_is_never_rendered(): void
    {
        $html = \App\Filament\Pages\AssistantPage::markdown('<script>alert(1)</script> [x](javascript:alert(1))');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('href="javascript', $html);
    }

    public function test_assistant_is_hidden_when_the_plan_has_no_ai_and_errors_keep_the_question(): void
    {
        $admin = $this->staff();
        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel('admin');
        config(['rowad.ai.api_key' => null]);

        \Livewire\Livewire::test(\App\Filament\Admin\Pages\Assistant::class)
            ->assertSee('غير مفعّل')
            ->call('send', 'سؤال')
            ->call('answer')
            ->assertSet('question', 'سؤال')
            ->assertSee('غير مفعّل');

        $plan = Plan::create(['slug' => 'noai', 'name_ar' => 'بدون', 'name_en' => 'No AI', 'monthly_price' => 1, 'features' => []]);
        $this->tenant->update(['plan_id' => $plan->id]);
        $this->tenant->unsetRelation('plan');
        $this->assertFalse(\App\Filament\Admin\Pages\Assistant::canAccess());
    }

    /** Fake API that answers any one-shot analysis with fixed text and records the request. */
    private function fakeAnalysis(string $answer = 'تحليل'): void
    {
        Http::fake(['api.anthropic.com/*' => function (Request $request) use ($answer) {
            $this->sent[] = $request->data();

            return Http::response(['content' => [['type' => 'text', 'text' => $answer]], 'stop_reason' => 'end_turn', 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]]);
        }]);
    }

    public function test_report_analysis_reads_the_filtered_report_and_opens_in_the_assistant(): void
    {
        \App\Models\ReceiptVoucher::create(['issued_at' => '2026-04-01', 'amount' => 777, 'party_name' => 'مستأجر التقرير']);
        $this->fakeAnalysis('## الدخل ممتاز');
        $this->actingAs($this->staff());
        \Filament\Facades\Filament::setCurrentPanel('admin');

        $page = \Livewire\Livewire::test(\App\Filament\Admin\Clusters\Reports\Pages\IncomeReport::class)
            ->callAction('analyze');

        $conversation = \App\Models\AiConversation::sole();
        $page->assertRedirect(\App\Filament\Admin\Pages\Assistant::getUrl(['c' => $conversation->id]));
        $this->assertSame('report', $conversation->context['type']);
        $this->assertStringContainsString('777', $this->sent[0]['messages'][0]['content']);
        $this->assertArrayNotHasKey('tools', $this->sent[0]);
        $this->assertSame(['user', 'assistant'], $conversation->messages->pluck('role')->all());
        $this->assertStringStartsWith('تحليل', $conversation->messages[0]->content); // readable, not the internal instruction
    }

    public function test_dashboard_insights_are_generated_once_and_shared(): void
    {
        $this->fakeAnalysis('- **الإشغال** 50%');
        $this->actingAs($this->staff());
        \Filament\Facades\Filament::setCurrentPanel('admin');

        \Livewire\Livewire::test(\App\Filament\Admin\Widgets\AiInsights::class)
            ->assertSee('توليد الرؤى')
            ->call('generate')
            ->assertSee('<strong>الإشغال</strong>', false)
            ->assertSee('تحديث الرؤى');

        $this->assertStringContainsString('"occupancy"', $this->sent[0]['messages'][0]['content']);
        $this->actingAs($this->staff('accountant'));
        \Livewire\Livewire::test(\App\Filament\Admin\Widgets\AiInsights::class)->assertSee('<strong>الإشغال</strong>', false);
        $this->assertCount(1, $this->sent);

        $this->actingAs($this->staff('agent')); // no reports permission
        $this->assertFalse(\App\Filament\Admin\Widgets\AiInsights::canView());
    }

    public function test_asking_about_a_record_carries_it_as_context(): void
    {
        $unit = $this->makeUnit([[100, 100]], ['title' => 'شقة السياق']);
        $this->fakeApi('unit_details', ['unit_id' => $unit->id]);
        $this->actingAs($this->staff());
        \Filament\Facades\Filament::setCurrentPanel('admin');

        \Livewire\Livewire::withQueryParams(['about' => 'unit:'.$unit->id])
            ->test(\App\Filament\Admin\Pages\Assistant::class)
            ->assertSee('شقة السياق')
            ->assertSee('لخص وضع هذه الوحدة')
            ->call('send', 'لخص وضع هذه الوحدة')
            ->call('answer');

        $this->assertStringContainsString("unit id {$unit->id}", $this->sent[0]['messages'][0]['content']);
        $this->assertSame('شقة السياق', $this->toolResult()['title']);

        // A portal user cannot use ?about to reach office records.
        $owner = app(PortalAccounts::class)->issue($unit->owners()->first(), 'own@office.test', 'secret-pass-1')['user'];
        $this->actingAs($owner);
        \Filament\Facades\Filament::setCurrentPanel('owner');
        \Livewire\Livewire::withQueryParams(['about' => 'unit:'.$unit->id])
            ->test(\App\Filament\Owner\Pages\Assistant::class)
            ->assertSet('subject', null);
    }

    public function test_ai_drafts_a_bulk_email_into_the_form_for_review(): void
    {
        $this->fakeAnalysis('```json
{"subject": "تذكير بموعد السداد", "body": "عملاءنا الكرام،\n\nنذكركم **بسداد** الإيجار قبل يوم 5."}
```');
        $this->actingAs($this->staff());
        \Filament\Facades\Filament::setCurrentPanel('admin');

        \Livewire\Livewire::test(\App\Filament\Admin\Resources\Campaigns\Pages\CreateCampaign::class)
            ->fillForm(['channel' => 'mail', 'audience' => 'renters'])
            ->callAction(\Filament\Actions\Testing\TestAction::make('aiWrite')->schemaComponent('body'), ['brief' => 'ذكرهم بالسداد قبل 5', 'tone' => 'ودّي', 'language' => 'ar'])
            ->assertHasNoActionErrors()
            ->assertSchemaStateSet(['subject' => 'تذكير بموعد السداد'])
            ->assertSchemaStateSet(fn (array $state) => [
                'body' => tap($state['body'], fn ($body) => $this->assertStringContainsString('بسداد', is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE))),
            ]);

        $request = $this->sent[0]['messages'][0]['content'];
        $this->assertStringContainsString('ذكرهم بالسداد قبل 5', $request);
        $this->assertStringContainsString('Tone: ودّي', $request);
        $this->assertSame(1, AiMessage::where('kind', 'writing')->where('role', 'assistant')->count()); // counts against the quota
        $this->assertSame(0, \App\Models\AiConversation::where('panel', 'admin')->count()); // not in the chat history
    }

    public function test_sms_drafts_are_plain_and_short(): void
    {
        $this->fakeAnalysis('{"subject": null, "body": "**تذكير:** سداد الإيجار قبل 5 من الشهر. شكرًا لكم"}');
        $draft = app(Assistant::class)->write($this->staff(), 'sms', 'تذكير', 'مختصر ومباشر', 'ar');

        $this->assertSame('تذكير: سداد الإيجار قبل 5 من الشهر. شكرًا لكم', $draft['body']);
        $this->assertNull($draft['subject']);
    }

    public function test_owner_dashboard_summary_reads_only_their_own_data(): void
    {
        $mine = $this->makeUnit([[100, 100]], ['title' => 'عمارتي 1']);
        $this->makeUnit([[100, 100]], ['title' => 'عمارة جاري']);
        $owner = app(PortalAccounts::class)->issue($mine->owners()->first(), 'own@office.test', 'secret-pass-1')['user'];
        $this->fakeAnalysis('- **وحدة واحدة** مؤجرة');
        $this->actingAs($owner);
        \Filament\Facades\Filament::setCurrentPanel('owner');

        \Livewire\Livewire::test(\App\Filament\Owner\Widgets\OwnerAiSummary::class)
            ->assertSee('ملخصي الذكي')
            ->call('generate')
            ->assertSee('<strong>وحدة واحدة</strong>', false);

        $request = $this->sent[0]['messages'][0]['content'];
        $this->assertStringContainsString('عمارتي 1', $request);
        $this->assertStringNotContainsString('عمارة جاري', $request);

        // Another owner does not see it.
        $other = app(PortalAccounts::class)->issue(\App\Models\Owner::where('id', '!=', $mine->owners()->first()->id)->first(), 'other@office.test', 'secret-pass-1')['user'];
        $this->actingAs($other);
        \Livewire\Livewire::test(\App\Filament\Owner\Widgets\OwnerAiSummary::class)->assertDontSee('وحدة واحدة');
    }
}
