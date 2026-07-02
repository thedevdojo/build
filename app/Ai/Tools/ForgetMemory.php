<?php

namespace App\Ai\Tools;

use App\Ai\WorkspaceException;
use App\Models\Memory;

class ForgetMemory extends Tool
{
    public function name(): string
    {
        return 'forget_memory';
    }

    public function description(): string
    {
        return 'Delete a saved memory by slug when it is wrong or no longer true.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'slug' => ['type' => 'string', 'description' => 'Slug of the memory to delete.'],
            ],
            'required' => ['slug'],
        ];
    }

    public function run(array $input): string
    {
        $slug = (string) ($input['slug'] ?? '');

        $deleted = Memory::query()->where('slug', $slug)->delete();

        if ($deleted === 0) {
            throw new WorkspaceException("No memory found with slug \"{$slug}\".");
        }

        return "Forgot \"{$slug}\".";
    }

    public function summarize(array $input): string
    {
        return 'Forgot '.($input['slug'] ?? 'a memory');
    }
}
