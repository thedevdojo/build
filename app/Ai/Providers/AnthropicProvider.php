<?php

namespace App\Ai\Providers;

use App\Ai\Contracts\Provider;
use App\Ai\ProviderResponse;
use App\Ai\ToolCall;
use App\Ai\Tools\Tool;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class AnthropicProvider implements Provider
{
    public function __construct(
        protected string $apiKey,
        protected string $model = 'claude-fable-5',
        protected int $maxTokens = 8192,
    ) {}

    public function name(): string
    {
        return 'anthropic:'.$this->model;
    }

    public function supportsTools(): bool
    {
        return true;
    }

    public function chat(string $system, array $messages, array $tools = []): ProviderResponse
    {
        $payload = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'system' => $system,
            'messages' => $messages,
        ];

        if ($tools !== []) {
            $payload['tools'] = array_map(fn (Tool $tool): array => [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'input_schema' => $tool->schema(),
            ], $tools);
        }

        $data = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ])
            ->timeout(300)
            ->connectTimeout(10)
            ->retry(2, 2000, function (\Throwable $exception): bool {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && in_array($exception->response->status(), [429, 500, 502, 503, 529], true));
            })
            ->post('https://api.anthropic.com/v1/messages', $payload)
            ->throw()
            ->json();

        $text = '';
        $toolCalls = [];

        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= $block['text'];
            }

            if (($block['type'] ?? null) === 'tool_use') {
                $toolCalls[] = new ToolCall($block['id'], $block['name'], $block['input'] ?? []);
            }
        }

        return new ProviderResponse(
            text: trim($text),
            toolCalls: $toolCalls,
            stopReason: $data['stop_reason'] ?? 'end_turn',
            content: $data['content'] ?? [],
        );
    }
}
