<?php

namespace App\Services\Ai;

use App\Support\PlatformSettings;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/** Thin wrapper over the Anthropic Messages API (one request, no streaming). Key and model: platform console, else .env. */
class ClaudeClient
{
    public static function configured(): bool
    {
        return filled(PlatformSettings::aiApiKey()) && filled(PlatformSettings::aiModel());
    }

    /**
     * @param  list<array{role: string, content: mixed}>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @return array<string, mixed> the API response (content blocks, stop_reason, usage)
     *
     * @throws AiUnavailable
     */
    public function message(string $system, array $messages, array $tools = [], ?int $maxTokens = null): array
    {
        if (! self::configured()) {
            throw new AiUnavailable(__('المساعد الذكي غير مفعّل بعد. يضيف مدير المنصة مفتاح الخدمة لتفعيله.'));
        }

        $payload = array_filter([
            'model' => PlatformSettings::aiModel(),
            'max_tokens' => $maxTokens ?? config('rowad.ai.max_tokens'),
            'system' => $system,
            'messages' => $messages,
            'tools' => $tools ?: null,
        ], fn ($v) => $v !== null);

        try {
            return Http::baseUrl((string) config('rowad.ai.base_url'))
                ->withHeaders(['x-api-key' => PlatformSettings::aiApiKey(), 'anthropic-version' => '2023-06-01'])
                ->timeout((int) config('rowad.ai.timeout'))
                ->retry(2, 1500, fn ($e) => $e instanceof RequestException && in_array($e->response->status(), [429, 500, 502, 503, 529], true), throw: true)
                ->post('/v1/messages', $payload)
                ->throw()
                ->json();
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
