<?php

namespace App\Ai;

class ToolCall
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $input = [],
    ) {}
}
