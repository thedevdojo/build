<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
});

test('it reports the target app status when reachable', function () {
    Http::fake([
        'default.test/platform/api/status' => Http::response([
            'name' => 'Demo App',
            'environment' => 'production',
            'features' => ['auth' => true],
        ]),
    ]);

    $this->getJson('/api/target-status')
        ->assertOk()
        ->assertJsonPath('reachable', true)
        ->assertJsonPath('name', 'Demo App')
        ->assertJsonPath('features.auth', true);
});

test('it reports unreachable when the target app errors', function () {
    Http::fake([
        'default.test/platform/api/status' => Http::response(['message' => 'Unauthorized'], 401),
    ]);

    $this->getJson('/api/target-status')
        ->assertOk()
        ->assertJsonPath('reachable', false);
});

test('it reports unreachable when the target app is down', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $this->getJson('/api/target-status')
        ->assertOk()
        ->assertJsonPath('reachable', false);
});

test('it prefers the internal app url for server-to-server calls', function () {
    config(['platform.app_internal_url' => 'http://127.0.0.1:8000']);

    Http::fake([
        '127.0.0.1:8000/platform/api/status' => Http::response(['name' => 'In-Pod App']),
    ]);

    $this->getJson('/api/target-status')
        ->assertOk()
        ->assertJsonPath('reachable', true)
        ->assertJsonPath('name', 'In-Pod App');

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'http://127.0.0.1:8000/'));
});
