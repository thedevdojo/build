<?php

namespace App\Ai\Tools;

use App\Ai\Workspace;

class DeleteFile extends Tool
{
    public function __construct(protected Workspace $workspace) {}

    public function name(): string
    {
        return 'delete_file';
    }

    public function description(): string
    {
        return 'Delete a single file from the app. Directories cannot be deleted.';
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
        $path = (string) ($input['path'] ?? '');

        $this->workspace->delete($path);

        return "Deleted {$path}.";
    }

    public function summarize(array $input): string
    {
        return 'Deleted '.($input['path'] ?? 'a file');
    }

    public function mutatesWorkspace(): bool
    {
        return true;
    }
}
