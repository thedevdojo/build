<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TargetApp
{
    /**
     * Fetch the target app's bridge status (name, environment, versions,
     * enabled foundation features), cached briefly since both the UI and
     * the agent's system prompt read it.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return Cache::remember('platform.target-status', 30, function (): array {
            $base = config('platform.app_internal_url') ?: config('platform.app_url');

            try {
                $response = Http::timeout(3)
                    ->acceptJson()
                    ->withHeaders(array_filter([
                        'X-Platform-Token' => config('platform.token'),
                    ]))
                    ->get(rtrim((string) $base, '/').'/platform/api/status');

                return $response->successful()
                    ? ['reachable' => true] + $response->json()
                    : ['reachable' => false];
            } catch (ConnectionException) {
                return ['reachable' => false];
            }
        });
    }
}
