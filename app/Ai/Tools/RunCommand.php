<?php

namespace App\Ai\Tools;

use App\Ai\Workspace;

class RunCommand extends Tool
{
    public function __construct(protected Workspace $workspace) {}

    public function name(): string
    {
        return 'run_command';
    }

    public function description(): string
    {
        return 'Run one of the allowed commands inside the app. Allowed commands: '
            .implode(' | ', $this->workspace->allowedCommands())
            .'. Run "vendor/bin/pint --dirty" after changing PHP files and "npm run build" after adding new Tailwind classes so the styles compile.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'command' => ['type' => 'string', 'description' => 'The exact command to run — must match the allow list verbatim.'],
            ],
            'required' => ['command'],
        ];
    }

    public function run(array $input): string
    {
        return $this->workspace->run((string) ($input['command'] ?? ''));
    }

    public function summarize(array $input): string
    {
        return 'Ran '.($input['command'] ?? 'a command');
    }

    public function mutatesWorkspace(): bool
    {
        return true;
    }
}
