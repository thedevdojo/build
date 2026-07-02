<?php

namespace App\Ai\Tools;

use App\Ai\Workspace;

class ReadFile extends Tool
{
    public function __construct(protected Workspace $workspace) {}

    public function name(): string
    {
        return 'read_file';
    }

    public function description(): string
    {
        return 'Read the full contents of a file in the app.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => ['type' => 'string', 'description' => 'File path relative to the app root.'],
            ],
            'required' => ['path'],
        ];
    }

    public function run(array $input): string
    {
        return $this->workspace->read((string) ($input['path'] ?? ''));
    }

    public function summarize(array $input): string
    {
        return 'Read '.($input['path'] ?? 'a file');
    }
}
