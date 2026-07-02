<?php

namespace App\Ai\Tools;

use App\Ai\Workspace;

class ListFiles extends Tool
{
    public function __construct(protected Workspace $workspace) {}

    public function name(): string
    {
        return 'list_files';
    }

    public function description(): string
    {
        return 'List files and directories in the app, as a flat tree. Directories end with "/". Build noise (vendor, node_modules, storage, …) is excluded.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => ['type' => 'string', 'description' => 'Directory to list, relative to the app root. Omit for the root.'],
                'depth' => ['type' => 'integer', 'description' => 'How many levels deep to walk. Default 3.'],
            ],
            'required' => [],
        ];
    }

    public function run(array $input): string
    {
        return $this->workspace->tree(
            (string) ($input['path'] ?? ''),
            max(1, min((int) ($input['depth'] ?? 3), 6)),
        );
    }

    public function summarize(array $input): string
    {
        $path = trim((string) ($input['path'] ?? ''), '/');

        return 'Explored '.($path === '' ? 'the app structure' : $path);
    }
}
