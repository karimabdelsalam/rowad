<?php

namespace App\Services\Ai\Providers;

/**
 * OpenAI Chat Completions behind the Anthropic-shaped interface the assistant is written against: requests
 * (system prompt, text, images, PDFs, tool definitions, tool calls and results) are translated to the OpenAI
 * format, and the reply back to content blocks with a stop_reason. Works with any OpenAI-compatible endpoint.
 */
final class OpenAiAdapter
{
    /**
     * @param  list<array{role: string, content: mixed}>  $messages  Anthropic-format messages
     * @param  list<array<string, mixed>>  $tools  Anthropic-format tool definitions
     * @return array{path: string, body: array<string, mixed>}
     */
    public static function request(string $model, string $system, array $messages, array $tools, int $maxTokens): array
    {
        $out = [['role' => 'system', 'content' => $system]];
        foreach ($messages as $message) {
            array_push($out, ...self::message($message));
        }

        return ['path' => '/v1/chat/completions', 'body' => array_filter([
            'model' => $model,
            'messages' => $out,
            'max_completion_tokens' => $maxTokens,
            'tools' => $tools === [] ? null : array_map(fn (array $t) => ['type' => 'function', 'function' => [
                'name' => $t['name'],
                'description' => $t['description'] ?? '',
                'parameters' => $t['input_schema'] ?? ['type' => 'object', 'properties' => new \stdClass],
            ]], $tools),
        ], fn ($v) => $v !== null)];
    }

    /** @return array{content: list<array<string, mixed>>, stop_reason: string, usage: array{input_tokens: int, output_tokens: int}, model: ?string} */
    public static function response(array $json): array
    {
        $choice = $json['choices'][0] ?? [];
        $message = $choice['message'] ?? [];
        $blocks = [];
        if (filled($message['content'] ?? null)) {
            $blocks[] = ['type' => 'text', 'text' => is_array($message['content']) ? collect($message['content'])->pluck('text')->implode('') : (string) $message['content']];
        }
        foreach ($message['tool_calls'] ?? [] as $call) {
            $blocks[] = ['type' => 'tool_use', 'id' => $call['id'], 'name' => $call['function']['name'] ?? '',
                'input' => json_decode($call['function']['arguments'] ?? '{}', true) ?: []];
        }

        return [
            'content' => $blocks,
            'stop_reason' => collect($blocks)->contains('type', 'tool_use') ? 'tool_use' : (($choice['finish_reason'] ?? '') === 'length' ? 'max_tokens' : 'end_turn'),
            'usage' => ['input_tokens' => (int) data_get($json, 'usage.prompt_tokens', 0), 'output_tokens' => (int) data_get($json, 'usage.completion_tokens', 0)],
            'model' => $json['model'] ?? null,
        ];
    }

    /** One Anthropic message can become several OpenAI ones (each tool result is its own "tool" message). */
    private static function message(array $message): array
    {
        $content = $message['content'];
        if (is_string($content)) {
            return [['role' => $message['role'], 'content' => $content]];
        }
        if ($message['role'] === 'assistant') {
            $text = collect($content)->where('type', 'text')->pluck('text')->implode("\n");
            $calls = collect($content)->where('type', 'tool_use')->map(fn ($b) => ['id' => $b['id'], 'type' => 'function',
                'function' => ['name' => $b['name'], 'arguments' => json_encode((object) ($b['input'] ?? []), JSON_UNESCAPED_UNICODE)]])->values()->all();

            return [array_filter(['role' => 'assistant', 'content' => $text !== '' ? $text : null, 'tool_calls' => $calls ?: null], fn ($v) => $v !== null)];
        }

        $out = [];
        $parts = [];
        foreach ($content as $block) {
            match ($block['type'] ?? null) {
                'tool_result' => $out[] = ['role' => 'tool', 'tool_call_id' => $block['tool_use_id'],
                    'content' => is_string($block['content'] ?? null) ? $block['content'] : json_encode($block['content'] ?? '', JSON_UNESCAPED_UNICODE)],
                'text' => $parts[] = ['type' => 'text', 'text' => $block['text']],
                'image' => $parts[] = ['type' => 'image_url', 'image_url' => ['url' => self::dataUrl($block['source'])]],
                'document' => $parts[] = ['type' => 'file', 'file' => ['filename' => 'document.pdf', 'file_data' => self::dataUrl($block['source'])]],
                default => null,
            };
        }
        if ($parts !== []) {
            $out[] = ['role' => 'user', 'content' => $parts];
        }

        return $out;
    }

    private static function dataUrl(array $source): string
    {
        return 'data:'.($source['media_type'] ?? 'application/octet-stream').';base64,'.($source['data'] ?? '');
    }
}
