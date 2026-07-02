<?php

namespace App\Ai\Tools;

use App\Ai\Workspace;

class EditFile extends Tool
{
    public function __construct(protected Workspace $workspace) {}

    public function name(): string
    {
        return 'edit_file';
    }

    public function description(): string
    {
        return 'Replace an exact string in a file. The search string must appear exactly once (include surrounding lines to disambiguate), or set replace_all to change every occurrence. Copy the search text verbatim from read_file output, including indentation.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => ['type' => 'string', 'description' => 'File path relative to the app root.'],
                'search' => ['type' => 'string', 'description' => 'Exact text to find.'],
                'replace' => ['type' => 'string', 'description' => 'Replacement text.'],
                'replace_all' => ['type' => 'boolean', 'description' => 'Replace every occurrence instead of requiring a unique match.'],
            ],
            'required' => ['path', 'search', 'replace'],
        ];
    }

    public function run(array $input): string
    {
        $path = (string) ($input['path'] ?? '');

        $count = $this->workspace->replace(
            $path,
            (string) ($input['search'] ?? ''),
            (string) ($input['replace'] ?? ''),
            (bool) ($input['replace_all'] ?? false),
        );

        return "Replaced {$count} occurrence(s) in {$path}.";
    }

    public function summarize(array $input): string
    {
        return 'Edited '.($input['path'] ?? 'a file');
    }

    public function mutatesWorkspace(): bool
    {
        return true;
    }
}
