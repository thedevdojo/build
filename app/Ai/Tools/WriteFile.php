<?php

namespace App\Ai\Tools;

use App\Ai\Workspace;

class WriteFile extends Tool
{
    public function __construct(protected Workspace $workspace) {}

    public function name(): string
    {
        return 'write_file';
    }

    public function description(): string
    {
        return 'Create a new file or fully overwrite an existing one. Parent directories are created automatically. For small changes to an existing file prefer edit_file.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => ['type' => 'string', 'description' => 'File path relative to the app root.'],
                'content' => ['type' => 'string', 'description' => 'The complete file contents.'],
            ],
            'required' => ['path', 'content'],
        ];
    }

    public function run(array $input): string
    {
        $path = (string) ($input['path'] ?? '');

        $this->workspace->write($path, (string) ($input['content'] ?? ''));

        return "Wrote {$path}.";
    }

    public function summarize(array $input): string
    {
        return 'Wrote '.($input['path'] ?? 'a file');
    }

    public function mutatesWorkspace(): bool
    {
        return true;
    }
}
