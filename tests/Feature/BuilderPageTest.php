<?php

test('the builder shell renders with the chat and canvas', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('DevDojo Builder')
        ->assertSee('Publish')
        ->assertSee('default.test')
        ->assertSeeLivewire('chat');
});
