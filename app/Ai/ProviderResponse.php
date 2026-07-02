<?php

namespace App\Ai;

class ProviderResponse
{
    /**
     * @param  array<int, ToolCall>  $toolCalls
     * @param  array<int, array<string, mixed>>  $content  Raw content blocks, replayed verbatim as the assistant turn in tool loops.
     */
    public function __construct(
        public string $text = '',
        public array $toolCalls = [],
        public string $stopReason = 'end_turn',
        public array $content = [],
    ) {}

    public function wantsTools(): bool
    {
        return $this->toolCalls !== [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function contentBlocks(): array
    {
        return $this->content !== [] ? $this->content : [['type' => 'text', 'text' => $this->text]];
    }
}
