<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Provider
    |--------------------------------------------------------------------------
    |
    | Which provider drives the build agent. Any provider without a
    | configured key falls back to "preview" — the builder UI stays fully
    | functional, the agent just explains that no engine is connected.
    |
    */

    'provider' => env('BUILDER_AI_PROVIDER', 'anthropic'),

    'providers' => [
        'anthropic' => [
            'key' => env('ANTHROPIC_API_KEY'),
            'model' => env('BUILDER_AI_MODEL', 'claude-fable-5'),
            'max_tokens' => (int) env('BUILDER_AI_MAX_TOKENS', 8192),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Limits
    |--------------------------------------------------------------------------
    */

    'max_steps' => (int) env('BUILDER_AI_MAX_STEPS', 16),

    /*
    |--------------------------------------------------------------------------
    | Workspace
    |--------------------------------------------------------------------------
    |
    | The target app's codebase the agent operates on. Locally that's the
    | sibling checkout at ~/Sites/default; in the shared K8s pod it's the
    | app directory mounted next to the builder.
    |
    | "protected" paths can never be written or deleted; "hidden" paths are
    | excluded from listings and searches; "commands" is the exact allow
    | list the run_command tool accepts.
    |
    */

    'workspace' => [
        'root' => env('PLATFORM_WORKSPACE_PATH', dirname(base_path()).'/default'),

        'protected' => [
            '.git',
            '.env',
            '.env.example',
            // In the shared pod the builder itself lives inside the app
            // workspace (a hidden .builder directory) — the agent must never
            // edit its own runtime.
            '.builder',
            '.platform-provisioned',
            'vendor',
            'node_modules',
            'storage',
            'bootstrap/cache',
            'public/build',
            'packages',
            'composer.lock',
            'package-lock.json',
        ],

        'hidden' => [
            '.git',
            '.builder',
            'vendor',
            'node_modules',
            'storage',
            'bootstrap/cache',
            'public/build',
            '.idea',
            '.vscode',
        ],

        'commands' => [
            'vendor/bin/pint --dirty',
            'npm run build',
            'php artisan route:list',
            'php artisan migrate --force',
            'php artisan view:clear',
            'php artisan config:clear',
            'php artisan test --compact',
            'composer dump-autoload',
        ],

        'max_read_bytes' => 262144,
    ],

    /*
    |--------------------------------------------------------------------------
    | Memory & Summaries
    |--------------------------------------------------------------------------
    |
    | The agent keeps durable notes about the app (branding, decisions,
    | preferences) across conversations, and long conversations fold their
    | oldest turns into a rolling summary so context never overflows.
    |
    */

    'memory' => [
        'max_prompt_chars' => 6000,
    ],

    'summary' => [
        'fold_after' => 24,
        'keep_recent' => 12,
    ],

];
