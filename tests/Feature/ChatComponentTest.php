<?php

use App\Models\Conversation;
use App\Models\Memory;
use Livewire\Livewire;

test('the chat renders the project card and suggestions', function () {
    Livewire::test('chat')
        ->assertSee('Initialized project')
        ->assertSee('Design a landing page for my product');
});

test('sending a message persists it and the preview engine replies', function () {
    Livewire::test('chat')
        ->set('draft', 'Build me a pricing page')
        ->call('send')
        ->assertSet('awaitingReply', true)
        ->assertSet('draft', '')
        ->assertSee('Build me a pricing page')
        ->call('reply')
        ->assertSet('awaitingReply', false)
        ->assertSee('preview mode')
        ->assertNotDispatched('app-updated');

    $conversation = Conversation::first();

    expect($conversation->messages)->toHaveCount(2)
        ->and($conversation->messages[0]->role)->toBe('user')
        ->and($conversation->messages[1]->role)->toBe('assistant')
        ->and($conversation->title)->toBe('Build me a pricing page');
});

test('blank messages are ignored', function () {
    Livewire::test('chat')
        ->set('draft', '   ')
        ->call('send')
        ->assertSet('awaitingReply', false);

    expect(Conversation::first()->messages)->toHaveCount(0);
});

test('suggestions fill the draft', function () {
    Livewire::test('chat')
        ->call('suggest', 'Add a pricing page with three tiers')
        ->assertSet('draft', 'Add a pricing page with three tiers');
});

test('a new chat starts a fresh conversation but reuses an empty one', function () {
    $component = Livewire::test('chat');
    $original = $component->get('conversationId');

    // Still empty — reuse it.
    $component->call('startConversation')->assertSet('conversationId', $original);

    $component->set('draft', 'Hello')->call('send')->call('reply');
    $component->call('startConversation');

    expect($component->get('conversationId'))->not->toBe($original)
        ->and(Conversation::count())->toBe(2);
});

test('the active conversation is restored from the session', function () {
    $conversation = Conversation::factory()->create();
    $conversation->messages()->create(['role' => 'user', 'content' => 'Earlier request']);

    session(['builder.conversation_id' => $conversation->id]);

    Livewire::test('chat')
        ->assertSet('conversationId', $conversation->id)
        ->assertSee('Earlier request');
});

test('past conversations can be reopened and deleted', function () {
    $old = Conversation::factory()->create(['title' => 'Rebrand everything']);
    $old->messages()->create(['role' => 'user', 'content' => 'Give it a darker look']);

    $component = Livewire::test('chat')
        ->call('openConversation', $old->id)
        ->assertSet('conversationId', $old->id)
        ->assertSee('Give it a darker look');

    $component->call('deleteConversation', $old->id);

    expect(Conversation::find($old->id))->toBeNull()
        ->and($component->get('conversationId'))->not->toBe($old->id);
});

test('memories are listed and can be forgotten from the panel', function () {
    $memory = Memory::factory()->create(['slug' => 'brand-name', 'content' => 'The product is called Nimbus.']);

    Livewire::test('chat')
        ->assertSee('brand-name')
        ->assertSee('The product is called Nimbus.')
        ->call('forgetMemory', $memory->id);

    expect(Memory::count())->toBe(0);
});
