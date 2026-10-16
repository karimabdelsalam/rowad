<?php

namespace Tests\Feature;

use App\Filament\Platform\Pages\PlatformSettingsPage;
use App\Models\User;
use App\Services\Ai\ClaudeClient;
use App\Services\Ai\Providers\GeminiAdapter;
use App\Services\Ai\Providers\OpenAiAdapter;
use App\Support\PlatformSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** The assistant's Anthropic-shaped requests work through OpenAI and Gemini too: text, images, tools and their results. */
class AiProvidersTest extends TestCase
{
    use RefreshDatabase;

    private const TOOL = ['name' => 'occupancy', 'description' => 'Occupancy rate', 'input_schema' => ['type' => 'object', 'properties' => ['year' => ['type' => 'integer']]]];

    private const IMAGE = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => 'AAAA']];

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

    /** A conversation in the middle of a tool round, as the assistant builds it. */
    private function conversation(): array
    {
        return [
            ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'كم نسبة الإشغال؟'], self::IMAGE]],
            ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'لحظة'], ['type' => 'tool_use', 'id' => 'call_1', 'name' => 'occupancy', 'input' => ['year' => 2026], 'signature' => 'sig-1']]],
            ['role' => 'user', 'content' => [['type' => 'tool_result', 'tool_use_id' => 'call_1', 'content' => '{"rate":55.6}']]],
        ];
    }

    public function test_openai_requests_and_replies_are_translated(): void
    {
        $request = OpenAiAdapter::request('gpt-5-mini', 'نظام', $this->conversation(), [self::TOOL], 500)['body'];

        $this->assertSame(['role' => 'system', 'content' => 'نظام'], $request['messages'][0]);
        $this->assertSame('image_url', $request['messages'][1]['content'][1]['type']);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $request['messages'][1]['content'][1]['image_url']['url']);
        $this->assertSame('occupancy', $request['messages'][2]['tool_calls'][0]['function']['name']);
        $this->assertSame('{"year":2026}', $request['messages'][2]['tool_calls'][0]['function']['arguments']);
        $this->assertSame(['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => '{"rate":55.6}'], $request['messages'][3]);
        $this->assertSame('function', $request['tools'][0]['type']);
        $this->assertSame(500, $request['max_completion_tokens']);

        $reply = OpenAiAdapter::response(['model' => 'gpt-5-mini', 'choices' => [['finish_reason' => 'tool_calls', 'message' => ['content' => null,
            'tool_calls' => [['id' => 'call_9', 'type' => 'function', 'function' => ['name' => 'occupancy', 'arguments' => '{"year":2025}']]]]]],
            'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 14]]);
        $this->assertSame('tool_use', $reply['stop_reason']);
        $this->assertSame(['type' => 'tool_use', 'id' => 'call_9', 'name' => 'occupancy', 'input' => ['year' => 2025]], $reply['content'][0]);
        $this->assertSame(['input_tokens' => 120, 'output_tokens' => 14], $reply['usage']);
    }

    public function test_gemini_requests_and_replies_are_translated(): void
    {
        $request = GeminiAdapter::request('gemini-2.5-flash', 'نظام', $this->conversation(), [self::TOOL, ['name' => 'today', 'description' => 'x', 'input_schema' => ['type' => 'object', 'properties' => new \stdClass]]], 500)['body'];

        $this->assertSame('نظام', $request['systemInstruction']['parts'][0]['text']);
        $this->assertSame(['mimeType' => 'image/jpeg', 'data' => 'AAAA'], $request['contents'][0]['parts'][1]['inlineData']);
        $this->assertSame('model', $request['contents'][1]['role']);
        $this->assertSame('occupancy', $request['contents'][1]['parts'][1]['functionCall']['name']);
        $this->assertSame('sig-1', $request['contents'][1]['parts'][1]['thoughtSignature'], 'the thought signature goes back with the call');
        $this->assertSame(['name' => 'occupancy', 'response' => ['result' => ['rate' => 55.6]]], $request['contents'][2]['parts'][0]['functionResponse']);
        $this->assertArrayHasKey('parameters', $request['tools'][0]['functionDeclarations'][0]);
        $this->assertArrayNotHasKey('parameters', $request['tools'][0]['functionDeclarations'][1], 'no empty object schema');

        $reply = GeminiAdapter::response(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [
            ['text' => 'thinking…', 'thought' => true], ['functionCall' => ['name' => 'occupancy', 'args' => ['year' => 2025]], 'thoughtSignature' => 'sig-2']]]]],
            'usageMetadata' => ['promptTokenCount' => 90, 'candidatesTokenCount' => 8]]);
        $this->assertSame('tool_use', $reply['stop_reason']);
        $this->assertSame(['occupancy', ['year' => 2025], 'sig-2'], [$reply['content'][0]['name'], $reply['content'][0]['input'], $reply['content'][0]['signature']]);
        $this->assertCount(1, $reply['content'], 'thoughts are not part of the answer');
    }

    public function test_the_client_talks_to_the_selected_provider(): void
    {
        PlatformSettings::save(['ai_provider' => 'openai', 'openai_model' => 'gpt-5-mini']);
        PlatformSettings::setAiApiKey('sk-openai-1', 'openai');
        PlatformSettings::setAiApiKey('AIza-gemini-1', 'gemini');
        Http::fake([
            'api.openai.com/*' => Http::response(['model' => 'gpt-5-mini', 'choices' => [['finish_reason' => 'stop', 'message' => ['content' => 'مرحبًا']]]]),
            'generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => 'أهلًا']]]]]]),
        ]);

        $this->assertTrue(ClaudeClient::configured());
        $reply = app(ClaudeClient::class)->message('s', [['role' => 'user', 'content' => 'hi']]);
        $this->assertSame('مرحبًا', $reply['content'][0]['text']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'api.openai.com/v1/chat/completions') && $r->header('Authorization')[0] === 'Bearer sk-openai-1');

        PlatformSettings::save(['ai_provider' => 'gemini', 'gemini_model' => 'gemini-2.5-flash']);
        $this->assertSame('أهلًا', app(ClaudeClient::class)->message('s', [['role' => 'user', 'content' => 'hi']])['content'][0]['text']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/v1beta/models/gemini-2.5-flash:generateContent') && $r->header('x-goog-api-key')[0] === 'AIza-gemini-1');
    }

    public function test_the_console_saves_each_providers_key_and_switches(): void
    {
        $this->actingAs(User::create(['name' => 'Platform', 'email' => 'root@rowad.test', 'password' => 'secret-password', 'is_super_admin' => true]));
        Filament::setCurrentPanel('platform');

        Livewire::test(PlatformSettingsPage::class)
            ->fillForm(['ai_provider' => 'gemini', 'gemini_api_key' => 'AIza-secret', 'gemini_model' => 'gemini-2.5-pro', 'openai_api_key' => 'sk-openai-secret'])
            ->call('save')->assertHasNoFormErrors();
        PlatformSettings::flush();

        $this->assertSame('gemini', PlatformSettings::aiProvider());
        $this->assertSame('AIza-secret', PlatformSettings::aiApiKey());
        $this->assertSame('gemini-2.5-pro', PlatformSettings::aiModel());
        $this->assertSame('sk-openai-secret', PlatformSettings::aiApiKey('openai'));
        $this->assertNull(PlatformSettings::aiApiKey('anthropic'));

        Livewire::test(PlatformSettingsPage::class)->assertSet('data.gemini_api_key', null)->assertDontSee('AIza-secret');
    }
}
