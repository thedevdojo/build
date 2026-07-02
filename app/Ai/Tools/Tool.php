<?php

namespace App\Ai\Tools;

abstract class Tool
{
    abstract public function name(): string;

    abstract public function description(): string;

    /**
     * JSON Schema for the tool's input.
     *
     * @return array<string, mixed>
     */
    abstract public function schema(): array;

    /**
     * Execute the tool and return output for the model.
     *
     * @param  array<string, mixed>  $input
     */
    abstract public function run(array $input): string;

    /**
     * Human-readable label shown as an action card in the chat while the
     * agent works ("Edited resources/views/pages/index.blade.php").
     *
     * @param  array<string, mixed>  $input
     */
    public function summarize(array $input): string
    {
        return $this->name();
    }

    /**
     * Whether a successful run changes the target app — used to decide
     * when the canvas should reload.
     */
    public function mutatesWorkspace(): bool
    {
        return false;
    }
}
