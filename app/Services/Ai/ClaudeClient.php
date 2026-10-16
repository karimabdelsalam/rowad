<?php

namespace App\Services\Ai;

use App\Services\Ai\Providers\GeminiAdapter;
use App\Services\Ai\Providers\OpenAiAdapter;
use App\Support\PlatformSettings;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * The AI service, one request at a time (no streaming). Callers speak the Anthropic Messages format (content
 * blocks, tool_use / tool_result, stop_reason); with OpenAI or Gemini selected in the platform console the
 * request and reply are translated by an adapter, so the assistant, document reading and writing help work
 * the same with any of the three. Key and model: platform console, else .env.
 */
class ClaudeClient
{
    public const PROVIDERS = ['anthropic' => 'Claude (Anthropic)', 'openai' => 'OpenAI', 'gemini' => 'Google Gemini'];

    public static function configured(): bool
    {
        return filled(PlatformSettings::aiApiKey()) && filled(PlatformSettings::aiModel());
    }

    /**
     * @param  list<array{role: string, content: mixed}>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @return array<string, mixed> Anthropic-shaped response (content blocks, stop_reason, usage)
     *
     * @throws AiUnavailable
     */
    public function message(string $system, array $messages, array $tools = [], ?int $maxTokens = null): array
    {
        if (! self::configured()) {
            throw new AiUnavailable(__('المساعد الذكي غير مفعّل بعد. يضيف مدير المنصة مفتاح الخدمة لتفعيله.'));
        }
        $provider = PlatformSettings::aiProvider();
        $model = (string) PlatformSettings::aiModel();
        $key = (string) PlatformSettings::aiApiKey();
        $maxTokens ??= (int) config('rowad.ai.max_tokens');

        [$base, $headers, $request, $translate] = match ($provider) {
            'openai' => [config('rowad.ai.openai_base_url'), ['Authorization' => 'Bearer '.$key],
                OpenAiAdapter::request($model, $system, $messages, $tools, $maxTokens), OpenAiAdapter::response(...)],
            'gemini' => [config('rowad.ai.gemini_base_url'), ['x-goog-api-key' => $key],
                GeminiAdapter::request($model, $system, $messages, $tools, $maxTokens), GeminiAdapter::response(...)],
            default => [config('rowad.ai.base_url'), ['x-api-key' => $key, 'anthropic-version' => '2023-06-01'],
                ['path' => '/v1/messages', 'body' => array_filter(['model' => $model, 'max_tokens' => $maxTokens, 'system' => $system,
                    'messages' => $messages, 'tools' => $tools ?: null], fn ($v) => $v !== null)], fn (array $json) => $json],
        };

        try {
            $json = Http::baseUrl(rtrim((string) $base, '/'))
                ->withHeaders($headers)
                ->timeout((int) config('rowad.ai.timeout'))
                ->retry(2, 1500, fn ($e) => $e instanceof RequestException && in_array($e->response->status(), [429, 500, 502, 503, 529], true), throw: true)
                ->post($request['path'], $request['body'])
                ->throw()
                ->json();

            return $translate((array) $json);
        } catch (RequestException $e) {
            report($e);

            throw new AiUnavailable($e->response->status() === 429
                ? __('المساعد مشغول حاليًا، حاول بعد قليل.')
                : __('تعذّر الوصول إلى خدمة الذكاء الاصطناعي. حاول مرة أخرى.'));
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            report($e);

            throw new AiUnavailable(__('تعذّر الوصول إلى خدمة الذكاء الاصطناعي. حاول مرة أخرى.'));
        }
    }
}
