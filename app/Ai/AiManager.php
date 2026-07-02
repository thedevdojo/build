<?php

namespace App\Ai;

use App\Ai\Contracts\Provider;
use App\Ai\Providers\AnthropicProvider;
use App\Ai\Providers\PreviewProvider;
use Illuminate\Support\Str;

class AiManager
{
    /**
     * Resolve the active provider. A container binding for the Provider
     * contract always wins (that's the extension point for custom
     * providers and for tests); otherwise the configured driver is used,
     * falling back to preview mode when its key is missing.
     */
    public function provider(): Provider
    {
        if (app()->bound(Provider::class)) {
            return app(Provider::class);
        }

        $anthropic = config('builder.providers.anthropic');

        if (config('builder.provider') === 'anthropic' && filled($anthropic['key'] ?? null)) {
            return new AnthropicProvider(
                $anthropic['key'],
                $anthropic['model'] ?? 'claude-fable-5',
                (int) ($anthropic['max_tokens'] ?? 8192),
            );
        }

        return new PreviewProvider;
    }

    public function isLive(): bool
    {
        return $this->provider()->supportsTools();
    }

    /**
     * Short human label for the active engine, shown in the composer.
     */
    public function label(): string
    {
        $provider = $this->provider();

        if (! $provider->supportsTools()) {
            return 'Preview';
        }

        $model = Str::after($provider->name(), ':');

        return Str::of($model)->after('claude-')->replace('-', ' ')->title()->value();
    }
}
