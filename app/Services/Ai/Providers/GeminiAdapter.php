<?php

namespace App\Services\Ai\Providers;

/**
 * Google Gemini (generateContent) behind the Anthropic-shaped interface the assistant is written against.
 * Gemini has no tool-call ids, so ids are made up here and tool results are matched back to the function
 * name; a function call's thought signature is carried in the block so it can be sent back as Gemini requires.
 */
final class GeminiAdapter
{
    /**
     * @param  list<array{role: string, content: mixed}>  $messages  Anthropic-format messages
     * @param  list<array<string, mixed>>  $tools  Anthropic-format tool definitions
     * @return array{path: string, body: array<string, mixed>}
     */
    public static function request(string $model, string $system, array $messages, array $tools, int $maxTokens): array
    {
        $names = []; // tool_use id => function name, for the results that follow
        $contents = [];
        foreach ($messages as $message) {
            $parts = [];
            foreach (is_string($message['content']) ? [['type' => 'text', 'text' => $message['content']]] : $message['content'] as $block) {
                switch ($block['type'] ?? null) {
                    case 'text':
                        $parts[] = ['text' => $block['text']];
                        break;
                    case 'image':
                    case 'document':
                        $parts[] = ['inlineData' => ['mimeType' => $block['source']['media_type'] ?? 'application/octet-stream', 'data' => $block['source']['data'] ?? '']];
                        break;
                    case 'tool_use':
                        $names[$block['id']] = $block['name'];
                        $parts[] = array_filter(['functionCall' => ['name' => $block['name'], 'args' => (object) ($block['input'] ?? [])],
                            'thoughtSignature' => $block['signature'] ?? null], fn ($v) => $v !== null);
                        break;
                    case 'tool_result':
                        $result = $block['content'] ?? '';
                        $decoded = is_string($result) ? json_decode($result, true) : $result;
                        $parts[] = ['functionResponse' => ['name' => $names[$block['tool_use_id']] ?? 'tool',
                            'response' => ['result' => $decoded ?? $result]]];
                        break;
                }
            }
            if ($parts !== []) {
                $contents[] = ['role' => $message['role'] === 'assistant' ? 'model' : 'user', 'parts' => $parts];
            }
        }

        return ['path' => '/v1beta/models/'.rawurlencode($model).':generateContent', 'body' => array_filter([
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => $contents,
            'tools' => $tools === [] ? null : [['functionDeclarations' => array_map(fn (array $t) => array_filter([
                'name' => $t['name'],
                'description' => $t['description'] ?? '',
                // Gemini rejects an object schema without properties: tools that take no input send none
                'parameters' => (array) ($t['input_schema']['properties'] ?? []) === [] ? null : $t['input_schema'],
            ], fn ($v) => $v !== null), $tools)]],
            'generationConfig' => ['maxOutputTokens' => $maxTokens],
        ], fn ($v) => $v !== null)];
    }

    /** @return array{content: list<array<string, mixed>>, stop_reason: string, usage: array{input_tokens: int, output_tokens: int}, model: ?string} */
    public static function response(array $json): array
    {
        $candidate = $json['candidates'][0] ?? [];
        $blocks = [];
        $text = '';
        foreach ($candidate['content']['parts'] ?? [] as $i => $part) {
            if (isset($part['functionCall'])) {
                $blocks[] = array_filter(['type' => 'tool_use', 'id' => 'gemini_'.$i.'_'.substr(md5(json_encode($part['functionCall'])), 0, 8),
                    'name' => $part['functionCall']['name'] ?? '', 'input' => (array) ($part['functionCall']['args'] ?? []),
                    'signature' => $part['thoughtSignature'] ?? null], fn ($v) => $v !== null);
            } elseif (isset($part['text']) && ! ($part['thought'] ?? false)) {
                $text .= $part['text'];
            }
        }
        if ($text !== '') {
            array_unshift($blocks, ['type' => 'text', 'text' => $text]);
        }

        return [
            'content' => $blocks,
            'stop_reason' => collect($blocks)->contains('type', 'tool_use') ? 'tool_use' : (($candidate['finishReason'] ?? '') === 'MAX_TOKENS' ? 'max_tokens' : 'end_turn'),
            'usage' => ['input_tokens' => (int) data_get($json, 'usageMetadata.promptTokenCount', 0),
                'output_tokens' => (int) data_get($json, 'usageMetadata.candidatesTokenCount', 0)],
            'model' => $json['modelVersion'] ?? null,
        ];
    }
}
