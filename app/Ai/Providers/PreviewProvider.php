<?php

namespace App\Ai\Providers;

use App\Ai\Contracts\Provider;
use App\Ai\ProviderResponse;

/**
 * The no-key fallback. Keeps the whole builder usable — chat, history,
 * memory, canvas — while explaining how to bring the real engine online.
 */
class PreviewProvider implements Provider
{
    public function name(): string
    {
        return 'preview';
    }

    public function supportsTools(): bool
    {
        return false;
    }

    public function chat(string $system, array $messages, array $tools = []): ProviderResponse
    {
        $prompt = $this->lastUserText($messages);

        $text = <<<REPLY
        I've saved this request to our conversation:

        “{$prompt}”

        Right now I'm in preview mode — no AI engine is connected yet. Add your Anthropic key to the builder's .env to bring me fully online:

        ANTHROPIC_API_KEY=sk-ant-…

        Once connected, I'll make changes like this directly to your app: reading and editing its files, creating pages and components, running formatting and builds, and reloading the canvas as I work — and I'll remember the decisions we make together across conversations.
        REPLY;

        return new ProviderResponse(text: $text);
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     */
    protected function lastUserText(array $messages): string
    {
        foreach (array_reverse($messages) as $message) {
            if (($message['role'] ?? null) === 'user' && is_string($message['content'] ?? null)) {
                return $message['content'];
            }
        }

        return 'your request';
    }
}
