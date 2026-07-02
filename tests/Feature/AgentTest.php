<?php

use App\Ai\Agent;
use App\Ai\Contracts\Provider;
use App\Ai\ProviderResponse;
use App\Ai\ToolCall;
use App\Ai\Workspace;
use App\Models\Conversation;
use App\Models\Memory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\FakeProvider;

beforeEach(function () {
    Http::fake();

    $this->root = storage_path('framework/testing/agent-workspace-'.uniqid());

    File::ensureDirectoryExists($this->root);

    app()->instance(Workspace::class, new Workspace($this->root, protected: ['.env'], hidden: [], commands: []));
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

function fakeAgentRun(array $responses): FakeProvider
{
    $provider = new FakeProvider($responses);

    app()->instance(Provider::class, $provider);

    return $provider;
}

test('the agent executes tool calls, records actions, and persists its reply', function () {
    fakeAgentRun([
        new ProviderResponse(
            text: 'Creating the page now.',
            toolCalls: [new ToolCall('tool-1', 'write_file', ['path' => 'resources/views/pages/pricing.blade.php', 'content' => '<h1>Pricing</h1>'])],
            stopReason: 'tool_use',
            content: [
                ['type' => 'text', 'text' => 'Creating the page now.'],
                ['type' => 'tool_use', 'id' => 'tool-1', 'name' => 'write_file', 'input' => ['path' => 'resources/views/pages/pricing.blade.php', 'content' => '<h1>Pricing</h1>']],
            ],
        ),
        new ProviderResponse(text: 'I added a pricing page for you.'),
    ]);

    $conversation = Conversation::create();
    $conversation->messages()->create(['role' => 'user', 'content' => 'Add a pricing page']);

    $activity = [];
    $message = app(Agent::class)->respond($conversation, function (string $label) use (&$activity): void {
        $activity[] = $label;
    });

    expect(file_get_contents($this->root.'/resources/views/pages/pricing.blade.php'))->toBe('<h1>Pricing</h1>')
        ->and($message->content)->toBe('I added a pricing page for you.')
        ->and($message->actions)->toHaveCount(1)
        ->and($message->actions[0])->toMatchArray(['tool' => 'write_file', 'status' => 'ok'])
        ->and($message->meta['mutated'])->toBeTrue()
        ->and($activity)->toBe(['Wrote resources/views/pages/pricing.blade.php'])
        ->and($conversation->refresh()->title)->toBe('Add a pricing page');
});

test('tool failures are returned to the model as error results', function () {
    $provider = fakeAgentRun([
        new ProviderResponse(
            toolCalls: [new ToolCall('tool-1', 'edit_file', ['path' => 'missing.blade.php', 'search' => 'a', 'replace' => 'b'])],
            stopReason: 'tool_use',
            content: [['type' => 'tool_use', 'id' => 'tool-1', 'name' => 'edit_file', 'input' => ['path' => 'missing.blade.php', 'search' => 'a', 'replace' => 'b']]],
        ),
        new ProviderResponse(text: 'That file does not exist yet, so I created it instead.'),
    ]);

    $conversation = Conversation::create();
    $conversation->messages()->create(['role' => 'user', 'content' => 'Tweak the page']);

    $message = app(Agent::class)->respond($conversation);

    $toolResult = collect($provider->capturedMessages[1])->last()['content'][0];

    expect($message->actions[0]['status'])->toBe('error')
        ->and($message->meta['mutated'])->toBeFalse()
        ->and($toolResult['is_error'])->toBeTrue()
        ->and($toolResult['content'])->toContain('File not found');
});

test('the agent can save memories that persist across conversations', function () {
    fakeAgentRun([
        new ProviderResponse(
            toolCalls: [new ToolCall('tool-1', 'save_memory', ['slug' => 'brand-name', 'kind' => 'decision', 'content' => 'The product is called Nimbus.'])],
            stopReason: 'tool_use',
            content: [['type' => 'tool_use', 'id' => 'tool-1', 'name' => 'save_memory', 'input' => ['slug' => 'brand-name', 'kind' => 'decision', 'content' => 'The product is called Nimbus.']]],
        ),
        new ProviderResponse(text: 'Noted — Nimbus it is.'),
    ]);

    $conversation = Conversation::create();
    $conversation->messages()->create(['role' => 'user', 'content' => 'We are calling the product Nimbus']);

    app(Agent::class)->respond($conversation);

    expect(Memory::query()->where('slug', 'brand-name')->value('content'))->toBe('The product is called Nimbus.');
});

test('saved memories are injected into the next system prompt', function () {
    Memory::factory()->create(['slug' => 'brand-name', 'content' => 'The product is called Nimbus.']);

    $provider = fakeAgentRun([new ProviderResponse(text: 'Hello!')]);

    $conversation = Conversation::create();
    $conversation->messages()->create(['role' => 'user', 'content' => 'Hi']);

    app(Agent::class)->respond($conversation);

    expect($provider->capturedSystems[0])->toContain('The product is called Nimbus.');
});

test('long conversations fold their oldest turns into a rolling summary', function () {
    config(['builder.summary.fold_after' => 4, 'builder.summary.keep_recent' => 2]);

    $provider = fakeAgentRun([
        new ProviderResponse(text: 'Reply.'),
        new ProviderResponse(text: 'Earlier we designed the landing page and picked violet as the accent.'),
    ]);

    $conversation = Conversation::create();

    foreach (range(1, 5) as $i) {
        $conversation->messages()->create(['role' => $i % 2 ? 'user' : 'assistant', 'content' => "Turn {$i}"]);
    }

    app(Agent::class)->respond($conversation);

    $conversation->refresh();

    expect($conversation->summary)->toContain('violet')
        ->and($conversation->summarized_up_to)->not->toBeNull()
        ->and($conversation->unsummarizedMessages()->count())->toBeLessThanOrEqual(3);
});
