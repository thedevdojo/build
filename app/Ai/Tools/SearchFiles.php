<?php

namespace App\Ai\Tools;

use App\Ai\Workspace;

class SearchFiles extends Tool
{
    public function __construct(protected Workspace $workspace) {}

    public function name(): string
    {
        return 'search_files';
    }

    public function description(): string
    {
        return 'Case-insensitive text search across the app. Returns "path:line: text" matches. Use it to find where something is defined or rendered before editing.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Text to search for.'],
                'path' => ['type' => 'string', 'description' => 'Directory to search within, relative to the app root. Omit for the whole app.'],
            ],
            'required' => ['query'],
        ];
    }

    public function run(array $input): string
    {
        return $this->workspace->search(
            (string) ($input['query'] ?? ''),
            (string) ($input['path'] ?? ''),
        );
    }

    public function summarize(array $input): string
    {
        return 'Searched for "'.($input['query'] ?? '').'"';
    }
}
