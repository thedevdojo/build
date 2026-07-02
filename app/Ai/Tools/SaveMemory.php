<?php

namespace App\Ai\Tools;

use App\Ai\WorkspaceException;
use App\Models\Memory;
use Illuminate\Support\Str;

class SaveMemory extends Tool
{
    public function name(): string
    {
        return 'save_memory';
    }

    public function description(): string
    {
        return 'Save a durable fact about this app or its owner so future conversations remember it — branding, naming, design direction, decisions, preferences, roadmap items. One fact per entry. Reusing an existing slug updates that entry.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'slug' => ['type' => 'string', 'description' => 'Short kebab-case identifier, e.g. "brand-name" or "pricing-tiers".'],
                'kind' => ['type' => 'string', 'enum' => ['fact', 'preference', 'decision', 'style'], 'description' => 'What kind of memory this is.'],
                'content' => ['type' => 'string', 'description' => 'The fact itself, one or two sentences, written so it stands alone.'],
            ],
            'required' => ['slug', 'content'],
        ];
    }

    public function run(array $input): string
    {
        $slug = Str::slug((string) ($input['slug'] ?? ''));
        $content = trim((string) ($input['content'] ?? ''));

        if ($slug === '' || $content === '') {
            throw new WorkspaceException('Both slug and content are required to save a memory.');
        }

        Memory::query()->updateOrCreate(
            ['slug' => $slug],
            ['kind' => (string) ($input['kind'] ?? 'fact'), 'content' => $content],
        );

        return "Remembered \"{$slug}\".";
    }

    public function summarize(array $input): string
    {
        return 'Remembered '.($input['slug'] ?? 'a fact');
    }
}
